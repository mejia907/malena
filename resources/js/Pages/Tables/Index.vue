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

        <div class="mx-auto max-w-5xl px-4 py-8">
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
                <Link
                    v-for="table in tables"
                    :key="table.id"
                    :href="route('tables.show', table.id)"
                    class="flex flex-col justify-between rounded-lg border p-4 transition hover:shadow-md"
                    :class="
                        table.status === 'occupied'
                            ? 'border-amber-300 bg-amber-50'
                            : 'border-emerald-300 bg-emerald-50'
                    "
                >
                    <div class="flex items-start justify-between">
                        <span class="text-lg font-semibold text-ink">{{
                            table.name
                        }}</span>
                        <span
                            class="flex items-center gap-1.5 text-xs font-medium text-ink/70"
                        >
                            <CircleDot
                                class="h-3.5 w-3.5"
                                :class="
                                    table.status === 'occupied'
                                        ? 'text-amber-600'
                                        : 'text-emerald-600'
                                "
                            />
                            {{
                                table.status === "occupied"
                                    ? "Ocupada"
                                    : "Libre"
                            }}
                        </span>
                    </div>

                    <div class="mt-4">
                        <p class="flex items-center gap-1 text-xs text-ink/70">
                            <Users class="h-3.5 w-3.5" /> Capacidad
                            {{ table.capacity }}
                        </p>
                        <p
                            v-if="table.status === 'occupied'"
                            class="mt-1 text-lg font-semibold text-ink"
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
