<script setup>
import {
    computed,
    ref,
    onMounted,
    onBeforeUnmount,
    watch,
    nextTick,
} from "vue";
import { router } from "@inertiajs/vue3";
import {
    Trash2,
    Minus,
    Plus,
    Image as ImageIcon,
    ArrowRightLeft,
    XCircle,
    Banknote,
    ArrowLeft,
} from "lucide-vue-next";
import Tooltip from "@/Components/Tooltip.vue";
import PageHeader from "@/Components/PageHeader.vue";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { useToast } from "@/composables/useToast";

const props = defineProps({
    table: { type: Object, required: true },
    menu: { type: Array, required: true },
    otherFreeTables: { type: Array, required: true },
});

const toast = useToast();

const paymentMethods = [
    { value: "cash", label: "Efectivo" },
    { value: "transfer", label: "Transferencia" },
];

const order = computed(() => props.table.active_order);
const items = computed(() => order.value?.items ?? []);

const total = computed(() =>
    new Intl.NumberFormat("es-CO", {
        style: "currency",
        currency: "COP",
        maximumFractionDigits: 0,
    }).format(order.value?.total ?? 0),
);

const paymentMethod = ref("cash");
const showMoveModal = ref(false);
const showCancelModal = ref(false);
const isPaying = ref(false);
const pendingProductIds = ref(new Set()); // productos con un toque de suma en curso
const pendingItemIds = ref(new Set()); // líneas del pedido con una resta en curso
const customerName = ref(order.value?.customer_name ?? "");
const customerNameInputRef = ref(null);

const selectedCategoryKey = ref(null); // null = mostrando las cards de categorías

let pollTimer = null;

const selectedCategory = computed(
    () =>
        props.menu.find(
            (c) => (c.id ?? "uncategorized") === selectedCategoryKey.value,
        ) ?? null,
);

// Productos del menú indexados por id, para validar stock al sumar desde la lista del pedido
const productsById = computed(
    () => new Map(props.menu.flatMap((c) => c.products).map((p) => [p.id, p])),
);

watch(
    () => order.value?.id,
    () => {
        customerName.value = order.value?.customer_name ?? "";
    },
);

function formatMoney(value) {
    return new Intl.NumberFormat("es-CO", {
        style: "currency",
        currency: "COP",
        maximumFractionDigits: 0,
    }).format(value);
}

function openCategory(category) {
    selectedCategoryKey.value = category.id ?? "uncategorized";
}

function backToCategories() {
    selectedCategoryKey.value = null;
}

// Con reserva de stock al agregar, product.stock ya descuenta lo que hay en pedidos abiertos
function remainingStock(product) {
    return product.stock;
}

// Línea del pedido que corresponde a un producto (o null si no está en el pedido)
function itemFor(productId) {
    return (
        items.value.find(
            (i) => i.product_id === productId || i.product?.id === productId,
        ) ?? null
    );
}

// Cantidad ya agregada al pedido actual para un producto — se muestra como badge en su card
function quantityInOrder(productId) {
    return itemFor(productId)?.quantity ?? 0;
}

// Pone en mayúscula solo la primera letra, sin tocar el resto de lo que ya escribió
function capitalizeFirst(value) {
    if (!value) return value;
    return value.charAt(0).toUpperCase() + value.slice(1);
}

// Aplica la capitalización preservando la posición del cursor — sin esto,
// el cursor saltaría al final del texto cada vez que se transforma el valor
function handleCapitalizeInput(event, elRef, setter) {
    const el = event.target;
    const start = el.selectionStart;
    const end = el.selectionEnd;
    const capitalized = capitalizeFirst(el.value);

    setter(capitalized);

    if (capitalized !== el.value) {
        nextTick(() => {
            elRef.value?.setSelectionRange(start, end);
        });
    }
}

let customerNameDebounce = null;

function handleCustomerNameInput(event) {
    handleCapitalizeInput(
        event,
        customerNameInputRef,
        (v) => (customerName.value = v),
    );

    if (!order.value) return;

    clearTimeout(customerNameDebounce);
    customerNameDebounce = setTimeout(() => {
        router.patch(
            route("orders.updateCustomerName", order.value.id),
            { customer_name: customerName.value },
            { preserveScroll: true, preserveState: true },
        );
    }, 500);
}

