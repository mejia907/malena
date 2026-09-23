<script setup>
import { ref } from "vue";
import { router } from "@inertiajs/vue3";
import {
    Lock,
    Wallet,
    ArrowLeftRight,
    Package2,
    TrendingUp,
    Receipt,
} from "lucide-vue-next";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import Pagination from "@/Components/Pagination.vue";

const props = defineProps({
    summary: { type: Object, required: true },
    history: { type: Object, required: true },
    soldProducts: { type: Array, required: true },
    wastes: { type: Array, required: true },
});

const showConfirmModal = ref(false);
const showSoldModal = ref(false);
const showWasteModal = ref(false);

const wasteReasonLabels = {
    sin_vender: "No se vendió",
    dañado: "Dañado o vencido",
    consumo_interno: "Consumo interno",
    otro: "Otro",
};

function formatCurrency(value) {
    return new Intl.NumberFormat("es-CO", {
        style: "currency",
        currency: "COP",
        maximumFractionDigits: 0,
    }).format(value);
}

function formatDateTime(value) {
    return new Date(value).toLocaleString("es-CO", {
        dateStyle: "short",
        timeStyle: "short",
    });
}

function confirmClosure() {
    router.post(
        route("cash-closures.store"),
        {},
        { onSuccess: () => (showConfirmModal.value = false) },
    );
}
</script>

