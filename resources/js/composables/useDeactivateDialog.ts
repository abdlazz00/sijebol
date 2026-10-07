import type { UserRow } from '@/types/access';
import { router } from '@inertiajs/vue3';
import { ref } from 'vue';

/** Status dialog konfirmasi nonaktifkan; menu aksi hanya memanggil `ask`. */
export function useDeactivateDialog() {
    const open = ref(false);
    const target = ref<Pick<UserRow, 'id' | 'name'> | null>(null);

    function ask(user: Pick<UserRow, 'id' | 'name'>) {
        target.value = user;
        open.value = true;
    }

    function confirm() {
        if (target.value)
            router.post(
                route('pengguna.deactivate', target.value.id),
                {},
                { preserveScroll: true },
            );
    }

    return { open, target, ask, confirm };
}
