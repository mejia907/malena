<script setup>
import { Link } from "@inertiajs/vue3";
</script>

<template>
    <div
        class="flex min-h-screen flex-col items-center bg-paper pt-6 font-sans sm:justify-center sm:pt-0"
    >
        <div class="mb-4">
            <Link href="/">
                <img
                    src="/images/login.png"
                    alt="Malena"
                    class="h-32 w-32 rounded-full ring-2 ring-line"
                />
            </Link>
        </div>

        <div
            class="mt-2 w-full overflow-hidden rounded-lg border border-line bg-surface px-6 py-6 shadow-sm sm:max-w-md"
        >
            <slot />
        </div>
    </div>
</template>