<template>
    <AuthenticatedLayout>
        <template #header>
            <h1 class="text-xl font-semibold text-ink">Cierre de caja</h1>
        </template>

        <div class="mx-auto max-w-4xl px-4 py-8 space-y-4">
            <!-- Resumen: mismo patrón visual que el total de mesa (borde izquierdo dorado) -->
            <div
                class="rounded-lg border-l-4 border-l-accent border-y border-r border-line bg-surface p-5 shadow-sm"
            >
                <p class="mb-4 text-xs text-ink/70">
                    Periodo pendiente:
                    {{ formatDateTime(summary.period_start) }} → ahora
                </p>

                <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                    <button
                        class="rounded border border-line bg-paper/60 p-3 text-left transition hover:border-accent hover:bg-accent/5"
                        @click="showSoldModal = true"
                    >
                        <p class="text-xs text-ink/50">Ventas totales</p>
                        <p class="mt-0.5 text-lg font-semibold text-ink">
                            {{ formatCurrency(summary.total_sales) }}
                        </p>
                    </button>
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
                            <ArrowLeftRight class="h-3.5 w-3.5" /> Transferencia
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
                    <div class="rounded border border-ok/30 bg-ok/5 p-3">
                        <p class="flex items-center gap-1 text-xs text-ok">
                            <TrendingUp class="h-3.5 w-3.5" /> Ganancia neta
                        </p>
                        <p class="mt-0.5 text-lg font-semibold text-ok">
                            {{ formatCurrency(summary.total_profit) }}
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
                    <button
                        class="rounded border border-danger/30 bg-danger/5 p-3 text-left transition hover:border-danger"
                        @click="showWasteModal = true"
                    >
                        <p class="text-xs text-danger">Pérdida por merma</p>
                        <p class="mt-0.5 text-lg font-semibold text-danger">
                            {{ formatCurrency(summary.total_waste_cost) }}
                        </p>
                    </button>
                    <div class="rounded border border-ok/30 bg-ok/5 p-3">
                        <p class="text-xs text-ok">Ganancia neta (con merma)</p>
                        <p class="mt-0.5 text-lg font-semibold text-ok">
                            {{ formatCurrency(summary.net_profit_after_waste) }}
                        </p>
                    </div>
                </div>

                <button
                    class="mt-4 flex items-center gap-1.5 rounded bg-ink px-4 py-2.5 text-sm font-semibold text-white hover:bg-ink/90 disabled:opacity-30"
                    :disabled="summary.orders_count === 0"
                    @click="showConfirmModal = true"
                >
                    <Lock class="h-4 w-4" /> Cerrar caja
                </button>
                <p
                    v-if="summary.orders_count === 0"
                    class="mt-2 text-xs text-ink/40"
                >
                    No hay ventas nuevas desde el último cierre.
                </p>
            </div>

            <!-- Historial -->
            <div
                class="overflow-hidden rounded-lg border border-line bg-surface shadow-sm"
            >
                <h2
                    class="border-b border-line bg-accent px-4 py-2.5 text-sm font-semibold text-white"
                >
                    Últimos cierres
                </h2>
                <table class="w-full text-sm">
                    <thead class="border-b border-line text-left text-ink/50">
                        <tr>
                            <th class="px-4 py-2 font-medium">Periodo</th>
                            <th class="px-4 py-2 font-medium">Ventas</th>
                            <th class="px-4 py-2 font-medium">Efectivo</th>
                            <th class="px-4 py-2 font-medium">Transf.</th>
                            <th class="px-4 py-2 font-medium">Merma</th>
                            <th class="px-4 py-2 font-medium">Ganancia neta</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="closure in history.data"
                            :key="closure.id"
                            class="border-b border-line/60 last:border-0"
                        >
                            <td class="px-4 py-2 text-ink/70">
                                {{ formatDateTime(closure.period_start) }} —
                                {{ formatDateTime(closure.period_end) }}
                            </td>
                            <td class="px-4 py-2 font-medium text-ink">
                                {{ formatCurrency(closure.total_sales) }}
                            </td>
                            <td class="px-4 py-2">
                                {{ formatCurrency(closure.total_cash) }}
                            </td>
                            <td class="px-4 py-2">
                                {{ formatCurrency(closure.total_transfer) }}
                            </td>
                            <td class="px-4 py-2 text-danger">
                                {{ formatCurrency(closure.total_waste_cost) }}
                            </td>
                            <td class="px-4 py-2 text-ok">
                                {{
                                    formatCurrency(
                                        closure.net_profit_after_waste,
                                    )
                                }}
                            </td>
                        </tr>
                        <tr v-if="history.data.length === 0">
                            <td
                                colspan="6"
                                class="px-4 py-6 text-center text-ink/40"
                            >
                                Aún no se ha hecho ningún cierre.
                            </td>
                        </tr>
                    </tbody>
                </table>
                <div class="flex justify-center border-t border-line py-3">
                    <Pagination :links="history.links" />
                </div>
            </div>
        </div>

        <!-- Modal confirmación -->
        <div
            v-if="showConfirmModal"
            class="fixed inset-0 flex items-center justify-center bg-ink/30"
            @click.self="showConfirmModal = false"
        >
            <div class="w-full max-w-sm rounded-lg bg-surface p-5">
                <h2 class="mb-2 font-semibold text-ink">¿Cerrar caja ahora?</h2>
                <p class="mb-4 text-sm text-ink/70">
                    Se guardará el corte con ventas por
                    {{ formatCurrency(summary.total_sales) }}. Esta acción no se
                    puede deshacer.
                </p>
                <div class="flex justify-end gap-2">
                    <button
                        class="rounded px-3 py-2 text-sm text-ink/70"
                        @click="showConfirmModal = false"
                    >
                        Volver
                    </button>
                    <button
                        class="rounded bg-accent px-3 py-2 text-sm font-medium text-white hover:bg-accent-dark"
                        @click="confirmClosure"
                    >
                        Sí, cerrar caja
                    </button>
                </div>
            </div>
        </div>
        <!-- Modal: productos vendidos en el periodo -->
        <div
            v-if="showSoldModal"
            class="fixed inset-0 flex items-center justify-center bg-ink/30 p-4"
            @click.self="showSoldModal = false"
        >
            <div
                class="max-h-[80vh] w-full max-w-lg overflow-hidden rounded-lg bg-surface"
            >
                <div
                    class="flex items-center justify-between border-b border-line px-5 py-3"
                >
                    <h2 class="font-semibold text-ink">Productos vendidos</h2>
                    <button
                        class="text-ink/40 hover:text-ink"
                        @click="showSoldModal = false"
                    >
                        ✕
                    </button>
                </div>
                <div class="max-h-[70vh] overflow-y-auto">
                    <table class="w-full text-sm">
                        <thead
                            class="sticky top-0 border-b border-line bg-accent text-left text-white"
                        >
                            <tr>
                                <th class="px-4 py-2 font-medium">Producto</th>
                                <th class="px-4 py-2 font-medium">Cantidad</th>
                                <th class="px-4 py-2 font-medium">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="product in soldProducts"
                                :key="product.id"
                                class="border-b border-line/60 last:border-0"
                            >
                                <td class="px-4 py-2 text-ink">
                                    {{ product.name }}
                                </td>
                                <td class="px-4 py-2 font-medium">
                                    {{ product.quantity_sold }}
                                </td>
                                <td class="px-4 py-2">
                                    {{ formatCurrency(product.total_sold) }}
                                </td>
                            </tr>
                            <tr v-if="soldProducts.length === 0">
                                <td
                                    colspan="3"
                                    class="px-4 py-6 text-center text-ink/40"
                                >
                                    No hay ventas en este periodo.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Modal: productos dados de baja (merma) en el periodo -->
        <div
            v-if="showWasteModal"
            class="fixed inset-0 flex items-center justify-center bg-ink/30 p-4"
            @click.self="showWasteModal = false"
        >
            <div
                class="max-h-[80vh] w-full max-w-lg overflow-hidden rounded-lg bg-surface"
            >
                <div
                    class="flex items-center justify-between border-b border-line px-5 py-3"
                >
                    <h2 class="font-semibold text-ink">
                        Productos dados de baja - Pérdida por merma
                    </h2>
                    <button
                        class="text-ink/40 hover:text-ink"
                        @click="showWasteModal = false"
                    >
                        ✕
                    </button>
                </div>
                <div class="max-h-[60vh] overflow-y-auto">
                    <table class="w-full text-sm">
                        <thead
                            class="sticky top-0 border-b border-line bg-accent text-left text-white"
                        >
                            <tr>
                                <th class="px-4 py-2 font-medium">Producto</th>
                                <th class="px-4 py-2 font-medium">Cant.</th>
                                <th class="px-4 py-2 font-medium">Motivo</th>
                                <th class="px-4 py-2 font-medium">Pérdida</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="waste in wastes"
                                :key="waste.id"
                                class="border-b border-line/60 last:border-0"
                            >
                                <td class="px-4 py-2 text-ink">
                                    {{ waste.product }}
                                    <p
                                        v-if="waste.note"
                                        class="text-xs text-ink/40"
                                    >
                                        {{ waste.note }}
                                    </p>
                                </td>
                                <td class="px-4 py-2 font-medium">
                                    {{ waste.quantity }}
                                </td>
                                <td class="px-4 py-2 text-ink/60">
                                    {{
                                        wasteReasonLabels[waste.reason] ||
                                        waste.reason
                                    }}
                                </td>
                                <td class="px-4 py-2 text-danger">
                                    {{ formatCurrency(waste.total_cost) }}
                                </td>
                            </tr>
                            <tr v-if="wastes.length === 0">
                                <td
                                    colspan="4"
                                    class="px-4 py-6 text-center text-ink/40"
                                >
                                    No hay mermas registradas en este periodo.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
