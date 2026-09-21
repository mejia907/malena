<script setup>
defineProps({
    value: { type: String, default: "" },
});
</script>

<template>
    <label class="block text-sm font-medium text-ink/70">
        <slot v-if="!value">{{ value }}</slot>
        <template v-else>{{ value }}</template>
    </label>
</template>