// Suma 1 unidad de un producto al pedido
function tapProduct(product) {
    if (pendingProductIds.value.has(product.id)) return;

    // Aviso inmediato, sin esperar al servidor
    if (remainingStock(product) <= 0) {
        toast.error(
            quantityInOrder(product.id) > 0
                ? `Ya agregaste todas las unidades disponibles de "${product.name}".`
                : `"${product.name}" está agotado.`,
        );
        return;
    }

    pendingProductIds.value.add(product.id);

    router.post(
        route("orders.addItem", props.table.id),
        { product_id: product.id, quantity: 1 },
        {
            preserveScroll: true,
            preserveState: true,
            // El servidor sigue siendo la autoridad: cubre el caso en que otro
            // dispositivo vendió la última unidad justo antes de este toque
            onError: (errors) => {
                const message = errors.stock ?? errors.quantity ?? errors.order;
                if (message) toast.error(message);
            },
            onFinish: () => pendingProductIds.value.delete(product.id),
        },
    );
}

// Resta 1 unidad de una línea del pedido (si queda en 0, el servidor la elimina)
function decrementItem(item) {
    if (!item || pendingItemIds.value.has(item.id)) return;

    pendingItemIds.value.add(item.id);

    router.patch(
        route("orders.decrementItem", item.id),
        {},
        {
            preserveScroll: true,
            preserveState: true,
            onError: (errors) => {
                if (errors.order) toast.error(errors.order);
            },
            onFinish: () => pendingItemIds.value.delete(item.id),
        },
    );
}

// Suma 1 desde la lista del pedido. Reutiliza tapProduct: misma validación de stock y
// mismo aviso. Si el producto ya no está en el menú (ej. se desactivó), el servidor decide.
function incrementItem(item) {
    const product = productsById.value.get(item.product_id);

    tapProduct(
        product ?? {
            id: item.product_id,
            name: item.product.name,
            stock: Number.MAX_SAFE_INTEGER,
        },
    );
}

// Cantidad escrita a mano en el campo
function onQuantityInput(item, event) {
    const raw = event.target.value;

    // Campo vacío = está a mitad de escribir. No se interpreta como 0, porque eliminaría la línea
    if (raw === "") return;

    updateQuantity(item, Number(raw), event.target);
}

function onQuantityBlur(item, event) {
    if (event.target.value === "") event.target.value = item.quantity;
}

let debounceTimer = null;
function updateQuantity(item, quantity, inputEl) {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
        router.patch(
            route("orders.updateItem", item.id),
            { quantity },
            {
                preserveScroll: true,
                onError: (errors) => {
                    const message =
                        errors.stock ?? errors.quantity ?? errors.order;
                    if (message) toast.error(message);
                    // Vuelve a mostrar la cantidad real; si no, el campo se queda con el número rechazado
                    if (inputEl) inputEl.value = item.quantity;
                },
            },
        );
    }, 400);
}

function pay() {
    if (!order.value || isPaying.value) return;

    isPaying.value = true;

    router.post(
        route("payments.store", order.value.id),
        { method: paymentMethod.value },
        {
            onError: (errors) =>
                toast.error(
                    errors.stock ??
                        errors.order ??
                        "No se pudo registrar el cobro.",
                ),
            onFinish: () => (isPaying.value = false),
        },
    );
}

function moveToTable(newTableId) {
    router.patch(route("orders.move", order.value.id), {
        table_id: newTableId,
    });
}

function cancelOrder(reason) {
    router.patch(route("orders.cancel", order.value.id), { reason });
}

onMounted(() => {
    pollTimer = setInterval(() => {
        router.reload({
            only: ["table", "menu"],
            preserveScroll: true,
            preserveState: true,
        });
    }, 4000);
});

onBeforeUnmount(() => {
    clearInterval(pollTimer);
});
</script>

