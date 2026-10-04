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
    Image as ImageIcon,
    ArrowRightLeft,
    XCircle,
    Banknote,
    ArrowLeft,
} from "lucide-vue-next";
import Tooltip from "@/Components/Tooltip.vue";
import PageHeader from "@/Components/PageHeader.vue";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";

const props = defineProps({
    table: { type: Object, required: true },
    menu: { type: Array, required: true },
    otherFreeTables: { type: Array, required: true },
});

const order = computed(() => props.table.active_order);
const items = computed(() => order.value?.items ?? []);

const total = computed(() =>
    new Intl.NumberFormat("es-CO", {
        style: "currency",
        currency: "COP",
        maximumFractionDigits: 0,
    }).format(order.value?.total ?? 0),
);

const selectedProductId = ref("");
const selectedQuantity = ref(1);
const productSearch = ref("");
const showProductOptions = ref(false);
const productDropdownRef = ref(null);
const highlightedIndex = ref(-1);
const productListRef = ref(null);
const paymentMethod = ref("cash");
const showMoveModal = ref(false);
const showCancelModal = ref(false);
const isPaying = ref(false);
const stockError = ref(null);
const selectedCategory = ref(null); // null = mostrando las cards de categorías
const pendingProductIds = ref(new Set()); // productos con un tap en curso (para deshabilitar su card)
const customerName = ref(order.value?.customer_name ?? "");
const customerNameInputRef = ref(null);

let pollTimer = null;

const filteredProducts = computed(() => {
    const search = productSearch.value.trim().toLowerCase();

    if (!search) {
        return props.products;
    }

    return props.products.filter((product) =>
        product.name.toLowerCase().includes(search),
    );
});

watch(filteredProducts, () => {
    highlightedIndex.value = -1;
});

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
    selectedCategory.value = category;
}

function backToCategories() {
    selectedCategory.value = null;
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

// Cantidad ya agregada al pedido actual para un producto — se muestra como badge en su card
function quantityInOrder(productId) {
    const item = items.value.find(
        (i) => i.product_id === productId || i.product?.id === productId,
    );
    return item?.quantity ?? 0;
}

function tapProduct(product) {
    if (pendingProductIds.value.has(product.id)) return; // evita doble tap mientras procesa

    stockError.value = null;
    pendingProductIds.value.add(product.id);

    router.post(
        route("orders.addItem", props.table.id),
        { product_id: product.id, quantity: 1 },
        {
            preserveScroll: true,
            preserveState: true,
            onError: (errors) => {
                stockError.value = errors.stock ?? null;
            },
            onFinish: () => {
                pendingProductIds.value.delete(product.id);
            },
        },
    );
}

function scrollToHighlighted() {
    nextTick(() => {
        const el = productListRef.value?.children?.[highlightedIndex.value];
        el?.scrollIntoView({ block: "nearest" });
    });
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

function handleClickOutside(event) {
    if (
        productDropdownRef.value &&
        !productDropdownRef.value.contains(event.target)
    ) {
        showProductOptions.value = false;
    }
}

onMounted(() => {
    document.addEventListener("click", handleClickOutside);

    pollTimer = setInterval(() => {
        router.reload({
            only: ["table"],
            preserveScroll: true,
            preserveState: true,
        });
    }, 4000);
});

onBeforeUnmount(() => {
    document.removeEventListener("click", handleClickOutside);
    clearInterval(pollTimer);
});

let debounceTimer = null;
function updateQuantity(item, quantity) {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
        stockError.value = null;

        router.patch(
            route("orders.updateItem", item.id),
            { quantity },
            {
                preserveScroll: true,
                onError: (errors) => {
                    stockError.value = errors.stock ?? null;
                },
            },
        );
    }, 400);
}

function removeItem(item) {
    router.delete(route("orders.removeItem", item.id), {
        preserveScroll: true,
    });
}

