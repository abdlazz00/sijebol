<script setup lang="ts">
import ConfirmDialog from '@/Components/shared/ConfirmDialog.vue';
import { Button } from '@/Components/ui/button';
import { formatBytes, validateFile } from '@/lib/files';
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
const form = useForm<{ file: File | null }>({ file: null });

const showPicker = computed(
    () =>
        props.canUpload &&
        (props.existing === null || replacing.value) &&
        file.value === null,
);
const error = computed(() => clientError.value || form.errors.file);

function choose(picked?: File | null) {
    if (!picked) return;
    const problem = validateFile(picked);
    form.clearErrors();
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
    choose(target.files?.[0]);
    target.value = '';
}

function onDrop(event: DragEvent) {
    dragging.value = false;
    choose(event.dataTransfer?.files?.[0]);
}

function cancel() {
    file.value = null;
    replacing.value = false;
    clientError.value = '';
    form.reset();
    form.clearErrors();
}

function upload() {
    form.post(props.uploadUrl, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: cancel,
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
            <div class="flex items-center gap-1">
                <Button
                    type="button"
                    variant="ghost"
                    class="h-10 px-3"
                    @click="emit('preview')"
                >
                    <Eye class="size-4" aria-hidden="true" />
                    Lihat
                </Button>
                <Button as-child variant="ghost" class="h-10 px-3">
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
                >Pilih berkas atau seret ke sini</span
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
                    Unggah
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
            <progress
                v-if="form.processing && form.progress"
                class="h-1 w-full"
                max="100"
                :value="form.progress.percentage"
            />
        </div>
        <span class="sr-only" role="status">{{
            form.processing && form.progress
                ? `Mengunggah ${form.progress.percentage}%`
                : ''
        }}</span>

        <p
            v-if="!existing && !canUpload"
            class="text-muted-foreground text-sm leading-5"
        >
            Belum diunggah.
        </p>
        <p v-if="error" class="text-danger text-xs leading-4" role="alert">
            {{ error }}
        </p>

        <ConfirmDialog
            v-model:open="confirmOpen"
            :title="`Hapus ${label}?`"
            description="Berkas akan dihapus permanen dan siswa dapat menjadi belum memenuhi syarat."
            confirm-label="Hapus"
            destructive
            @confirm="remove"
        />
    </div>
</template>
