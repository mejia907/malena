<script setup>
import { Link } from "@inertiajs/vue3";

defineProps({
    links: { type: Array, required: true },
});
</script>

<template>
    <div v-if="links.length > 3" class="flex flex-wrap items-center gap-1">
        <Link
            v-for="(link, index) in links"
            :key="index"
            :href="link.url ?? '#'"
            v-html="link.label"
            class="rounded px-3 py-1.5 text-sm transition"
            :class="[
                link.active
                    ? 'bg-accent text-white'
                    : 'text-ink/70 hover:bg-paper',
                !link.url ? 'cursor-not-allowed opacity-30' : '',
            ]"
            :preserve-scroll="true"
        />
    </div>
</template>
