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
    Plus,
    ArrowRightLeft,
    XCircle,
    Banknote,
    Package,
} from "lucide-vue-next";
import Tooltip from "@/Components/Tooltip.vue";
import PageHeader from "@/Components/PageHeader.vue";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";

const props = defineProps({
    table: { type: Object, required: true },
    products: { type: Array, required: true },
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

function handleKeydown(event) {
    if (
        !showProductOptions.value &&
        ["ArrowDown", "ArrowUp"].includes(event.key)
    ) {
        showProductOptions.value = true;
        return;
    }

    switch (event.key) {
        case "ArrowDown":
            event.preventDefault();
            if (filteredProducts.value.length === 0) return;
            highlightedIndex.value =
                (highlightedIndex.value + 1) % filteredProducts.value.length;
            scrollToHighlighted();
            break;

        case "ArrowUp":
            event.preventDefault();
            if (filteredProducts.value.length === 0) return;
            highlightedIndex.value =
                (highlightedIndex.value - 1 + filteredProducts.value.length) %
                filteredProducts.value.length;
            scrollToHighlighted();
            break;

        case "Enter":
            event.preventDefault();
            if (
                showProductOptions.value &&
                highlightedIndex.value >= 0 &&
                filteredProducts.value[highlightedIndex.value]
            ) {
                selectAndAdd(filteredProducts.value[highlightedIndex.value]);
            } else if (selectedProductId.value) {
                // Si ya hay uno seleccionado pero no hay highlight, agrega el actual
                addItem();
            }
            break;

        case "Escape":
            showProductOptions.value = false;
            highlightedIndex.value = -1;
            break;
    }
}

function scrollToHighlighted() {
    nextTick(() => {
        const el = productListRef.value?.children?.[highlightedIndex.value];
        el?.scrollIntoView({ block: "nearest" });
    });
}

function selectAndAdd(product) {
    selectedProductId.value = product.id;
    productSearch.value = product.name;
    showProductOptions.value = false;
    highlightedIndex.value = -1;

    // Agregar directo
    nextTick(() => {
        addItem();
    });
}

function selectProduct(product) {
    selectedProductId.value = product.id;
    productSearch.value = product.name;
    showProductOptions.value = false;
}

function clearProduct() {
    selectedProductId.value = "";
    productSearch.value = "";
    showProductOptions.value = false;
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

function addItem() {
    if (!selectedProductId.value) return;

    router.post(
        route("orders.addItem", props.table.id),
        {
            product_id: selectedProductId.value,
            quantity: selectedQuantity.value,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                selectedProductId.value = "";
                productSearch.value = "";
                selectedQuantity.value = 1;
            },
        },
    );
}

let debounceTimer = null;
function updateQuantity(item, quantity) {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
        router.patch(
            route("orders.updateItem", item.id),
            { quantity },
            { preserveScroll: true },
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

            <!-- Agregar producto -->
            <div
                class="flex flex-wrap items-end gap-3 rounded-lg border border-line bg-surface p-4 shadow-sm"
            >
                <div class="flex-1 min-w-[200px]">
                    <label
                        class="text-xs font-medium text-ink/100 items-start gap-1.5 flex mb-1"
                    >
                        <Package class="h-4 w-4" />
                        Producto
                    </label>
                    <div class="relative" ref="productDropdownRef">
                        <input
                            v-model="productSearch"
                            type="text"
                            placeholder="Buscar producto..."
                            autocomplete="off"
                            class="mt-1 w-full rounded border-line pr-8 text-sm focus:border-accent focus:ring-accent"
                            @focus="showProductOptions = true"
                            @input="showProductOptions = true"
                            @keydown="handleKeydown"
                        />

                        <!-- Botón limpiar -->
                        <button
                            v-if="productSearch"
                            type="button"
                            class="absolute right-2 top-1/2 -translate-y-1/2 mt-0.5 text-ink/40 hover:text-ink"
                            @mousedown.prevent
                            @click.stop="clearProduct"
                            aria-label="Limpiar producto"
                        >
                            ✕
                        </button>

                        <div
                            v-if="showProductOptions"
                            ref="productListRef"
                            class="absolute z-50 mt-1 max-h-60 w-full overflow-y-auto rounded border border-line bg-surface shadow-lg"
                        >
                            <button
                                v-for="(product, index) in filteredProducts"
                                :key="product.id"
                                type="button"
                                :class="[
                                    'flex w-full items-center justify-between px-3 py-2 text-left text-sm',
                                    index === highlightedIndex
                                        ? 'bg-accent/10'
                                        : 'hover:bg-paper',
                                ]"
                                @mouseenter="highlightedIndex = index"
                                @click="selectProduct(product)"
                            >
                                <span class="truncate text-ink">
                                    {{ product.name }}
                                </span>

                                <span
                                    class="ml-3 shrink-0 text-xs text-ink/100"
                                >
                                    {{
                                        new Intl.NumberFormat("es-CO", {
                                            style: "currency",
                                            currency: "COP",
                                            maximumFractionDigits: 0,
                                        }).format(product.sale_price)
                                    }}
                                </span>
                            </button>

                            <div
                                v-if="filteredProducts.length === 0"
                                class="px-3 py-3 text-center text-sm text-ink/40"
                            >
                                No se encontraron productos.
                            </div>
                        </div>
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-medium text-ink/100"
                        >Cantidad</label
                    >
                    <input
                        v-model.number="selectedQuantity"
                        type="number"
                        min="1"
                        class="mt-1 w-20 rounded border-line text-sm focus:border-accent focus:ring-accent"
                    />
                </div>
                <button
                    class="flex items-center gap-1.5 rounded bg-accent px-4 py-2 text-sm font-medium text-white hover:bg-accent-dark"
                    @click="addItem"
                >
                    <Plus class="h-4 w-4" /> Agregar
                </button>
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
