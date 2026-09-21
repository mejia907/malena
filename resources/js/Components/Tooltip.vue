<script setup>
defineProps({
    text: { type: String, required: true },
});
</script>

<template>
    <span class="group relative inline-flex">
        <slot />
        <span
            class="pointer-events-none absolute bottom-full left-1/2 z-50 mb-2 -translate-x-1/2 whitespace-nowrap rounded bg-ink px-2 py-1 text-xs font-medium text-white opacity-0 shadow-lg transition-opacity duration-150 group-hover:opacity-100"
        >
            {{ text }}
            <span
                class="absolute left-1/2 -bottom-0.5 h-1.5 w-1.5 -translate-x-1/2 rotate-45 bg-ink"
            ></span>
        </span>
    </span>
</template>
