import { nextTick } from 'vue';

/** Setelah validasi gagal, pindahkan fokus ke kolom pertama yang bertanda tidak valid. */
export async function focusFirstInvalid() {
    await nextTick();
    document.querySelector<HTMLElement>('[aria-invalid="true"]')?.focus();
}