<template>
    <AuthenticatedLayout>
        <template #header>
            <PageHeader :title="table.name" :back-href="route('tables.index')">
                <template #right>
                    <span
                        class="rounded-full bg-paper px-3 py-1 text-xs font-medium text-ink/60"
                    >
                        Capacidad {{ table.capacity }}
                    </span>
                </template>
            </PageHeader>

            <div v-if="order" class="mt-3">
                <input
                    ref="customerNameInputRef"
                    :value="customerName"
                    type="text"
                    placeholder="Nombre del cliente (opcional)"
                    class="w-full max-w-xs rounded-md border-line bg-surface text-sm focus:border-accent focus:ring-accent"
                    @input="handleCustomerNameInput"
                />
            </div>
        </template>

        <div class="mx-auto max-w-4xl px-4 py-8">
            <!-- Items del pedido -->
            <div
                v-if="order"
                class="overflow-hidden rounded-lg border border-line bg-surface shadow-sm"
            >
                <div
                    class="flex items-center justify-between border-b border-line bg-accent px-4 py-2.5 text-sm font-medium text-white"
                >
                    <span>Pedido</span>
                    <span
                        >{{ items.length }} producto{{
                            items.length === 1 ? "" : "s"
                        }}</span
                    >
                </div>

                <ul class="divide-y divide-line/60">
                    <li
                        v-for="item in items"
                        :key="item.id"
                        class="flex items-center gap-3 px-4 py-3"
                    >
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-normal font-medium text-ink">
                                {{ item.product.name }}
                            </p>
                            <p class="text-xs text-ink/60">
                                {{ formatMoney(item.unit_price) }} c/u ·
                                <span class="text-sm font-semibold text-ink">{{
                                    formatMoney(item.subtotal)
                                }}</span>
                            </p>
                        </div>

                        <!-- Control de cantidad: botones de 40px, cómodos para el dedo y para el mouse -->
                        <div
                            class="flex shrink-0 items-center overflow-hidden rounded-lg border border-line"
                        >
                            <button
                                type="button"
                                class="flex h-10 w-10 items-center justify-center hover:bg-paper active:bg-paper disabled:opacity-40"
                                :disabled="pendingItemIds.has(item.id)"
                                :aria-label="
                                    item.quantity <= 1
                                        ? 'Quitar producto'
                                        : 'Quitar una unidad'
                                "
                                @click="decrementItem(item)"
                            >
                                <component
                                    :is="item.quantity <= 1 ? Trash2 : Minus"
                                    class="h-4 w-4"
                                    :class="
                                        item.quantity <= 1
                                            ? 'text-danger'
                                            : 'text-ink/70'
                                    "
                                />
                            </button>

                            <input
                                type="number"
                                inputmode="numeric"
                                min="0"
                                :value="item.quantity"
                                class="h-10 w-12 border-0 border-x border-line bg-surface p-0 text-center text-sm font-semibold text-ink focus:ring-1 focus:ring-accent [appearance:textfield] [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none"
                                @input="onQuantityInput(item, $event)"
                                @blur="onQuantityBlur(item, $event)"
                            />

                            <button
                                type="button"
                                class="flex h-10 w-10 items-center justify-center text-ink/70 hover:bg-paper active:bg-paper disabled:opacity-40"
                                :disabled="
                                    pendingProductIds.has(item.product_id)
                                "
                                aria-label="Agregar una unidad"
                                @click="incrementItem(item)"
                            >
                                <Plus class="h-4 w-4" />
                            </button>
                        </div>
                    </li>
                </ul>

                <p
                    v-if="items.length === 0"
                    class="px-4 py-6 text-center text-sm text-ink/40"
                >
                    Sin productos aún.
                </p>
            </div>

            <div
                v-else
                class="rounded-lg border border-dashed border-line bg-surface/50 p-6 text-center text-ink/70"
            >
                Esta mesa está libre. Agrega el primer producto para abrir el
                pedido.
            </div>

            <!-- Selector de productos por categoría -->
            <div class="rounded-lg border border-line bg-surface p-4 shadow-sm">
                <!-- Vista: categorías -->
                <div v-if="!selectedCategory">
                    <h2 class="mb-3 text-sm font-semibold text-ink">
                        Selecciona una categoría
                    </h2>
                    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                        <button
                            v-for="category in menu"
                            :key="category.id ?? 'uncategorized'"
                            class="flex flex-col items-center justify-center gap-1 rounded-lg border border-line bg-paper/60 px-3 py-5 text-center transition hover:border-accent hover:bg-accent/5"
                            @click="openCategory(category)"
                        >
                            <span class="text-base font-semibold text-ink">{{
                                category.name
                            }}</span>
                            <span class="text-xs text-ink/60"
                                >{{ category.products.length }} producto{{
                                    category.products.length === 1 ? "" : "s"
                                }}</span
                            >
                        </button>
                    </div>
                    <p
                        v-if="menu.length === 0"
                        class="py-6 text-center text-sm text-ink/40"
                    >
                        No hay productos activos configurados todavía.
                    </p>
                </div>

                <!-- Vista: productos de la categoría seleccionada -->
                <div v-else>
                    <div class="mb-3 flex items-center gap-2">
                        <button
                            class="flex h-8 w-8 items-center justify-center rounded-full border border-line text-ink/50 hover:border-accent hover:text-accent-dark"
                            @click="backToCategories"
                        >
                            <ArrowLeft class="h-4 w-4" />
                        </button>
                        <h2 class="text-sm font-semibold text-ink">
                            {{ selectedCategory.name }}
                        </h2>
                    </div>

                    <div
                        class="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4"
                    >
                        <div
                            v-for="product in selectedCategory.products"
                            :key="product.id"
                            class="relative overflow-hidden rounded-lg border border-line bg-paper/60 transition hover:border-accent"
                            :class="{ 'opacity-50': product.stock <= 0 }"
                        >
                            <!-- Cuerpo de la tarjeta: tocar suma 1 unidad -->
                            <button
                                type="button"
                                class="flex w-full flex-col text-left transition hover:bg-accent/5 active:scale-[0.98] disabled:cursor-wait disabled:opacity-60"
                                :disabled="pendingProductIds.has(product.id)"
                                @click="tapProduct(product)"
                            >
                                <div
                                    class="flex aspect-square items-center justify-center bg-surface"
                                >
                                    <img
                                        v-if="product.image_url"
                                        :src="product.image_url"
                                        :alt="product.name"
                                        class="h-full w-full object-contain"
                                        loading="lazy"
                                    />
                                    <ImageIcon
                                        v-else
                                        class="h-8 w-8 text-ink/15"
                                    />
                                </div>

                                <div class="p-2">
                                    <p
                                        class="truncate text-xs font-medium text-ink"
                                    >
                                        {{ product.name }}
                                    </p>
                                    <p class="text-xs text-ink/50">
                                        {{ formatMoney(product.sale_price) }}
                                    </p>
                                    <p
                                        v-if="product.stock <= 0"
                                        class="text-xs font-medium text-danger"
                                    >
                                        {{
                                            quantityInOrder(product.id) > 0
                                                ? "Sin más disponibles"
                                                : "Agotado"
                                        }}
                                    </p>
                                    <p
                                        v-else-if="product.stock <= 5"
                                        class="text-xs text-warn"
                                    >
                                        Quedan {{ product.stock }}
                                    </p>
                                </div>
                            </button>

                            <!-- Restar 1: solo aparece si el producto ya está en el pedido -->
                            <button
                                v-if="quantityInOrder(product.id) > 0"
                                type="button"
                                class="absolute left-1.5 top-1.5 z-10 flex h-9 w-9 items-center justify-center rounded-full border border-line bg-surface text-ink shadow hover:bg-paper active:bg-paper disabled:opacity-50"
                                :disabled="
                                    pendingItemIds.has(itemFor(product.id)?.id)
                                "
                                aria-label="Quitar una unidad"
                                @click="decrementItem(itemFor(product.id))"
                            >
                                <Minus class="h-4 w-4" />
                            </button>

                            <!-- Badge de cantidad: rojo cuando ya no se puede agregar más -->
                            <span
                                v-if="quantityInOrder(product.id) > 0"
                                class="pointer-events-none absolute right-1.5 top-1.5 z-10 flex h-6 min-w-6 items-center justify-center rounded-full px-1.5 text-xs font-semibold text-white"
                                :class="
                                    remainingStock(product) <= 0
                                        ? 'bg-danger'
                                        : 'bg-accent'
                                "
                            >
                                {{ quantityInOrder(product.id) }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Total: el punto focal real de la pantalla -->
            <template v-if="order">
                <div
                    class="flex items-center justify-between rounded-lg border-y border-l-4 border-r border-line border-l-accent bg-surface p-5 shadow-sm"
                >
                    <div>
                        <p
                            class="text-xs font-medium uppercase tracking-wide text-ink"
                        >
                            Total del pedido
                        </p>
                        <p class="mt-0.5 text-2xl font-semibold text-ink">
                            {{ total }}
                        </p>
                    </div>

                    <div class="flex gap-2">
                        <Tooltip text="Cambiar de mesa">
                            <button
                                class="rounded border border-line p-2.5 text-ink/70 hover:bg-paper"
                                @click="showMoveModal = true"
                            >
                                <ArrowRightLeft class="h-4 w-4" />
                            </button>
                        </Tooltip>
                        <Tooltip text="Cancelar pedido">
                            <button
                                class="rounded border border-danger/30 p-2.5 text-danger hover:bg-danger/5"
                                @click="showCancelModal = true"
                            >
                                <XCircle class="h-4 w-4" />
                            </button>
                        </Tooltip>
                    </div>
                </div>

                <div
                    class="flex flex-col gap-3 rounded-lg bg-accent/20 p-4 sm:flex-row sm:items-center"
                >
                    <!-- Método de pago: botones grandes en vez de un select, más fáciles de tocar en celular -->
                    <div class="grid grid-cols-2 gap-2 sm:w-80">
                        <button
                            v-for="method in paymentMethods"
                            :key="method.value"
                            type="button"
                            class="rounded-md border px-3 py-2.5 text-sm font-medium transition"
                            :class="
                                paymentMethod === method.value
                                    ? 'border-accent bg-surface text-accent-dark shadow-sm'
                                    : 'border-transparent bg-surface/50 text-ink/50 hover:bg-surface'
                            "
                            @click="paymentMethod = method.value"
                        >
                            {{ method.label }}
                        </button>
                    </div>

                    <button
                        class="flex w-full items-center justify-center gap-2 rounded-md bg-accent px-5 py-3 text-base font-semibold text-white hover:bg-accent-dark disabled:cursor-not-allowed disabled:opacity-50 sm:flex-1"
                        :disabled="items.length === 0 || isPaying"
                        @click="pay"
                    >
                        <Banknote class="h-5 w-5" />
                        {{
                            isPaying ? "Procesando..." : "Cobrar y cerrar mesa"
                        }}
                    </button>
                </div>
            </template>

            <!-- Modal: cambiar de mesa -->
            <div
                v-if="showMoveModal"
                class="fixed inset-0 z-50 flex items-center justify-center bg-ink/30 p-4"
                @click.self="showMoveModal = false"
            >
                <div class="w-full max-w-sm rounded-lg bg-surface p-5">
                    <h2 class="mb-3 font-semibold text-ink">
                        Mover pedido a...
                    </h2>
                    <div class="space-y-2">
                        <button
                            v-for="freeTable in otherFreeTables"
                            :key="freeTable.id"
                            class="block w-full rounded border border-line px-3 py-2 text-left text-sm hover:border-accent"
                            @click="moveToTable(freeTable.id)"
                        >
                            {{ freeTable.name }}
                        </button>
                        <p
                            v-if="otherFreeTables.length === 0"
                            class="text-sm text-ink/40"
                        >
                            No hay mesas libres.
                        </p>
                    </div>
                </div>
            </div>

            <!-- Modal: cancelar pedido -->
            <div
                v-if="showCancelModal"
                class="fixed inset-0 z-50 flex items-center justify-center bg-ink/30 p-4"
                @click.self="showCancelModal = false"
            >
                <div class="w-full max-w-sm rounded-lg bg-surface p-5">
                    <h2 class="mb-3 font-semibold text-ink">
                        ¿Cancelar este pedido?
                    </h2>
                    <p class="mb-4 text-sm text-ink/80">
                        Esta acción libera la mesa y devuelve el stock de los
                        productos del pedido. No se ha cobrado nada.
                    </p>
                    <div class="flex justify-end gap-2">
                        <button
                            class="rounded px-3 py-2 text-sm text-ink/70"
                            @click="showCancelModal = false"
                        >
                            Volver
                        </button>
                        <button
                            class="rounded bg-danger px-3 py-2 text-sm text-white hover:bg-danger/90"
                            @click="cancelOrder('Cancelado desde el panel')"
                        >
                            Sí, cancelar
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
