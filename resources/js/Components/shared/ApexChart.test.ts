import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import ApexChart from './ApexChart.vue';

vi.mock('vue3-apexcharts', () => ({
    default: {
        name: 'VueApexCharts',
        props: ['type', 'height', 'width', 'options', 'series'],
        template: '<div data-testid="mock-vue-apexcharts" :data-type="type"></div>',
    },
}));

describe('ApexChart Component', () => {
    it('merender kontainer chart dengan prop yang diberikan tanpa crash', async () => {
        const wrapper = mount(ApexChart, {
            props: {
                type: 'bar',
                height: 300,
                series: [{ name: 'Siswa', data: [10, 20, 30] }],
                options: {
                    chart: { id: 'test-chart' },
                    xaxis: { categories: ['A', 'B', 'C'] },
                },
            },
        });

        expect(wrapper.exists()).toBe(true);
        expect(wrapper.find('[data-testid="apexchart-container"]').exists()).toBe(true);
    });

    it('menggunakan tinggi bawaan 300px jika tidak ditentukan', () => {
        const wrapper = mount(ApexChart, {
            props: {
                type: 'donut',
                series: [40, 60],
                options: {},
            },
        });

        const container = wrapper.find('[data-testid="apexchart-container"]');
        expect(container.attributes('style')).toContain('300px');
    });
});
