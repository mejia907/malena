<script setup>
import { ref, computed, nextTick } from "vue";
import { router } from "@inertiajs/vue3";
import { RotateCw, Pencil, PowerOff, Power, Plus } from "lucide-vue-next";
import Tooltip from "@/Components/Tooltip.vue";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import Pagination from "@/Components/Pagination.vue";

const props = defineProps({
    products: { type: Object, required: true },
});

const editingProduct = ref(null);
const isSubmitting = ref(false);
const isSubmittingRestock = ref(false);
const nameInputRef = ref(null);
const packageLabelInputRef = ref(null);
const form = ref(emptyForm());
const errors = ref({});

const restockingProduct = ref(null);
const restockForm = ref({
    purchase_quantity: 1,
    purchase_total_cost: "",
    note: "",
});
const restockErrors = ref({});

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

function emptyForm() {
    return {
        name: "",
        cost_price: "",
        sale_price: "",
        stock: "",
        purchase_unit_label: "",
        units_per_purchase_unit: "",
    };
}

function formatCurrency(value) {
    return new Intl.NumberFormat("es-CO", {
        style: "currency",
        currency: "COP",
        maximumFractionDigits: 0,
    }).format(value);
}

function openCreateForm() {
    editingProduct.value = null;
    form.value = emptyForm();
    errors.value = {};
}

function openEditForm(product) {
    editingProduct.value = product;
    form.value = {
        name: product.name,
        cost_price: product.cost_price,
        sale_price: product.sale_price,
        stock: product.stock,
        purchase_unit_label: product.purchase_unit_label ?? "",
        units_per_purchase_unit: product.units_per_purchase_unit ?? "",
    };
    errors.value = {};
}

function submit() {
    if (isSubmitting.value) return; // protección extra por si el evento se dispara dos veces

    isSubmitting.value = true;

    const options = {
        onError: (formErrors) => (errors.value = formErrors),
        onSuccess: () => openCreateForm(),
        onFinish: () => (isSubmitting.value = false), // se ejecuta SIEMPRE: éxito, error o fallo de red
    };

    if (editingProduct.value) {
        router.patch(
            route("products.update", editingProduct.value.id),
            form.value,
            options,
        );
    } else {
        router.post(route("products.store"), form.value, options);
    }
}

function toggleActive(product) {
    const routeName = product.is_active
        ? "products.destroy"
        : "products.restore";
    router.patch(route(routeName, product.id), {}, { preserveScroll: true });
}

function openRestockModal(product) {
    restockingProduct.value = product;
    restockForm.value = {
        purchase_quantity: 1,
        purchase_total_cost: "",
        note: "",
    };
    restockErrors.value = {};
}

const restockUnitsPreview = computed(() => {
    if (!restockingProduct.value?.units_per_purchase_unit) {
        return restockForm.value.purchase_quantity || 0;
    }
    return (
        (restockForm.value.purchase_quantity || 0) *
        restockingProduct.value.units_per_purchase_unit
    );
});

const restockUnitCostPreview = computed(() => {
    const units = restockUnitsPreview.value;
    const total = restockForm.value.purchase_total_cost;
    if (!units || !total) return 0;
    return total / units;
});

function submitRestock() {
    if (isSubmittingRestock.value) return;

    isSubmittingRestock.value = true;

    router.post(
        route("products.restock", restockingProduct.value.id),
        restockForm.value,
        {
            preserveScroll: true,
            onError: (formErrors) => (restockErrors.value = formErrors),
            onSuccess: () => (restockingProduct.value = null),
            onFinish: () => (isSubmittingRestock.value = false),
        },
    );
}
</script>

