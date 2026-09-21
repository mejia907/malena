<script setup>
import { ref } from "vue";
import {
    UtensilsCrossed,
    Package,
    Calculator,
    BarChart3,
    ChevronDown,
} from "lucide-vue-next";
import Dropdown from "@/Components/Dropdown.vue";
import DropdownLink from "@/Components/DropdownLink.vue";
import ResponsiveNavLink from "@/Components/ResponsiveNavLink.vue";
import { Link } from "@inertiajs/vue3";

const showingNavigationDropdown = ref(false);

const navItems = [
    {
        label: "Mesas",
        route: "tables.index",
        active: "tables.*",
        icon: UtensilsCrossed,
    },
    {
        label: "Productos",
        route: "products.index",
        active: "products.*",
        icon: Package,
    },
    {
        label: "Cierre de caja",
        route: "cash-closures.pending",
        active: "cash-closures.*",
        icon: Calculator,
    },
    {
        label: "Reportes",
        route: "reports.index",
        active: "reports.*",
        icon: BarChart3,
    },
];
</script>

<template>
    <div class="min-h-screen bg-paper font-sans text-ink">
        <nav class="border-b border-line bg-surface">
            <div class="mx-auto max-w-6xl px-4">
                <div class="flex h-16 items-center justify-between">
                    <!-- Marca -->
                    <Link
                        :href="route('tables.index')"
                        class="flex shrink-0 items-center gap-2.5"
                    >
                        <img
                            src="/images/logo.png"
                            alt="Malena"
                            class="h-9 w-9 rounded-full ring-1 ring-line"
                        />
                        <span
                            class="text-base font-semibold tracking-tight text-ink"
                            >Malena</span
                        >
                    </Link>

                    <!-- Navegación desktop -->
                    <div class="hidden items-center gap-1 sm:flex">
                        <Link
                            v-for="item in navItems"
                            :key="item.route"
                            :href="route(item.route)"
                            class="flex items-center gap-1.5 rounded-md px-3 py-2 text-sm font-medium transition"
                            :class="
                                route().current(item.active)
                                    ? 'bg-accent/10 text-accent-dark'
                                    : 'text-ink/60 hover:bg-paper hover:text-ink'
                            "
                        >
                            <component :is="item.icon" class="h-4 w-4" />
                            {{ item.label }}
                        </Link>
                    </div>

                    <!-- Usuario (desktop) -->
                    <div class="hidden sm:flex sm:items-center">
                        <Dropdown align="right" width="48">
                            <template #trigger>
                                <button
                                    type="button"
                                    class="flex items-center gap-2 rounded-md px-2 py-1.5 text-sm text-ink/70 transition hover:bg-paper"
                                >
                                    <span
                                        class="flex h-7 w-7 items-center justify-center rounded-full bg-ink text-xs font-semibold text-white"
                                    >
                                        {{
                                            $page.props.auth.user.name
                                                .charAt(0)
                                                .toUpperCase()
                                        }}
                                    </span>
                                    {{ $page.props.auth.user.name }}
                                    <ChevronDown
                                        class="h-3.5 w-3.5 text-ink/40"
                                    />
                                </button>
                            </template>

                            <template #content>
                                <DropdownLink :href="route('profile.edit')"
                                    >Perfil</DropdownLink
                                >
                                <DropdownLink
                                    :href="route('logout')"
                                    method="post"
                                    as="button"
                                >
                                    Cerrar sesión
                                </DropdownLink>
                            </template>
                        </Dropdown>
                    </div>

                    <!-- Hamburguesa (móvil) -->
                    <div class="-me-2 flex items-center sm:hidden">
                        <button
                            @click="
                                showingNavigationDropdown =
                                    !showingNavigationDropdown
                            "
                            class="inline-flex items-center justify-center rounded-md p-2 text-ink/50 transition hover:bg-paper hover:text-ink"
                        >
                            <svg
                                class="h-6 w-6"
                                stroke="currentColor"
                                fill="none"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    :class="{
                                        hidden: showingNavigationDropdown,
                                        'inline-flex':
                                            !showingNavigationDropdown,
                                    }"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M4 6h16M4 12h16M4 18h16"
                                />
                                <path
                                    :class="{
                                        hidden: !showingNavigationDropdown,
                                        'inline-flex':
                                            showingNavigationDropdown,
                                    }"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M6 18L18 6M6 6l12 12"
                                />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Menú responsive -->
            <div
                :class="{
                    block: showingNavigationDropdown,
                    hidden: !showingNavigationDropdown,
                }"
                class="border-t border-line sm:hidden"
            >
                <div class="space-y-1 px-2 py-3">
                    <ResponsiveNavLink
                        v-for="item in navItems"
                        :key="item.route"
                        :href="route(item.route)"
                        :active="route().current(item.active)"
                        class="flex items-center gap-2"
                    >
                        <component :is="item.icon" class="h-4 w-4" />
                        {{ item.label }}
                    </ResponsiveNavLink>
                </div>

                <div class="border-t border-line pb-1 pt-4">
                    <div class="px-4">
                        <div class="text-base font-medium text-ink">
                            {{ $page.props.auth.user.name }}
                        </div>
                        <div class="text-sm text-ink/50">
                            {{ $page.props.auth.user.email }}
                        </div>
                    </div>

                    <div class="mt-3 space-y-1">
                        <ResponsiveNavLink :href="route('profile.edit')"
                            >Perfil</ResponsiveNavLink
                        >
                        <ResponsiveNavLink
                            :href="route('logout')"
                            method="post"
                            as="button"
                        >
                            Cerrar sesión
                        </ResponsiveNavLink>
                    </div>
                </div>
            </div>
        </nav>

        <!-- Encabezado de página -->
        <header class="border-b border-line bg-surface" v-if="$slots.header">
            <div class="mx-auto max-w-6xl px-4 py-5">
                <slot name="header" />
            </div>
        </header>

        <!-- Contenido -->
        <main>
            <slot />
        </main>
    </div>
</template>
