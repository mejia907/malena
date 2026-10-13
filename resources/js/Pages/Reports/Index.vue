<script setup>
import { ref } from "vue";
import { router } from "@inertiajs/vue3";
import {
    Search,
    Star,
    Banknote,
    Wallet,
    ArrowLeftRight,
    Package2,
    AlertTriangle,
    TrendingUp,
    Receipt,
} from "lucide-vue-next";
import Tooltip from "@/Components/Tooltip.vue";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import Pagination from "@/Components/Pagination.vue";

const props = defineProps({
    summary: { type: Object, required: true },
    products: { type: Object, required: true },
    filters: { type: Object, required: true },
});

const from = ref(props.filters.from);
const to = ref(props.filters.to);

function formatCurrency(value) {
    return new Intl.NumberFormat("es-CO", {
        style: "currency",
        currency: "COP",
        maximumFractionDigits: 0,
    }).format(value);
}

function applyFilter() {
    router.get(
        route("reports.index"),
        { from: from.value, to: to.value },
        { preserveState: true },
    );
}

// Producto más vendido — para resaltarlo en la tabla, útil de un vistazo para decidir compras
const topProductId = props.products.data[0]?.id ?? null;
</script>

<template>
    <AuthenticatedLayout>
        <template #header>
            <h1 class="text-xl font-semibold text-ink">Reportes</h1>
        </template>

        <div class="mx-auto max-w-4xl px-4 py-8 space-y-4">
            <!-- Filtro -->
            <div
                class="flex flex-wrap items-end gap-3 rounded-lg border border-line bg-surface p-4 shadow-sm"
            >
                <div>
                    <label class="block text-xs font-medium text-ink/70"
                        >Desde</label
                    >
                    <input
                        v-model="from"
                        type="date"
                        class="mt-1 rounded border-line text-sm focus:border-accent focus:ring-accent"
                    />
                </div>
                <div>
                    <label class="block text-xs font-medium text-ink/70"
                        >Hasta</label
                    >
                    <input
                        v-model="to"
                        type="date"
                        class="mt-1 rounded border-line text-sm focus:border-accent focus:ring-accent"
                    />
                </div>
                <Tooltip text="Consultar">
                    <button
                        class="rounded bg-accent p-2.5 text-white hover:bg-accent-dark"
                        @click="applyFilter"
                    >
                        <Search class="h-4 w-4" />
                    </button>
                </Tooltip>
            </div>

            <!-- Métricas del rango -->
            <div
                class="rounded-lg border-l-4 border-l-accent border-y border-r border-line bg-surface p-5 shadow-sm"
            >
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                    <div class="rounded border border-line bg-paper/60 p-3">
                        <p class="flex items-center gap-1 text-xs text-ink/70">
                            <Banknote class="h-3.5 w-3.5" /> Ventas totales
                        </p>
                        <p class="mt-0.5 text-lg font-semibold text-ink">
                            {{ formatCurrency(summary.total_sales) }}
                        </p>
                    </div>
                    <div class="rounded border border-line bg-paper/60 p-3">
                        <p class="flex items-center gap-1 text-xs text-ink/70">
                            <Wallet class="h-3.5 w-3.5" /> Efectivo
                        </p>
                        <p class="mt-0.5 text-lg font-semibold text-ink">
                            {{ formatCurrency(summary.total_cash) }}
                        </p>
                    </div>
                    <div class="rounded border border-line bg-paper/60 p-3">
                        <p class="flex items-center gap-1 text-xs text-ink/70">
                            <ArrowLeftRight class="h-3.5 w-3.5" />
                            Transferencia
                        </p>
                        <p class="mt-0.5 text-lg font-semibold text-ink">
                            {{ formatCurrency(summary.total_transfer) }}
                        </p>
                    </div>
                    <div class="rounded border border-line bg-paper/60 p-3">
                        <p class="flex items-center gap-1 text-xs text-ink/70">
                            <Package2 class="h-3.5 w-3.5" /> Costo total
                        </p>
                        <p class="mt-0.5 text-lg font-semibold text-ink">
                            {{ formatCurrency(summary.total_cost) }}
                        </p>
                    </div>
                    <div
                        class="rounded border border-danger/30 bg-danger/5 p-3"
                    >
                        <p class="flex items-center gap-1 text-xs text-danger">
                            <AlertTriangle class="h-3.5 w-3.5" /> Pérdida por
                            merma
                        </p>
                        <p class="mt-0.5 text-lg font-semibold text-danger">
                            {{ formatCurrency(summary.total_waste_cost) }}
                        </p>
                    </div>
                    <div class="rounded border border-ok/30 bg-ok/5 p-3">
                        <p class="flex items-center gap-1 text-xs text-ok">
                            <TrendingUp class="h-3.5 w-3.5" /> Ganancia neta
                            (con merma)
                        </p>
                        <p class="mt-0.5 text-lg font-semibold text-ok">
                            {{ formatCurrency(summary.net_profit_after_waste) }}
                        </p>
                    </div>
                    <div class="rounded border border-line bg-paper/60 p-3">
                        <p class="flex items-center gap-1 text-xs text-ink/70">
                            <Receipt class="h-3.5 w-3.5" /> Pedidos cobrados
                        </p>
                        <p class="mt-0.5 text-lg font-semibold text-ink">
                            {{ summary.orders_count }}
                        </p>
                    </div>
                </div>
            </div>

            <!-- Productos vendidos en el rango -->
            <div
                class="overflow-hidden rounded-lg border border-line bg-surface shadow-sm"
            >
                <h2
                    class="border-b border-line bg-accent px-4 py-2.5 text-sm font-semibold text-white"
                >
                    Productos vendidos en el periodo
                </h2>
                <!-- TABLA: pantallas medianas y grandes -->
                <table class="hidden w-full text-sm md:table">
                    <thead class="border-b border-line text-left text-ink/70">
                        <tr>
                            <th class="px-4 py-2 font-medium">Producto</th>
                            <th class="px-4 py-2 font-medium">
                                Cantidad vendida
                            </th>
                            <th class="px-4 py-2 font-medium">Total vendido</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="product in products.data"
                            :key="product.id"
                            class="border-b border-line/60 last:border-0"
                            :class="{
                                'bg-accent/5': product.id === topProductId,
                            }"
                        >
                            <td class="px-4 py-2 text-ink">
                                {{ product.name }}
                                <span
                                    v-if="product.id === topProductId"
                                    class="ml-1 inline-flex items-center gap-0.5 text-xs font-medium text-accent-dark"
                                >
                                    <Star class="h-3 w-3 fill-accent-dark" />
                                    más vendido
                                </span>
                            </td>
                            <td class="px-4 py-2 font-medium text-ink">
                                {{ product.quantity_sold }}
                            </td>
                            <td class="px-4 py-2 text-ink">
                                {{ formatCurrency(product.total_sold) }}
                            </td>
                        </tr>
                        <tr v-if="products.data.length === 0">
                            <td
                                colspan="3"
                                class="px-4 py-6 text-center text-ink/70"
                            >
                                No hay ventas en este periodo.
                            </td>
                        </tr>
                    </tbody>
                </table>

                <!-- TARJETAS: solo en celular -->
                <div class="divide-y divide-line md:hidden">
                    <div
                        v-for="product in products.data"
                        :key="product.id"
                        class="flex items-center justify-between gap-3 px-4 py-3"
                        :class="{
                            'bg-accent/5': product.id === topProductId,
                        }"
                    >
                        <div class="min-w-0">
                            <p class="truncate text-sm font-medium text-ink">
                                {{ product.name }}
                                <span
                                    v-if="product.id === topProductId"
                                    class="ml-1 inline-flex items-center gap-0.5 text-xs font-medium text-accent-dark"
                                >
                                    <Star class="h-3 w-3 fill-accent-dark" />
                                    más vendido
                                </span>
                            </p>
                            <p class="text-xs text-ink/60">
                                {{ product.quantity_sold }} unidades
                            </p>
                        </div>
                        <p
                            class="shrink-0 text-sm font-semibold text-ink"
                        >
                            {{ formatCurrency(product.total_sold) }}
                        </p>
                    </div>
                    <p
                        v-if="products.data.length === 0"
                        class="px-4 py-6 text-center text-sm text-ink/70"
                    >
                        No hay ventas en este periodo.
                    </p>
                </div>
                <div class="flex justify-center border-t border-line py-3">
                    <Pagination :links="products.links" />
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