<template>
    <AuthenticatedLayout>
        <template #header>
            <h1 class="text-xl font-semibold text-ink">Productos</h1>
        </template>

        <div class="mx-auto max-w-5xl px-4 py-8 space-y-4">
            <!-- Formulario crear/editar -->
            <div class="rounded-lg border border-line bg-surface p-4 shadow-sm">
                <h2 class="mb-3 text-sm font-semibold text-ink">
                    {{
                        editingProduct
                            ? `Editando: ${editingProduct.name}`
                            : "Nuevo producto"
                    }}
                </h2>

                <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                    <div class="col-span-2 sm:col-span-1">
                        <label class="block text-xs font-medium text-ink/70"
                            >Nombre</label
                        >
                        <input
                            ref="nameInputRef"
                            :value="form.name"
                            @input="
                                handleCapitalizeInput(
                                    $event,
                                    nameInputRef,
                                    (v) => (form.name = v),
                                )
                            "
                            type="text"
                            class="mt-1 w-full rounded border-line text-sm focus:border-accent focus:ring-accent"
                        />
                        <p v-if="errors.name" class="mt-1 text-xs text-danger">
                            {{ errors.name }}
                        </p>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-ink/70"
                            >Costo unitario</label
                        >
                        <input
                            v-model.number="form.cost_price"
                            type="number"
                            min="0"
                            step="0.01"
                            class="mt-1 w-full rounded border-line text-sm focus:border-accent focus:ring-accent"
                        />
                        <p
                            v-if="errors.cost_price"
                            class="mt-1 text-xs text-danger"
                        >
                            {{ errors.cost_price }}
                        </p>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-ink/70"
                            >Precio venta</label
                        >
                        <input
                            v-model.number="form.sale_price"
                            type="number"
                            min="0"
                            step="0.01"
                            class="mt-1 w-full rounded border-line text-sm focus:border-accent focus:ring-accent"
                        />
                        <p
                            v-if="errors.sale_price"
                            class="mt-1 text-xs text-danger"
                        >
                            {{ errors.sale_price }}
                        </p>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-ink/70"
                            >Stock inicial</label
                        >
                        <input
                            v-model.number="form.stock"
                            type="number"
                            min="0"
                            class="mt-1 w-full rounded border-line text-sm focus:border-accent focus:ring-accent"
                            :disabled="!!editingProduct"
                        />
                        <p
                            v-if="editingProduct"
                            class="mt-1 text-xs text-ink/40"
                        >
                            Usa "Reabastecer" para sumar stock.
                        </p>
                    </div>
                </div>

                <!-- Configuración de compra empaquetada -->
                <div
                    class="mt-3 rounded border border-dashed border-line bg-paper/60 p-3"
                >
                    <p class="mb-2 text-xs font-medium text-ink/70">
                        ¿Este producto se compra empaquetado pero se vende
                        individual? (opcional — ej: empanadas por paquete de 10)
                    </p>
                    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                        <div>
                            <label class="block text-xs font-medium text-ink/70"
                                >Nombre del empaque</label
                            >
                            <input
                                ref="packageLabelInputRef"
                                :value="form.purchase_unit_label"
                                @input="
                                    handleCapitalizeInput(
                                        $event,
                                        packageLabelInputRef,
                                        (v) => (form.purchase_unit_label = v),
                                    )
                                "
                                type="text"
                                placeholder="ej: paquete, caja"
                                class="mt-1 w-full rounded border-line text-sm focus:border-accent focus:ring-accent"
                            />
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-ink/70"
                                >Unidades por empaque</label
                            >
                            <input
                                v-model.number="form.units_per_purchase_unit"
                                type="number"
                                min="2"
                                placeholder="ej: 10"
                                class="mt-1 w-full rounded border-line text-sm focus:border-accent focus:ring-accent"
                            />
                            <p
                                v-if="errors.units_per_purchase_unit"
                                class="mt-1 text-xs text-danger"
                            >
                                {{ errors.units_per_purchase_unit }}
                            </p>
                        </div>
                    </div>
                </div>

                <div class="mt-3 flex gap-2">
                    <button
                        class="flex items-center gap-1.5 rounded bg-accent px-4 py-2 text-sm font-medium text-white hover:bg-accent-dark disabled:cursor-not-allowed disabled:opacity-50"
                        :disabled="isSubmitting"
                        @click="submit"
                    >
                        <Plus class="h-4 w-4" />
                        {{
                            isSubmitting
                                ? "Guardando..."
                                : editingProduct
                                  ? "Guardar cambios"
                                  : "Crear producto"
                        }}
                    </button>
                    <button
                        v-if="editingProduct"
                        class="rounded px-4 py-2 text-sm text-ink/70"
                        @click="openCreateForm"
                    >
                        Cancelar
                    </button>
                </div>
            </div>

            <!-- Listado -->
            <div
                class="overflow-hidden rounded-lg border border-line bg-surface shadow-sm"
            >
                <table class="w-full text-sm">
                    <thead
                        class="border-b border-line bg-accent text-left text-white"
                    >
                        <tr>
                            <th class="px-4 py-2.5 font-medium">Producto</th>
                            <th class="px-4 py-2.5 font-medium">
                                Costo (prom.)
                            </th>
                            <th class="px-4 py-2.5 font-medium">Venta</th>
                            <th class="px-4 py-2.5 font-medium">Margen</th>
                            <th class="px-4 py-2.5 font-medium">Stock</th>
                            <th class="px-4 py-2.5 font-medium">Estado</th>
                            <th class="px-4 py-2.5"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="product in products.data"
                            :key="product.id"
                            class="border-b border-line/60 last:border-0"
                            :class="{ 'opacity-50': !product.is_active }"
                        >
                            <td class="px-4 py-2.5 text-ink">
                                {{ product.name }}
                                <span
                                    v-if="product.units_per_purchase_unit"
                                    class="ml-1 text-xs text-ink/40"
                                >
                                    ({{
                                        product.purchase_unit_label || "paquete"
                                    }}
                                    de {{ product.units_per_purchase_unit }})
                                </span>
                            </td>
                            <td class="px-4 py-2.5">
                                {{ formatCurrency(product.cost_price) }}
                            </td>
                            <td class="px-4 py-2.5">
                                {{ formatCurrency(product.sale_price) }}
                            </td>
                            <td class="px-4 py-2.5">
                                {{ formatCurrency(product.margin) }}
                            </td>
                            <td class="px-4 py-2.5 font-medium">
                                {{ product.stock }}
                            </td>
                            <td class="px-4 py-2.5">
                                <span
                                    class="rounded-full px-2 py-0.5 text-xs font-medium"
                                    :class="
                                        product.is_active
                                            ? 'bg-ok/10 text-ok'
                                            : 'bg-line text-ink/70'
                                    "
                                >
                                    {{
                                        product.is_active
                                            ? "Activo"
                                            : "Inactivo"
                                    }}
                                </span>
                            </td>
                            <td
                                class="px-4 py-2.5 text-right space-x-3 whitespace-nowrap"
                            >
                                <div
                                    class="flex items-center justify-end gap-1"
                                >
                                    <Tooltip text="Registrar compra">
                                        <button
                                            class="rounded p-1.5 text-accent-dark hover:bg-accent/10"
                                            @click="openRestockModal(product)"
                                        >
                                            <RotateCw class="h-4 w-4" />
                                        </button>
                                    </Tooltip>
                                    <Tooltip text="Editar">
                                        <button
                                            class="rounded p-1.5 text-ink/60 hover:bg-paper"
                                            @click="openEditForm(product)"
                                        >
                                            <Pencil class="h-4 w-4" />
                                        </button>
                                    </Tooltip>
                                    <Tooltip
                                        :text="
                                            product.is_active
                                                ? 'Desactivar'
                                                : 'Reactivar'
                                        "
                                    >
                                        <button
                                            class="rounded p-1.5 hover:bg-paper"
                                            :class="
                                                product.is_active
                                                    ? 'text-danger'
                                                    : 'text-ok'
                                            "
                                            @click="toggleActive(product)"
                                        >
                                            <component
                                                :is="
                                                    product.is_active
                                                        ? PowerOff
                                                        : Power
                                                "
                                                class="h-4 w-4"
                                            />
                                        </button>
                                    </Tooltip>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="products.data.length === 0">
                            <td
                                colspan="7"
                                class="px-4 py-6 text-center text-ink/40"
                            >
                                No hay productos aún.
                            </td>
                        </tr>
                    </tbody>
                </table>
                <div class="flex justify-center border-t border-line py-3">
                    <Pagination :links="products.links" />
                </div>
            </div>
        </div>

        <!-- Modal: reabastecer stock -->
        <div
            v-if="restockingProduct"
            class="fixed inset-0 flex items-center justify-center bg-ink/30"
            @click.self="restockingProduct = null"
        >
            <div class="w-full max-w-sm rounded-lg bg-surface p-5">
                <h2 class="mb-1 font-semibold text-ink">
                    Nueva compra: {{ restockingProduct.name }}
                </h2>
                <p class="mb-4 text-xs text-ink/70">
                    Stock actual: {{ restockingProduct.stock }} unidades
                </p>

                <div class="space-y-3">
                    <div>
                        <label class="block text-xs font-medium text-ink/70">
                            Cantidad comprada ({{
                                restockingProduct.purchase_unit_label ||
                                "unidades"
                            }})
                        </label>
                        <input
                            v-model.number="restockForm.purchase_quantity"
                            type="number"
                            min="1"
                            class="mt-1 w-full rounded border-line text-sm focus:border-accent focus:ring-accent"
                        />
                        <p
                            v-if="restockErrors.purchase_quantity"
                            class="mt-1 text-xs text-danger"
                        >
                            {{ restockErrors.purchase_quantity }}
                        </p>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-ink/70"
                            >Total pagado por esta compra</label
                        >
                        <input
                            v-model.number="restockForm.purchase_total_cost"
                            type="number"
                            min="0"
                            step="0.01"
                            class="mt-1 w-full rounded border-line text-sm focus:border-accent focus:ring-accent"
                        />
                        <p
                            v-if="restockErrors.purchase_total_cost"
                            class="mt-1 text-xs text-danger"
                        >
                            {{ restockErrors.purchase_total_cost }}
                        </p>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-ink/70"
                            >Nota (opcional)</label
                        >
                        <input
                            v-model="restockForm.note"
                            type="text"
                            placeholder="ej: proveedor, factura #123"
                            class="mt-1 w-full rounded border-line text-sm focus:border-accent focus:ring-accent"
                        />
                    </div>

                    <!-- Previsualización del cálculo antes de guardar -->
                    <div class="rounded bg-paper p-3 text-xs text-ink/70">
                        <p>
                            Unidades que se sumarán al stock:
                            <strong class="text-ink">{{
                                restockUnitsPreview
                            }}</strong>
                        </p>
                        <p>
                            Costo por unidad de esta compra:
                            <strong class="text-ink">{{
                                formatCurrency(restockUnitCostPreview)
                            }}</strong>
                        </p>
                    </div>
                </div>

                <div class="mt-4 flex justify-end gap-2">
                    <button
                        class="rounded px-3 py-2 text-sm text-ink/70"
                        @click="restockingProduct = null"
                    >
                        Cancelar
                    </button>
                    <button
                        class="rounded bg-accent px-3 py-2 text-sm font-medium text-white hover:bg-accent-dark disabled:cursor-not-allowed disabled:opacity-50"
                        :disabled="isSubmittingRestock"
                        @click="submitRestock"
                    >
                        {{ isSubmittingRestock ? "Guardando..." : "Guardar" }}
                    </button>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
