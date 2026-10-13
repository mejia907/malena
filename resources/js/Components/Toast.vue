<script setup>
import { computed, ref, watch } from "vue";
import { CircleAlert, CircleCheck, Info, X } from "lucide-vue-next";

const props = defineProps({
    message: { type: String, default: null },
    // error = rojo | info = azul | success = verde
    type: { type: String, default: "error" },
});

defineEmits(["close"]);

// Clases escritas completas (no armadas con variables) para que Tailwind las detecte al compilar
const styles = {
    error: {
        icon: CircleAlert,
        box: "border-red-200 border-l-red-400 bg-red-50",
        iconColor: "text-red-500",
        text: "text-red-800",
        close: "text-red-400 hover:text-red-600",
    },
    info: {
        icon: Info,
        box: "border-sky-200 border-l-sky-400 bg-sky-50",
        iconColor: "text-sky-500",
        text: "text-sky-800",
        close: "text-sky-400 hover:text-sky-600",
    },
    success: {
        icon: CircleCheck,
        box: "border-emerald-200 border-l-emerald-400 bg-emerald-50",
        iconColor: "text-emerald-500",
        text: "text-emerald-800",
        close: "text-emerald-400 hover:text-emerald-600",
    },
};

const current = computed(() => styles[props.type] ?? styles.error);

// Conserva el último texto mientras dura la animación de salida;
// sin esto, el cuadro se vaciaría un instante antes de desaparecer
const lastMessage = ref(props.message ?? "");
watch(
    () => props.message,
    (value) => {
        if (value) lastMessage.value = value;
    },
);
</script>

<template>
    <Transition
        enter-active-class="transition duration-200 ease-out"
        enter-from-class="-translate-y-2 opacity-0"
        enter-to-class="translate-y-0 opacity-100"
        leave-active-class="transition duration-150 ease-in"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
    >
        <div
            v-if="message"
            :role="type === 'error' ? 'alert' : 'status'"
            class="fixed inset-x-4 top-4 z-[60] mx-auto flex max-w-md items-start gap-3 rounded-xl border border-l-4 p-3.5 shadow-lg"
            :class="current.box"
        >
            <component
                :is="current.icon"
                class="mt-0.5 h-5 w-5 shrink-0"
                :class="current.iconColor"
            />
            <p class="flex-1 text-sm font-medium" :class="current.text">
                {{ lastMessage }}
            </p>
            <button
                class="shrink-0"
                :class="current.close"
                @click="$emit('close')"
            >
                <X class="h-4 w-4" />
            </button>
        </div>
    </Transition>
</template>
