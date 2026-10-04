<script setup>
import { ref } from "vue";
import { router } from "@inertiajs/vue3";
import { Plus, Pencil, Trash2, GripVertical } from "lucide-vue-next";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";

const props = defineProps({
    categories: { type: Array, required: true },
});

// Copia local editable para poder reordenar visualmente antes de confirmar con el servidor
const localCategories = ref([...props.categories]);

const editingCategory = ref(null);
const form = ref({ name: "" });
const errors = ref({});
const isSubmitting = ref(false);

function openCreateForm() {
    editingCategory.value = null;
    form.value = { name: "" };
    errors.value = {};
}

function openEditForm(category) {
    editingCategory.value = category;
    form.value = { name: category.name };
    errors.value = {};
}

function submit() {
    if (isSubmitting.value) return;
    isSubmitting.value = true;

    const options = {
        preserveScroll: true,
        onError: (formErrors) => (errors.value = formErrors),
        onSuccess: () => {
            openCreateForm();
            localCategories.value = [...props.categories]; // sincroniza con los datos frescos del servidor
        },
        onFinish: () => (isSubmitting.value = false),
    };

    if (editingCategory.value) {
        router.patch(
            route("product-categories.update", editingCategory.value.id),
            form.value,
            options,
        );
    } else {
        router.post(route("product-categories.store"), form.value, options);
    }
}

function destroy(category) {
    if (!confirm(`¿Eliminar la categoría "${category.name}"?`)) return;
    router.delete(route("product-categories.destroy", category.id), {
        preserveScroll: true,
    });
}

// --- Drag & drop ---
const draggedIndex = ref(null);
const dragOverIndex = ref(null);

function onDragStart(index) {
    draggedIndex.value = index;
}

function onDragOver(index) {
    dragOverIndex.value = index;
}

function onDrop(index) {
    if (draggedIndex.value === null || draggedIndex.value === index) {
        resetDrag();
        return;
    }

    const updated = [...localCategories.value];
    const [moved] = updated.splice(draggedIndex.value, 1);
    updated.splice(index, 0, moved);
    localCategories.value = updated;

    resetDrag();
    persistOrder();
}

function resetDrag() {
    draggedIndex.value = null;
    dragOverIndex.value = null;
}

function persistOrder() {
    router.patch(
        route("product-categories.reorder"),
        { ids: localCategories.value.map((c) => c.id) },
        { preserveScroll: true, preserveState: true },
    );
}
</script>

<template>
    <AuthenticatedLayout>
        <template #header>
            <h1 class="text-xl font-semibold text-ink">
                Categorías de productos
            </h1>
        </template>

        <div class="mx-auto max-w-2xl px-4 py-8 space-y-4">
            <!-- Formulario crear/editar -->
            <div class="rounded-lg border border-line bg-surface p-4 shadow-sm">
                <h2 class="mb-3 text-sm font-semibold text-ink">
                    {{
                        editingCategory
                            ? `Editando: ${editingCategory.name}`
                            : "Nueva categoría"
                    }}
                </h2>

                <div>
                    <label class="block text-xs font-medium text-ink/70"
                        >Nombre</label
                    >
                    <input
                        v-model="form.name"
                        type="text"
                        placeholder="ej: Cervezas, Gaseosas"
                        class="mt-1 w-full rounded border-line text-sm focus:border-accent focus:ring-accent"
                    />
                    <p v-if="errors.name" class="mt-1 text-xs text-danger">
                        {{ errors.name }}
                    </p>
                </div>

                <div class="mt-3 flex gap-2">
                    <button
                        class="flex items-center gap-1.5 rounded bg-accent px-4 py-2 text-sm font-medium text-white hover:bg-accent-dark disabled:opacity-50"
                        :disabled="isSubmitting"
                        @click="submit"
                    >
                        <Plus class="h-4 w-4" />
                        {{
                            isSubmitting
                                ? "Guardando..."
                                : editingCategory
                                  ? "Guardar cambios"
                                  : "Crear categoría"
                        }}
                    </button>
                    <button
                        v-if="editingCategory"
                        class="rounded px-4 py-2 text-sm text-ink/70"
                        @click="openCreateForm"
                    >
                        Cancelar
                    </button>
                </div>
            </div>

            <!-- Listado reordenable -->
            <div
                class="overflow-hidden rounded-lg border border-line bg-surface shadow-sm"
            >
                <div
                    class="border-b border-line bg-accent px-4 py-2.5 text-sm font-medium text-white"
                >
                    Arrastra
                    <GripVertical
                        class="inline h-3.5 w-3.5 align-text-bottom"
                    />
                    para cambiar el orden de aparición
                </div>

                <div
                    v-for="(category, index) in localCategories"
                    :key="category.id"
                    draggable="true"
                    class="flex items-center gap-3 border-b border-line/60 bg-surface px-4 py-3 transition last:border-0"
                    :class="{
                        'opacity-40': draggedIndex === index,
                        'bg-accent/5':
                            dragOverIndex === index && draggedIndex !== index,
                    }"
                    @dragstart="onDragStart(index)"
                    @dragover.prevent="onDragOver(index)"
                    @drop.prevent="onDrop(index)"
                    @dragend="resetDrag"
                >
                    <GripVertical
                        class="h-4 w-4 shrink-0 cursor-grab text-ink/30 active:cursor-grabbing"
                    />

                    <div class="flex-1">
                        <p class="text-sm text-ink">{{ category.name }}</p>
                        <p class="text-xs text-ink/40">
                            {{ category.products_count }} producto{{
                                category.products_count === 1 ? "" : "s"
                            }}
                        </p>
                    </div>

                    <div class="flex items-center gap-1">
                        <button
                            class="rounded p-1.5 text-ink/60 hover:bg-paper"
                            @click="openEditForm(category)"
                        >
                            <Pencil class="h-4 w-4" />
                        </button>
                        <button
                            class="rounded p-1.5 text-danger hover:bg-danger/10"
                            @click="destroy(category)"
                        >
                            <Trash2 class="h-4 w-4" />
                        </button>
                    </div>
                </div>

                <div
                    v-if="localCategories.length === 0"
                    class="px-4 py-6 text-center text-sm text-ink/40"
                >
                    No hay categorías aún.
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