function pay() {
    if (!order.value || isPaying.value) return;

    isPaying.value = true;

    router.post(
        route("payments.store", order.value.id),
        { method: paymentMethod.value },
        { onFinish: () => (isPaying.value = false) },
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
                    v-model="customerName"
                    type="text"
                    placeholder="Nombre del cliente (opcional)"
                    class="w-full max-w-xs rounded-md border-line bg-surface text-sm focus:border-accent focus:ring-accent"
                    @input="handleCustomerNameInput"
                />
            </div>
        </template>

        <div class="mx-auto max-w-4xl px-4 py-8 space-y-4">
            <!-- Items del pedido -->
            <div
                v-if="order"
                class="overflow-hidden rounded-lg border border-line bg-surface shadow-sm"
            >
                <table class="w-full text-sm">
                    <thead
                        class="border-b border-line bg-accent text-white text-left"
                    >
                        <tr>
                            <th class="px-4 py-2.5 font-medium">Producto</th>
                            <th class="px-4 py-2.5 font-medium">Cant.</th>
                            <th class="px-4 py-2.5 font-medium">Subtotal</th>
                            <th class="px-4 py-2.5"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="item in items"
                            :key="item.id"
                            class="border-b border-line/60 last:border-0"
                        >
                            <td class="px-4 py-2.5 text-ink">
                                {{ item.product.name }}
                            </td>
                            <td class="px-4 py-2.5">
                                <input
                                    type="number"
                                    min="0"
                                    :value="item.quantity"
                                    class="w-16 rounded border-line text-sm focus:border-accent focus:ring-accent"
                                    @input="
                                        updateQuantity(
                                            item,
                                            Number($event.target.value),
                                        )
                                    "
                                />
                            </td>
                            <td class="px-4 py-2.5 font-medium text-ink">
                                {{
                                    new Intl.NumberFormat("es-CO", {
                                        style: "currency",
                                        currency: "COP",
                                        maximumFractionDigits: 0,
                                    }).format(item.subtotal)
                                }}
                            </td>
                            <td class="px-4 py-2.5 text-right">
                                <Tooltip text="Quitar">
                                    <button
                                        class="flex items-center gap-1 text-xs font-medium text-danger hover:underline"
                                        @click="removeItem(item)"
                                    >
                                        <Trash2 class="h-3.5 w-3.5" />
                                    </button>
                                </Tooltip>
                            </td>
                        </tr>
                        <tr v-if="items.length === 0">
                            <td
                                colspan="4"
                                class="px-4 py-6 text-center text-ink/40"
                            >
                                Sin productos aún.
                            </td>
                        </tr>
                    </tbody>
                </table>
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
                            <span class="text-nomral font-semibold text-ink">{{
                                category.name
                            }}</span>
                            <span class="text-xs text-ink/40"
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
                        <button
                            v-for="product in selectedCategory.products"
                            :key="product.id"
                            class="relative flex flex-col overflow-hidden rounded-lg border border-line bg-paper/60 text-left transition hover:border-accent hover:bg-accent/5 disabled:cursor-not-allowed disabled:opacity-40"
                            :disabled="
                                product.stock <= 0 ||
                                pendingProductIds.has(product.id)
                            "
                            @click="tapProduct(product)"
                        >
                            <!-- Badge de cantidad ya en el pedido -->
                            <span
                                v-if="quantityInOrder(product.id) > 0"
                                class="absolute right-1.5 top-1.5 z-10 flex h-5 min-w-5 items-center justify-center rounded-full bg-accent px-1 text-xs font-semibold text-white"
                            >
                                {{ quantityInOrder(product.id) }}
                            </span>

                            <!-- Imagen o placeholder -->
                            <div
                                class="flex aspect-square items-center justify-center bg-surface"
                            >
                                <img
                                    v-if="product.image_url"
                                    :src="product.image_url"
                                    :alt="product.name"
                                    class="h-full w-full object-cover"
                                    loading="lazy"
                                />
                                <ImageIcon v-else class="h-8 w-8 text-ink/15" />
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
                                    Agotado
                                </p>
                                <p
                                    v-else-if="product.stock <= 5"
                                    class="text-xs text-warn"
                                >
                                    Quedan {{ product.stock }}
                                </p>
                            </div>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Mensaje de error de stock -->
            <div
                v-if="stockError"
                class="rounded-lg border border-danger/30 bg-danger/5 px-4 py-2.5 text-sm text-danger"
            >
                {{ stockError }}
            </div>

            <!-- Total: el punto focal real de la pantalla -->
            <template v-if="order">
                <div
                    class="flex items-center justify-between rounded-lg border-l-4 border-l-accent border-y border-r border-line bg-surface p-5 shadow-sm"
                >
                    <div>
                        <p
                            class="text-xs font-medium uppercase tracking-wide text-ink/70"
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
                    class="flex items-center justify-between rounded-lg bg-accent/20 p-4"
                >
                    <select
                        v-model="paymentMethod"
                        class="rounded border-0 text-sm focus:ring-accent"
                    >
                        <option value="cash">Efectivo</option>
                        <option value="transfer">Transferencia</option>
                    </select>
                    <button
                        class="flex items-center gap-1.5 rounded bg-accent px-5 py-2.5 text-sm font-semibold text-white hover:bg-accent-dark disabled:cursor-not-allowed disabled:opacity-50"
                        :disabled="items.length === 0 || isPaying"
                        @click="pay"
                    >
                        <Banknote class="h-4 w-4" />
                        {{
                            isPaying ? "Procesando..." : "Cobrar y cerrar mesa"
                        }}
                    </button>
                </div>
            </template>

            <!-- Modal: cambiar de mesa -->
            <div
                v-if="showMoveModal"
                class="fixed inset-0 flex items-center justify-center bg-ink/30"
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
                class="fixed inset-0 flex items-center justify-center bg-ink/30"
                @click.self="showCancelModal = false"
            >
                <div class="w-full max-w-sm rounded-lg bg-surface p-5">
                    <h2 class="mb-3 font-semibold text-ink">
                        ¿Cancelar este pedido?
                    </h2>
                    <p class="mb-4 text-sm text-ink/80">
                        Esta acción libera la mesa. No se ha cobrado nada.
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
