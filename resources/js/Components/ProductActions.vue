<script setup>
import { RotateCw, Pencil, PowerOff, Power, PackageX } from "lucide-vue-next";
import Tooltip from "@/Components/Tooltip.vue";

defineProps({
    product: { type: Object, required: true },
    // true = botones con texto (móvil); false = íconos con tooltip (escritorio)
    showLabels: { type: Boolean, default: false },
});

defineEmits(["restock", "waste", "edit", "toggle"]);
</script>

<template>
    <!-- Versión móvil: botones grandes con texto, fáciles de tocar -->
    <div v-if="showLabels" class="grid grid-cols-2 gap-2">
        <button
            class="flex items-center justify-center gap-1.5 rounded-md border border-line px-3 py-2.5 text-xs font-medium text-accent-dark active:bg-accent/10"
            @click="$emit('restock', product)"
        >
            <RotateCw class="h-4 w-4" /> Registrar compra
        </button>
        <button
            class="flex items-center justify-center gap-1.5 rounded-md border border-line px-3 py-2.5 text-xs font-medium text-danger active:bg-danger/10"
            @click="$emit('waste', product)"
        >
            <PackageX class="h-4 w-4" /> Registrar merma
        </button>
        <button
            class="flex items-center justify-center gap-1.5 rounded-md border border-line px-3 py-2.5 text-xs font-medium text-ink/70 active:bg-paper"
            @click="$emit('edit', product)"
        >
            <Pencil class="h-4 w-4" /> Editar
        </button>
        <button
            class="flex items-center justify-center gap-1.5 rounded-md border border-line px-3 py-2.5 text-xs font-medium active:bg-paper"
            :class="product.is_active ? 'text-danger' : 'text-ok'"
            @click="$emit('toggle', product)"
        >
            <component
                :is="product.is_active ? PowerOff : Power"
                class="h-4 w-4"
            />
            {{ product.is_active ? "Desactivar" : "Reactivar" }}
        </button>
    </div>

    <!-- Versión escritorio: íconos compactos con tooltip -->
    <div v-else class="flex items-center justify-end gap-1">
        <Tooltip text="Registrar compra">
            <button
                class="rounded p-1.5 text-accent-dark hover:bg-accent/10"
                @click="$emit('restock', product)"
            >
                <RotateCw class="h-4 w-4" />
            </button>
        </Tooltip>
        <Tooltip text="Registrar merma/pérdida">
            <button
                class="rounded p-1.5 text-danger hover:bg-danger/10"
                @click="$emit('waste', product)"
            >
                <PackageX class="h-4 w-4" />
            </button>
        </Tooltip>
        <Tooltip text="Editar">
            <button
                class="rounded p-1.5 text-ink/60 hover:bg-paper"
                @click="$emit('edit', product)"
            >
                <Pencil class="h-4 w-4" />
            </button>
        </Tooltip>
        <Tooltip :text="product.is_active ? 'Desactivar' : 'Reactivar'">
            <button
                class="rounded p-1.5 hover:bg-paper"
                :class="product.is_active ? 'text-danger' : 'text-ok'"
                @click="$emit('toggle', product)"
            >
                <component
                    :is="product.is_active ? PowerOff : Power"
                    class="h-4 w-4"
                />
            </button>
        </Tooltip>
    </div>
</template>
