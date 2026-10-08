<script setup lang="ts">
import FormField from '@/Components/shared/FormField.vue';
import InlineAlert from '@/Components/shared/InlineAlert.vue';
import SelectField from '@/Components/shared/SelectField.vue';
import { Button } from '@/Components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/Components/ui/dialog';
import { formatBytes } from '@/lib/files';
import type { PageProps } from '@/types';
import type { ImportResult } from '@/types/student';
import { useForm, usePage } from '@inertiajs/vue3';
import { Download, LoaderCircle } from '@lucide/vue';
import { computed, ref, useId } from 'vue';

const props = defineProps<{ schools: { id: number; name: string }[] | null }>();
const open = defineModel<boolean>('open', { required: true });

const MAX_BYTES = 2 * 1024 * 1024;
const fileId = useId();
const page = usePage<PageProps>();
const form = useForm<{ file: File | null; school_id: number | '' }>({
    file: null,
    school_id: '',
});
const result = ref<ImportResult | null>(null);
const clientError = ref('');

const schoolOptions = computed(() =>
    (props.schools ?? []).map((s) => ({ value: s.id, label: s.name })),
);

function pick(event: Event) {
    const picked = (event.target as HTMLInputElement).files?.[0] ?? null;
    form.clearErrors();
    clientError.value = '';
    form.file = null;
    if (!picked) return;
    if (!picked.name.toLowerCase().endsWith('.xlsx'))
        clientError.value = 'Berkas harus berformat .xlsx.';
    else if (picked.size > MAX_BYTES)
        clientError.value = 'Ukuran berkas maksimal 2 MB.';
    else form.file = picked;
}

function submit() {
    if (props.schools && form.school_id === '') {
        form.setError('school_id', 'Pilih sekolah.');
        return;
    }
    form.post(route('siswa.impor.store'), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            result.value = page.props.flash.import_result ?? null;
        },
    });
}

function again() {
    result.value = null;
    form.reset();
    form.clearErrors();
}

function close() {
    open.value = false;
    again();
}
</script>

<template>
    <Dialog v-model:open="open" @update:open="(v) => !v && again()">
        <DialogContent class="rounded-xl sm:max-w-[560px]">
            <DialogHeader>
                <DialogTitle>Impor siswa dari Excel</DialogTitle>
                <DialogDescription>
                    Baris yang benar langsung ditambahkan; baris yang salah
                    dilaporkan beserta alasannya.
                </DialogDescription>
            </DialogHeader>

            <div v-if="result" class="flex flex-col gap-3 text-sm">
                <InlineAlert
                    :variant="result.failed.length ? 'warning' : 'success'"
                >
                    {{ result.imported }} siswa berhasil ditambahkan,
                    {{ result.failed.length }} baris gagal.
                </InlineAlert>
                <div
                    v-if="result.failed.length > 0"
                    class="max-h-[320px] overflow-auto rounded-lg border"
                >
                    <table class="w-full text-left text-sm">
                        <thead
                            class="bg-secondary text-muted-foreground text-xs"
                        >
                            <tr>
                                <th
                                    scope="col"
                                    class="w-16 px-3 py-2 font-medium"
                                >
                                    Baris
                                </th>
                                <th scope="col" class="px-3 py-2 font-medium">
                                    Alasan
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="f in result.failed"
                                :key="f.row"
                                class="border-t align-top"
                            >
                                <td class="px-3 py-2">{{ f.row }}</td>
                                <td class="px-3 py-2">{{ f.reason }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <DialogFooter>
                    <Button variant="outline" @click="again">Impor lagi</Button>
                    <Button @click="close">Tutup</Button>
                </DialogFooter>
            </div>

            <form
                v-else
                class="flex flex-col gap-4"
                novalidate
                @submit.prevent="submit"
            >
                <p class="text-sm leading-5">
                    <strong class="font-medium">1.</strong>
                    <a
                        :href="route('siswa.impor.template')"
                        class="text-primary ml-1 inline-flex items-center gap-1 font-medium hover:underline"
                    >
                        <Download class="size-4" aria-hidden="true" />
                        Unduh template (.xlsx)
                    </a>
                </p>
                <p class="text-muted-foreground text-xs leading-4">
                    Kolom NIK, NISN, dan Tanggal lahir berformat teks; isi
                    tanggal sebagai YYYY-MM-DD atau DD/MM/YYYY. Maksimal 500
                    baris.
                </p>

                <FormField
                    v-if="schools"
                    v-slot="{ id, describedBy, invalid }"
                    label="Sekolah"
                    required
                    :error="form.errors.school_id"
                >
                    <SelectField
                        :id="id"
                        v-model="form.school_id"
                        :options="schoolOptions"
                        placeholder="Pilih sekolah"
                        :invalid="invalid"
                        :described-by="describedBy"
                    />
                </FormField>

                <div class="flex flex-col gap-2">
                    <label :for="fileId" class="text-sm leading-5 font-medium">
                        <span class="mr-1">2.</span>Berkas Excel
                        <span class="text-danger" aria-hidden="true">*</span>
                    </label>
                    <input
                        :id="fileId"
                        type="file"
                        accept=".xlsx"
                        class="border-input file:bg-secondary file:text-foreground block w-full rounded-lg border text-sm file:mr-3 file:h-10 file:border-0 file:px-4 file:text-sm file:font-medium"
                        @change="pick"
                    />
                    <p
                        v-if="form.file"
                        class="text-muted-foreground text-xs leading-4"
                    >
                        {{ form.file.name }} · {{ formatBytes(form.file.size) }}
                    </p>
                </div>

                <InlineAlert v-if="clientError || form.errors.file">{{
                    clientError || form.errors.file
                }}</InlineAlert>

                <DialogFooter>
                    <Button type="button" variant="outline" @click="close"
                        >Batal</Button
                    >
                    <Button
                        type="submit"
                        :disabled="form.processing || !form.file"
                    >
                        <LoaderCircle
                            v-if="form.processing"
                            class="size-4 animate-spin"
                            aria-hidden="true"
                        />
                        {{ form.processing ? 'Mengimpor…' : 'Impor' }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
