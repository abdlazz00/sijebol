<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref } from 'vue';

interface TurnstileApi {
    render: (el: HTMLElement, options: Record<string, unknown>) => string;
    reset: (id: string) => void;
    remove: (id: string) => void;
}

declare global {
    interface Window {
        turnstile?: TurnstileApi;
    }
}

const props = defineProps<{ siteKey: string }>();
const emit = defineEmits<{
    'update:token': [token: string];
    'load-error': [];
}>();

const SCRIPT_SRC =
    'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit';

let loading: Promise<TurnstileApi> | undefined;

function loadApi(): Promise<TurnstileApi> {
    if (window.turnstile) return Promise.resolve(window.turnstile);

    loading ??= new Promise<TurnstileApi>((resolve, reject) => {
        const script = document.createElement('script');
        script.src = SCRIPT_SRC;
        script.async = true;
        script.onload = () => resolve(window.turnstile!);
        script.onerror = () => {
            loading = undefined;
            reject(new Error('Turnstile gagal dimuat'));
        };
        document.head.appendChild(script);
    });

    return loading;
}

const container = ref<HTMLElement>();
let api: TurnstileApi | undefined;
let widgetId: string | undefined;

onMounted(async () => {
    try {
        api = await loadApi();
    } catch {
        // Tanpa widget tidak ada token: halaman menjelaskan kepada pengguna.
        emit('load-error');
        return;
    }
    if (!container.value) return;

    widgetId = api.render(container.value, {
        sitekey: props.siteKey,
        // Tak terlihat kecuali Cloudflare butuh interaksi pengguna.
        appearance: 'interaction-only',
        callback: (token: string) => emit('update:token', token),
        'expired-callback': () => emit('update:token', ''),
        'error-callback': () => {
            emit('update:token', '');
            emit('load-error');
        },
    });
});

onBeforeUnmount(() => {
    if (api && widgetId) api.remove(widgetId);
});

/** Token Turnstile sekali pakai: panggil setelah setiap pengiriman. */
function reset() {
    emit('update:token', '');
    if (api && widgetId) api.reset(widgetId);
}

defineExpose({ reset });
</script>

<template>
    <div ref="container" />
</template>
