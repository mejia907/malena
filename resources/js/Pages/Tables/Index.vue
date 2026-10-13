<script setup>
import { onMounted, onBeforeUnmount } from "vue";
import { Link, router } from "@inertiajs/vue3";
import { Users, CircleDot } from "lucide-vue-next";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";

const props = defineProps({
    tables: {
        type: Array,
        required: true,
    },
});

let pollTimer = null;

onMounted(() => {
    pollTimer = setInterval(() => {
        router.reload({
            only: ["tables"],
            preserveScroll: true,
            preserveState: true,
        });
    }, 4000);
});

onBeforeUnmount(() => {
    clearInterval(pollTimer);
});

const formatCurrency = (value) =>
    new Intl.NumberFormat("es-CO", {
        style: "currency",
        currency: "COP",
        maximumFractionDigits: 0,
    }).format(value);
</script>

<template>
    <AuthenticatedLayout>
        <template #header>
            <h1 class="text-xl font-semibold text-ink">Mesas</h1>
        </template>

        <div class="mx-auto max-w-5xl px-4 py-6 sm:py-8">
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
                <Link
                    v-for="table in tables"
                    :key="table.id"
                    :href="route('tables.show', table.id)"
                    class="flex min-h-[9.5rem] flex-col justify-between rounded-xl border p-3.5 transition hover:shadow-md active:scale-[0.98] sm:min-h-[8.5rem] sm:p-4"
                    :class="
                        table.status === 'occupied'
                            ? 'border-amber-300 bg-amber-50'
                            : 'border-emerald-300 bg-emerald-50'
                    "
                >
                    <!-- Arriba: nombre + estado (apilados en celular, en fila desde sm) -->
                    <div>
                        <div
                            class="flex flex-col items-start gap-1.5 sm:flex-row sm:justify-between"
                        >
                            <span
                                class="whitespace-nowrap text-base font-semibold text-ink sm:text-lg"
                            >
                                {{ table.name }}
                            </span>

                            <span
                                class="inline-flex shrink-0 items-center gap-1 rounded-full px-2 py-0.5 text-[11px] font-medium sm:text-xs"
                                :class="
                                    table.status === 'occupied'
                                        ? 'bg-amber-200/70 text-amber-800'
                                        : 'bg-emerald-200/70 text-emerald-800'
                                "
                            >
                                <CircleDot class="h-3 w-3" />
                                {{
                                    table.status === "occupied"
                                        ? "Ocupada"
                                        : "Libre"
                                }}
                            </span>
                        </div>

                        <p
                            v-if="table.customer_name"
                            class="mt-1.5 truncate text-sm font-medium text-accent-dark"
                        >
                            {{ table.customer_name }}
                        </p>
                    </div>

                    <!-- Abajo: capacidad + total, siempre pegados al borde inferior -->
                    <div>
                        <p class="flex items-center gap-1 text-xs text-ink/70">
                            <Users class="h-3.5 w-3.5" /> Capacidad
                            {{ table.capacity }}
                        </p>
                        <p
                            v-if="table.status === 'occupied'"
                            class="mt-1 text-lg font-semibold leading-tight text-ink"
                        >
                            {{ formatCurrency(table.total) }}
                        </p>
                    </div>
                </Link>
            </div>

            <div
                v-if="tables.length === 0"
                class="rounded-lg border border-dashed border-line py-16 text-center text-sm text-ink/50"
            >
                No hay mesas configuradas todavía.
            </div>
        </div>
    </AuthenticatedLayout>
</template>
