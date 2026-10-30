<script setup lang="ts">
import InlineAlert from '@/Components/shared/InlineAlert.vue';
import KtpLangsungBadge from '@/Components/shared/KtpLangsungBadge.vue';
import { Button } from '@/Components/ui/button';
import { usePermission } from '@/composables/usePermission';
import AppLayout from '@/Layouts/AppLayout.vue';
import { formatDateWithDay, formatTime } from '@/lib/date';
import { PERMISSIONS } from '@/lib/permissions';
import { postJson } from '@/lib/postJson';
import type {
    ScanCounts,
    ScanResponse,
    ScheduleDetail,
} from '@/types/schedule';
import { Link } from '@inertiajs/vue3';
import {
    Camera,
    CameraOff,
    CircleCheck,
    CircleX,
    ClipboardCheck,
    TriangleAlert,
} from '@lucide/vue';
import QrScanner from 'qr-scanner';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

const props = defineProps<{
    schedule: ScheduleDetail;
    counts: ScanCounts;
    window: { from: string; to: string };
}>();

type Hasil = {
    kind: 'ok' | 'rejected' | 'error';
    title: string;
    message: string;
    nisn?: string;
    ktpLangsung?: boolean;
    at: Date;
};

const SAMA_DETIK = 5_000;
const RIWAYAT_MAKS = 5;

const { can } = usePermission();
const counts = ref<ScanCounts>({ ...props.counts });
const video = ref<HTMLVideoElement | null>(null);
const state = ref<'idle' | 'starting' | 'scanning'>('idle');
const cameraError = ref<string | null>(null);
const history = ref<Hasil[]>([]);
const last = computed(() => history.value[0] ?? null);

let scanner: QrScanner | null = null;
let busy = false;
let lastCode = '';
let lastAt = 0;

// Hanya untuk peringatan jam; server yang memutuskan. Diperbarui tiap 30 detik agar peringatan hilang saat jendela dibuka.
const now = ref(new Date());
let clock: ReturnType<typeof setInterval> | undefined;
onMounted(() => {
    clock = setInterval(() => (now.value = new Date()), 30_000);
});
const outsideWindow = computed(
    () =>
        now.value < new Date(props.window.from) ||
        now.value > new Date(props.window.to),
);
const windowText = computed(
    () => `${formatTime(props.window.from)}–${formatTime(props.window.to)}`,
);

function disposeScanner() {
    scanner?.stop();
    scanner?.destroy();
    scanner = null;
}

function cameraMessage(e: unknown): string {
    const name = e instanceof Error ? e.name : String(e);

    return name === 'NotAllowedError' || name === 'PermissionDeniedError'
        ? 'Izin kamera ditolak. Izinkan kamera untuk situs ini di pengaturan peramban, lalu tekan Mulai memindai lagi.'
        : 'Kamera tidak dapat dinyalakan. Tutup aplikasi lain yang memakai kamera, lalu coba lagi.';
}

async function startCamera() {
    cameraError.value = null;
    if (!window.isSecureContext) {
        cameraError.value =
            'Kamera butuh HTTPS. Buka aplikasi lewat alamat https:// atau gunakan penandaan manual di bawah.';

        return;
    }
    if (!(await QrScanner.hasCamera())) {
        cameraError.value =
            'Kamera tidak ditemukan di perangkat ini. Gunakan perangkat lain atau penandaan manual di bawah.';

        return;
    }

    state.value = 'starting';
    try {
        scanner = new QrScanner(video.value!, onDecode, {
            preferredCamera: 'environment',
            maxScansPerSecond: 5,
            highlightScanRegion: false,
            returnDetailedScanResult: true,
        });
        await scanner.start();
        state.value = 'scanning';
    } catch (e) {
        disposeScanner();
        state.value = 'idle';
        cameraError.value = cameraMessage(e);
    }
}

function stopCamera() {
    disposeScanner();
    state.value = 'idle';
}

function record(hasil: Omit<Hasil, 'at'>) {
    const item = { ...hasil, at: new Date() };
    history.value = [item, ...history.value].slice(0, RIWAYAT_MAKS);
}

async function onDecode(result: { data: string }) {
    const code = result.data;
    if (busy || (code === lastCode && Date.now() - lastAt < SAMA_DETIK)) return;

    busy = true;
    lastCode = code;
    lastAt = Date.now();
    try {
        const { status, data } = await postJson<ScanResponse>(
            route('schedules.scan.store', props.schedule.id),
            { code },
        );

        if (status === 200 && data?.status === 'ok') {
            counts.value = data.counts;
            record({
                kind: 'ok',
                title: data.student.name,
                message: data.message,
                nisn: data.student.nisn,
                ktpLangsung: data.student.ktp_langsung,
            });
        } else if (status === 422 && data?.message) {
            record({
                kind: 'rejected',
                title: 'Ditolak',
                message: data.message,
            });
        } else if (status === 429) {
            record({
                kind: 'error',
                title: 'Gagal',
                message: 'Terlalu banyak pindaian; tunggu sebentar.',
            });
        } else if (status === 419 || status === 401) {
            record({
                kind: 'error',
                title: 'Gagal',
                message:
                    'Sesi login habis. Muat ulang halaman, lalu pindai lagi.',
            });
        } else {
            record({
                kind: 'error',
                title: 'Gagal',
                message: `Pindaian belum tercatat (kode ${status}). Coba lagi.`,
            });
        }
    } catch {
        lastCode = ''; // Tanpa sambungan: kode yang sama boleh langsung diulang.
        record({
            kind: 'error',
            title: 'Gagal',
            message: 'Tidak ada sambungan. Pindaian belum tercatat; coba lagi.',
        });
    } finally {
        busy = false;
    }
}

