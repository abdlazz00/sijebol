<script setup lang="ts">
import ConfirmDialog from '@/Components/shared/ConfirmDialog.vue';
import { Button } from '@/Components/ui/button';
import { formatBytes, validateFile } from '@/lib/files';
import { shrinkImage } from '@/lib/imageResize';
import type { StudentDocumentRow } from '@/types/student';
import { router, useForm } from '@inertiajs/vue3';
import { Download, Eye, FileText, LoaderCircle, Upload } from '@lucide/vue';
import { computed, ref, useId } from 'vue';

const props = defineProps<{
    label: string;
    existing: StudentDocumentRow | null;
    canUpload: boolean;
    canDelete: boolean;
    uploadUrl: string;
    deleteUrl: string;
    /** Siswa memegang berkasnya sendiri: tombol Unduh tidak diperlukan. */
    hideDownload?: boolean;
}>();

const emit = defineEmits<{
    (e: 'preview'): void;
}>();

const inputId = useId();
const file = ref<File | null>(null);
const clientError = ref('');
const dragging = ref(false);
const replacing = ref(false);
const confirmOpen = ref(false);
const preparing = ref(false);
const failed = ref(false);
const form = useForm<{ file: File | null }>({ file: null });

const showPicker = computed(
    () =>
        props.canUpload &&
        (props.existing === null || replacing.value) &&
        file.value === null,
);
const error = computed(() => clientError.value || form.errors.file);
const percentage = computed(() => form.progress?.percentage ?? 0);

async function choose(raw?: File | null) {
    if (!raw) return;
    form.clearErrors();
    failed.value = false;

    preparing.value = true;
    const picked = await shrinkImage(raw);
    preparing.value = false;

    const problem = validateFile(picked);
    if (problem) {
        clientError.value = problem;
        file.value = null;
        return;
    }
    clientError.value = '';
    file.value = picked;
    form.file = picked;
}

function onPick(event: Event) {
    const target = event.target as HTMLInputElement;
    void choose(target.files?.[0]);
    target.value = '';
}

function onDrop(event: DragEvent) {
    dragging.value = false;
    void choose(event.dataTransfer?.files?.[0]);
}

function cancel() {
    file.value = null;
    replacing.value = false;
    clientError.value = '';
    failed.value = false;
    form.reset();
    form.clearErrors();
}

function upload() {
    failed.value = false;
    let succeeded = false;

    form.post(props.uploadUrl, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            succeeded = true;
            cancel();
        },
        // Jaringan putus atau respons non-validasi: berkas tetap terpilih agar bisa dicoba lagi tanpa memilih ulang.
        onFinish: () => {
            if (!succeeded && !form.hasErrors) failed.value = true;
        },
    });
}

function remove() {
    router.delete(props.deleteUrl, { preserveScroll: true });
}
</script>

