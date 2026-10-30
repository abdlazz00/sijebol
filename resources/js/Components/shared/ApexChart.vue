<script setup lang="ts">
import { computed, defineAsyncComponent, onMounted, ref } from 'vue';

const props = withDefaults(
    defineProps<{
        type: 'line' | 'area' | 'bar' | 'pie' | 'donut' | 'radialBar';
        series: any[];
        options?: Record<string, any>;
        height?: number | string;
        width?: number | string;
    }>(),
    {
        options: () => ({}),
        height: 300,
        width: '100%',
    },
);

const isMounted = ref(false);

const VueApexCharts = defineAsyncComponent(() =>
    import('vue3-apexcharts').then((m) => m.default || m),
);

onMounted(() => {
    isMounted.value = true;
});

const containerStyle = computed(() => ({
    minHeight: typeof props.height === 'number' ? `${props.height}px` : props.height,
    height: typeof props.height === 'number' ? `${props.height}px` : props.height,
    width: typeof props.width === 'number' ? `${props.width}px` : props.width,
}));
</script>

<template>
    <div
        data-testid="apexchart-container"
        class="relative w-full overflow-hidden flex items-center justify-center"
        :style="containerStyle"
    >
        <VueApexCharts
            v-if="isMounted"
            :type="type"
            :height="height"
            :width="width"
            :options="options"
            :series="series"
            class="w-full h-full"
        />
        <div
            v-else
            class="w-full h-full flex items-center justify-center bg-muted/20 animate-pulse rounded-md"
            aria-label="Memuat grafik..."
        />
    </div>
</template>