onBeforeUnmount(() => {
    clearInterval(clock);
    disposeScanner();
});

const crumbs = computed(() => [
    { label: 'Sesi rekaman', href: route('schedules.index') },
    {
        label: formatDateWithDay(props.schedule.date),
        href: route('schedules.show', props.schedule.id),
    },
    { label: 'Pindai tiket' },
]);

const tone = {
    ok: 'bg-success-soft text-success',
    rejected: 'bg-danger-soft text-danger',
    error: 'bg-warning-soft text-warning',
} as const;
</script>

<template>
    <AppLayout title="Pindai tiket" :breadcrumbs="crumbs">
        <div class="mb-6 flex flex-col gap-1">
            <h1 class="text-2xl leading-8 font-semibold">Pindai tiket</h1>
            <p class="text-muted-foreground text-sm leading-5">
                {{ schedule.location }} ·
                {{ formatDateWithDay(schedule.date) }}
                · {{ schedule.start_time.replace(':', '.') }}–{{
                    schedule.end_time.replace(':', '.')
                }}<template v-if="schedule.school">
                    · {{ schedule.school }}</template
                >
            </p>
        </div>

        <div class="flex max-w-[560px] flex-col gap-4">
            <InlineAlert v-if="schedule.cancelled" variant="warning">
                Sesi ini dibatalkan. Pindai tiket tidak dapat dilakukan.
            </InlineAlert>
            <InlineAlert v-else-if="outsideWindow" variant="warning">
                Pemindaian dibuka {{ windowText }} pada
                {{ formatDateWithDay(schedule.date) }}. Di luar jam itu tiket
                akan ditolak.
            </InlineAlert>

            <p class="flex items-center gap-4 text-base leading-6">
                <span class="text-success"
                    >Sudah
                    <strong class="font-semibold">{{
                        counts.done
                    }}</strong></span
                >
                <span aria-hidden="true">·</span>
                <span
                    >Belum
                    <strong class="font-semibold">{{
                        counts.waiting
                    }}</strong></span
                >
            </p>

            <template v-if="!schedule.cancelled">
                <div
                    class="bg-foreground/90 relative mx-auto aspect-square w-full max-w-[420px] overflow-hidden rounded-lg"
                >
                    <video
                        ref="video"
                        class="size-full object-cover"
                        muted
                        playsinline
                    />
                    <div
                        v-if="state !== 'scanning'"
                        class="bg-card text-muted-foreground absolute inset-0 flex flex-col items-center justify-center gap-2 p-6 text-center text-sm leading-5"
                    >
                        <component
                            :is="cameraError ? CameraOff : Camera"
                            class="size-8"
                            aria-hidden="true"
                        />
                        <p>
                            {{
                                state === 'starting'
                                    ? 'Menyalakan kamera…'
                                    : 'Kamera belum aktif.'
                            }}
                        </p>
                    </div>
                </div>

                <InlineAlert v-if="cameraError">{{ cameraError }}</InlineAlert>

                <Button
                    v-if="state === 'scanning'"
                    type="button"
                    variant="outline"
                    class="min-h-11 w-full text-base"
                    @click="stopCamera"
                >
                    Hentikan
                </Button>
                <Button
                    v-else
                    type="button"
                    class="min-h-11 w-full text-base"
                    :disabled="state === 'starting'"
                    @click="startCamera"
                >
                    <Camera class="size-5" aria-hidden="true" />
                    Mulai memindai
                </Button>
            </template>

            <div aria-live="polite" aria-atomic="true">
                <div
                    v-if="last"
                    class="flex flex-col items-start gap-1 rounded-lg px-4 py-3"
                    :class="tone[last.kind]"
                >
                    <p
                        class="flex items-center gap-2 text-lg leading-7 font-semibold"
                    >
                        <CircleCheck
                            v-if="last.kind === 'ok'"
                            class="size-5 shrink-0"
                            aria-hidden="true"
                        />
                        <CircleX
                            v-else-if="last.kind === 'rejected'"
                            class="size-5 shrink-0"
                            aria-hidden="true"
                        />
                        <TriangleAlert
                            v-else
                            class="size-5 shrink-0"
                            aria-hidden="true"
                        />
                        {{ last.title }}
                    </p>
                    <p v-if="last.nisn" class="text-sm leading-5">
                        NISN {{ last.nisn }}
                    </p>
                    <p class="text-base leading-6">{{ last.message }}</p>
                    <KtpLangsungBadge v-if="last.ktpLangsung" />
                    <p class="text-xs leading-4">
                        Pukul {{ formatTime(last.at) }}
                    </p>
                </div>
            </div>

            <section v-if="history.length" class="flex flex-col gap-2">
                <h2 class="text-base leading-6 font-semibold">
                    Pindaian terakhir
                </h2>
                <ul
                    data-testid="riwayat"
                    class="flex flex-col divide-y rounded-lg border"
                >
                    <li
                        v-for="(item, i) in history"
                        :key="i"
                        class="flex items-start justify-between gap-3 px-3 py-2 text-sm leading-5"
                    >
                        <span class="min-w-0">
                            <span class="font-medium">{{ item.title }}</span>
                            <span class="text-muted-foreground block">{{
                                item.message
                            }}</span>
                        </span>
                        <span class="text-muted-foreground shrink-0 text-xs">{{
                            formatTime(item.at)
                        }}</span>
                    </li>
                </ul>
            </section>

            <Button
                v-if="can(PERMISSIONS.RekamanLihat)"
                as-child
                variant="outline"
                class="min-h-11 w-full"
            >
                <Link :href="route('schedules.recording.show', schedule.id)">
                    <ClipboardCheck class="size-4" aria-hidden="true" />
                    Tandai kehadiran manual
                </Link>
            </Button>
        </div>
    </AppLayout>
</template>
