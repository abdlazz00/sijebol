<script setup lang="ts">
import { Button } from '@/Components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/Components/ui/dialog';
import { formatBytes } from '@/lib/files';
import type { DocumentType, StudentDocumentRow } from '@/types/student';
import {
    AlertCircle,
    Download,
    ExternalLink,
    FileText,
    LoaderCircle,
    RotateCcw,
    X,
    ZoomIn,
    ZoomOut,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';

const open = defineModel<boolean>('open', { required: true });

const props = withDefaults(
    defineProps<{
        title: string;
        documents: StudentDocumentRow[];
        initialType?: DocumentType;
    }>(),
    {
        initialType: 'kk',
    },
);

const activeType = ref<DocumentType>(props.initialType);
const zoom = ref(1);
const loading = ref(true);
const loadError = ref(false);

const docTypeLabels: Record<DocumentType, string> = {
    kk: 'Kartu Keluarga',
    akta_lahir: 'Akta Kelahiran',
};

watch(
    open,
    (isOpen) => {
        if (isOpen) {
            activeType.value = props.initialType;
            zoom.value = 1;
            loading.value = true;
            loadError.value = false;
        }
    },
);

watch(
    () => props.initialType,
    (newType) => {
        if (newType) activeType.value = newType;
    },
);

const activeDoc = computed<StudentDocumentRow | null>(() => {
    return (
        props.documents.find((d) => d.type === activeType.value) ??
        props.documents[0] ??
        null
    );
});

watch(activeDoc, () => {
    zoom.value = 1;
    loading.value = true;
    loadError.value = false;
});

const isPdf = computed(() => {
    if (!activeDoc.value) return false;
    const mime = activeDoc.value.mime?.toLowerCase() ?? '';
    const name = activeDoc.value.name.toLowerCase();
    return mime.includes('pdf') || name.endsWith('.pdf');
});

function selectType(type: DocumentType) {
    activeType.value = type;
}

function zoomIn() {
    if (zoom.value < 3) zoom.value = Number((zoom.value + 0.25).toFixed(2));
}

function zoomOut() {
    if (zoom.value > 0.5) zoom.value = Number((zoom.value - 0.25).toFixed(2));
}

function resetZoom() {
    zoom.value = 1;
}

function onLoaded() {
    loading.value = false;
    loadError.value = false;
}

function onError() {
    loading.value = false;
    loadError.value = true;
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent
            class="sm:max-w-5xl w-[95vw] h-[88vh] max-h-[92vh] p-0 flex flex-col gap-0 overflow-hidden bg-background border shadow-2xl rounded-xl"
            :show-close-button="false"
        >
            <!-- Header Modal -->
            <DialogHeader
                class="flex flex-col sm:flex-row sm:items-center sm:justify-between px-5 py-3.5 border-b bg-muted/40 shrink-0 gap-3"
            >
                <div class="flex flex-col gap-0.5 min-w-0">
                    <DialogTitle class="text-base sm:text-lg font-semibold truncate">
                        {{ title }}
                    </DialogTitle>
                    <DialogDescription class="text-xs text-muted-foreground truncate">
                        Pratinjau berkas kelengkapan persyaratan pendaftaran
                    </DialogDescription>
                </div>

                <div class="flex items-center flex-wrap gap-2">
                    <!-- Tab Switcher Dokumen (KK & Akta) -->
                    <div
                        v-if="documents.length > 0"
                        class="inline-flex items-center bg-muted/70 p-1 rounded-lg border text-xs font-medium"
                        role="tablist"
                        aria-label="Pilihan dokumen siswa"
                    >
                        <button
                            v-for="doc in documents"
                            :key="doc.type"
                            type="button"
                            role="tab"
                            :aria-selected="activeDoc?.type === doc.type"
                            class="px-3 py-1.5 rounded-md transition-all flex items-center gap-1.5"
                            :class="
                                activeDoc?.type === doc.type
                                    ? 'bg-background text-foreground shadow-xs font-semibold'
                                    : 'text-muted-foreground hover:text-foreground'
                            "
                            @click="selectType(doc.type)"
                        >
                            <FileText class="size-3.5 shrink-0" aria-hidden="true" />
                            <span>{{ docTypeLabels[doc.type] }}</span>
                        </button>
                    </div>

                    <!-- Tombol Aksi Tambahan -->
                    <div class="flex items-center gap-1.5 ml-auto sm:ml-2">
                        <Button
                            v-if="activeDoc"
                            as-child
                            variant="outline"
                            size="sm"
                            class="h-8.5 px-2.5 text-xs gap-1.5"
                            title="Buka dokumen di tab baru browser"
                        >
                            <a
                                :href="activeDoc.preview_url || activeDoc.url"
                                target="_blank"
                                rel="noopener noreferrer"
                            >
                                <ExternalLink class="size-3.5" aria-hidden="true" />
                                <span class="hidden md:inline">Buka Tab Baru</span>
                            </a>
                        </Button>

                        <Button
                            v-if="activeDoc"
                            as-child
                            variant="outline"
                            size="sm"
                            class="h-8.5 px-2.5 text-xs gap-1.5"
                            title="Unduh berkas ke perangkat"
                        >
                            <a :href="activeDoc.url">
                                <Download class="size-3.5" aria-hidden="true" />
                                <span class="hidden md:inline">Unduh</span>
                            </a>
                        </Button>

                        <DialogClose as-child>
                            <Button
                                variant="ghost"
                                size="sm"
                                class="h-8.5 w-8.5 p-0 rounded-md"
                                title="Tutup pratinjau"
                            >
                                <X class="size-4" aria-hidden="true" />
                                <span class="sr-only">Tutup</span>
                            </Button>
                        </DialogClose>
                    </div>
                </div>
            </DialogHeader>

            <!-- Viewer Area -->
            <div class="relative flex-1 min-h-0 w-full overflow-hidden flex flex-col bg-neutral-950/5 dark:bg-neutral-950/40">
                <!-- Toolbar Khusus Gambar -->
                <div
                    v-if="activeDoc && !isPdf && !loadError"
                    class="absolute top-3 left-1/2 -translate-x-1/2 z-10 flex items-center gap-1 bg-background/90 backdrop-blur-xs px-2.5 py-1 rounded-full border shadow-sm text-xs"
                >
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        class="h-7 w-7 p-0 rounded-full"
                        title="Perkecil"
                        :disabled="zoom <= 0.5"
                        @click="zoomOut"
                    >
                        <ZoomOut class="size-3.5" aria-hidden="true" />
                    </Button>
                    <span class="w-12 text-center font-mono font-medium select-none">
                        {{ Math.round(zoom * 100) }}%
                    </span>
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        class="h-7 w-7 p-0 rounded-full"
                        title="Perbesar"
                        :disabled="zoom >= 3"
                        @click="zoomIn"
                    >
                        <ZoomIn class="size-3.5" aria-hidden="true" />
                    </Button>
                    <div class="h-3.5 w-px bg-border mx-0.5" />
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        class="h-7 w-7 p-0 rounded-full"
                        title="Atur ulang ukuran"
                        :disabled="zoom === 1"
                        @click="resetZoom"
                    >
                        <RotateCcw class="size-3.5" aria-hidden="true" />
                    </Button>
                </div>

                <!-- Loading State Indicator -->
                <div
                    v-if="loading && activeDoc && !loadError"
                    class="absolute inset-0 flex items-center justify-center bg-background/50 backdrop-blur-2xs z-5"
                >
                    <div class="flex flex-col items-center gap-2 p-4 rounded-lg bg-card border shadow-xs">
                        <LoaderCircle class="size-6 animate-spin text-primary" aria-hidden="true" />
                        <span class="text-xs text-muted-foreground font-medium">Memuat berkas...</span>
                    </div>
                </div>

                <!-- Konten PDF -->
                <template v-if="activeDoc && isPdf && !loadError">
                    <iframe
                        :key="activeDoc.preview_url || activeDoc.url"
                        :src="activeDoc.preview_url || activeDoc.url"
                        class="w-full h-full border-0 flex-1"
                        title="Pratinjau Dokumen PDF"
                        @load="onLoaded"
                        @error="onError"
                    />
                </template>

                <!-- Konten Gambar -->
                <template v-else-if="activeDoc && !isPdf && !loadError">
                    <div class="flex-1 w-full h-full overflow-auto p-4 flex items-center justify-center">
                        <img
                            :key="activeDoc.preview_url || activeDoc.url"
                            :src="activeDoc.preview_url || activeDoc.url"
                            :alt="activeDoc.name"
                            class="max-w-full max-h-full object-contain rounded-md transition-transform duration-100 origin-center select-none shadow-sm"
                            :style="{ transform: `scale(${zoom})` }"
                            @load="onLoaded"
                            @error="onError"
                        />
                    </div>
                </template>

                <!-- Error Fallback State -->
                <div
                    v-else-if="loadError && activeDoc"
                    class="flex-1 flex flex-col items-center justify-center p-6 text-center gap-3"
                >
                    <AlertCircle class="size-10 text-danger" aria-hidden="true" />
                    <div class="max-w-md flex flex-col gap-1">
                        <h4 class="text-sm font-semibold">Tidak dapat memuat pratinjau langsung</h4>
                        <p class="text-xs text-muted-foreground">
                            Format berkas ini mungkin tidak didukung oleh browser atau berkas bermasalah. Anda tetap dapat mengunduh berkas aslinya.
                        </p>
                    </div>
                    <Button as-child variant="default" size="sm" class="gap-1.5">
                        <a :href="activeDoc.url">
                            <Download class="size-4" aria-hidden="true" />
                            Unduh Berkas ({{ formatBytes(activeDoc.size) }})
                        </a>
                    </Button>
                </div>

                <!-- Empty State (Tidak ada berkas sama sekali) -->
                <div
                    v-else
                    class="flex-1 flex flex-col items-center justify-center p-6 text-center gap-2"
                >
                    <FileText class="size-10 text-muted-foreground/50" aria-hidden="true" />
                    <p class="text-sm font-medium text-muted-foreground">
                        Belum ada dokumen yang tersedia untuk ditampilkan.
                    </p>
                </div>
            </div>

            <!-- Footer Ringkas Informasi Berkas -->
            <div
                v-if="activeDoc"
                class="px-5 py-2.5 border-t bg-muted/20 text-xs text-muted-foreground flex flex-wrap items-center justify-between gap-2 shrink-0"
            >
                <span class="truncate font-medium text-foreground">
                    {{ activeDoc.name }}
                </span>
                <span class="shrink-0">
                    Ukuran: {{ formatBytes(activeDoc.size) }}
                </span>
            </div>
        </DialogContent>
    </Dialog>
</template>
