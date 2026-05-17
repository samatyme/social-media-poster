import { ref } from 'vue'

const toasts = ref([])
let counter = 0

export function useToast() {
    function add(message, type = 'success', duration = 4000) {
        const id = ++counter
        toasts.value.push({ id, message, type })
        setTimeout(() => remove(id), duration)
    }

    function remove(id) {
        const index = toasts.value.findIndex(t => t.id === id)
        if (index > -1) toasts.value.splice(index, 1)
    }

    return {
        toasts,
        success: (msg)  => add(msg, 'success'),
        error:   (msg)  => add(msg, 'error'),
        warning: (msg)  => add(msg, 'warning'),
        info:    (msg)  => add(msg, 'info'),
        remove,
    }
}