<template>
    <div class="flex flex-col gap-2">
        <h3 class="text-sm leading-5 font-medium">{{ label }}</h3>

        <div
            v-if="existing && !replacing && !file"
            class="bg-secondary flex flex-wrap items-center gap-3 rounded-lg border p-3"
        >
            <FileText
                class="text-muted-foreground size-5 shrink-0"
                aria-hidden="true"
            />
            <div class="min-w-0 flex-1">
                <p class="truncate text-sm leading-5 font-medium">
                    {{ existing.name }}
                </p>
                <p class="text-muted-foreground text-xs leading-4">
                    {{ formatBytes(existing.size) }}
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <Button
                    type="button"
                    variant="ghost"
                    class="h-10 px-3"
                    @click="emit('preview')"
                >
                    <Eye class="size-4" aria-hidden="true" />
                    Lihat
                </Button>
                <Button
                    v-if="!hideDownload"
                    as-child
                    variant="ghost"
                    class="h-10 px-3"
                >
                    <a :href="existing.url">
                        <Download class="size-4" aria-hidden="true" />
                        Unduh
                    </a>
                </Button>
                <Button
                    v-if="canUpload"
                    type="button"
                    variant="ghost"
                    class="h-10 px-3"
                    @click="replacing = true"
                >
                    Ganti
                </Button>
                <Button
                    v-if="canDelete"
                    type="button"
                    variant="ghost"
                    class="text-danger hover:text-danger h-10 px-3"
                    @click="confirmOpen = true"
                >
                    Hapus
                </Button>
            </div>
        </div>

        <label
            v-if="showPicker"
            :for="inputId"
            class="focus-within:ring-ring/50 flex cursor-pointer flex-col items-center gap-1 rounded-lg border-2 border-dashed px-4 py-6 text-center transition-colors focus-within:ring-3"
            :class="
                dragging
                    ? 'border-primary bg-primary-soft'
                    : 'border-input hover:bg-secondary'
            "
            @dragover.prevent="dragging = true"
            @dragleave="dragging = false"
            @drop.prevent="onDrop"
        >
            <Upload class="text-muted-foreground size-5" aria-hidden="true" />
            <span class="text-sm leading-5 font-medium"
                >Pilih berkas<span class="hidden md:inline">
                    atau seret ke sini</span
                ></span
            >
            <span class="text-muted-foreground text-xs leading-4"
                >PDF, JPG, atau PNG, maksimal 5 MB</span
            >
            <input
                :id="inputId"
                type="file"
                class="sr-only"
                accept=".pdf,.jpg,.jpeg,.png"
                @change="onPick"
            />
        </label>

        <p
            v-if="preparing"
            class="text-muted-foreground flex items-center gap-2 text-xs leading-4"
            role="status"
        >
            <LoaderCircle class="size-4 animate-spin" aria-hidden="true" />
            Menyiapkan berkas…
        </p>

        <div
            v-if="file"
            class="bg-secondary flex flex-wrap items-center gap-3 rounded-lg border p-3"
        >
            <FileText
                class="text-muted-foreground size-5 shrink-0"
                aria-hidden="true"
            />
            <div class="min-w-0 flex-1">
                <p class="truncate text-sm leading-5 font-medium">
                    {{ file.name }}
                </p>
                <p class="text-muted-foreground text-xs leading-4">
                    {{ formatBytes(file.size) }}
                </p>
            </div>
            <div class="flex items-center gap-2">
                <Button
                    type="button"
                    class="h-10 px-4"
                    :disabled="form.processing"
                    @click="upload"
                >
                    <LoaderCircle
                        v-if="form.processing"
                        class="size-4 animate-spin"
                        aria-hidden="true"
                    />
                    {{ failed ? 'Coba lagi' : 'Unggah' }}
                </Button>
                <Button
                    type="button"
                    variant="outline"
                    class="h-10 px-4"
                    :disabled="form.processing"
                    @click="cancel"
                >
                    Batal
                </Button>
            </div>
            <div v-if="form.processing" class="flex w-full flex-col gap-1">
                <progress
                    class="h-2 w-full"
                    max="100"
                    :value="percentage"
                    :aria-label="`Mengunggah ${label}`"
                />
                <p
                    class="text-muted-foreground text-xs leading-4"
                    role="status"
                >
                    Mengunggah {{ percentage }}%… jangan tutup halaman.
                </p>
            </div>
        </div>

        <p
            v-if="!existing && !canUpload"
            class="text-muted-foreground text-sm leading-5"
        >
            Belum diunggah.
        </p>
        <p v-if="error" class="text-danger text-xs leading-4" role="alert">
            {{ error }}
        </p>
        <p v-if="failed" class="text-danger text-xs leading-4" role="alert">
            Gagal mengunggah. Periksa sinyal, lalu coba lagi.
        </p>

        <ConfirmDialog
            v-model:open="confirmOpen"
            :title="`Hapus ${label}?`"
            description="Berkas akan dihapus permanen. Unggah ulang agar data tetap lengkap."
            confirm-label="Hapus"
            destructive
            @confirm="remove"
        />
    </div>
</template>
