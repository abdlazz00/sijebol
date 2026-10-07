import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import DataTable from './DataTable.vue';

const columns = [
    { key: 'name', label: 'Nama' },
    { key: 'role', label: 'Role' },
];

describe('DataTable', () => {
    it('merender header dan baris', () => {
        const wrapper = mount(DataTable, {
            props: { columns, rows: [{ name: 'Budi', role: 'Operator' }] },
        });
        expect(wrapper.text()).toContain('Nama');
        expect(wrapper.text()).toContain('Budi');
    });

    it('memakai slot sel kustom', () => {
        const wrapper = mount(DataTable, {
            props: { columns, rows: [{ name: 'Budi', role: 'Operator' }] },
            slots: {
                'cell-role':
                    '<template #cell-role="{ row }">[{{ row.role }}]</template>',
            },
        });
        expect(wrapper.text()).toContain('[Operator]');
    });

    it('menampilkan keadaan kosong saat tidak ada baris', () => {
        const wrapper = mount(DataTable, { props: { columns, rows: [] } });
        expect(wrapper.text()).toContain('Tidak ada data');
    });

    it('menampilkan skeleton saat loading', () => {
        const wrapper = mount(DataTable, {
            props: { columns, rows: [], loading: true },
        });
        expect(wrapper.findAll('[data-skeleton-row]').length).toBeGreaterThan(
            0,
        );
        expect(wrapper.text()).not.toContain('Tidak ada data');
    });
});
