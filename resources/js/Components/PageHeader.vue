<script setup>
import { Link } from "@inertiajs/vue3";
import { ArrowLeft } from "lucide-vue-next";
import Tooltip from "./Tooltip.vue";

defineProps({
    title: { type: String, required: true },
    backHref: { type: String, default: null },
    backLabel: { type: String, default: "Regresar" },
});
</script>

<template>
    <div class="flex items-center justify-between gap-4">
        <Tooltip v-if="backHref" :text="backLabel">
            <div class="flex items-center gap-3">
                <Link
                    v-if="backHref"
                    :href="backHref"
                    class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full border border-line text-ink/50 transition hover:border-accent hover:text-accent-dark"
                >
                    <ArrowLeft class="h-4 w-4" />
                </Link>
                <h1 class="text-xl font-semibold text-ink">{{ title }}</h1>
            </div>
        </Tooltip>

        <div class="shrink-0">
            <slot name="right" />
        </div>
    </div>
</template>
