import { reactive } from "vue";

// Estado a nivel de módulo: todos los componentes que llamen useToast() comparten
// el mismo aviso, así nunca se superponen dos toasts en pantalla
const state = reactive({
    message: null,
    type: "error",
});

let timer = null;
let lastFlashId = null;

function hide() {
    clearTimeout(timer);
    state.message = null;
}

function show(message, type = "error", duration = 4000) {
    clearTimeout(timer);
    state.message = message;
    state.type = type;
    timer = setTimeout(hide, duration);
}

// Muestra el mensaje que el servidor dejó en la sesión (->with('success', ...)).
// El id evita repetir un aviso ya mostrado, por ejemplo al volver atrás en el historial
function showFlash(flash) {
    if (!flash?.id || flash.id === lastFlashId) return;

    lastFlashId = flash.id;
    show(flash.message, flash.type);
}

export function useToast() {
    return {
        state,
        show,
        hide,
        showFlash,
        error: (message) => show(message, "error"),
        info: (message) => show(message, "info"),
        success: (message) => show(message, "success"),
    };
}
