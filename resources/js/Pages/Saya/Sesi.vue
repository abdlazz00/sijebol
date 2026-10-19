<script setup lang="ts">
import ConfirmDialog from '@/Components/shared/ConfirmDialog.vue';
import EmptyState from '@/Components/shared/EmptyState.vue';
import InlineAlert from '@/Components/shared/InlineAlert.vue';
import PageHeader from '@/Components/shared/PageHeader.vue';
import StatusBadge from '@/Components/shared/StatusBadge.vue';
import { Button } from '@/Components/ui/button';
import { useFlash } from '@/composables/useFlash';
import AppLayout from '@/Layouts/AppLayout.vue';
import {
    formatDateTime,
    formatDateWithDay,
    formatMonth,
    formatTimeRange,
    relativeTime,
} from '@/lib/date';
import type {
    OpenSchedule,
    RegistrationStatus,
    SelfEntry,
} from '@/types/registration';
import { Link, router } from '@inertiajs/vue3';
import { CalendarDays, LoaderCircle } from '@lucide/vue';
import { computed, nextTick, ref } from 'vue';

const props = defineProps<{
    ready: boolean;
    close_days: number;
    entry: SelfEntry | null;
    schedules: OpenSchedule[];
}>();

const { flash } = useFlash();

const ACTIVE: RegistrationStatus[] = [
    'menunggu_verifikasi',
    'perlu_perbaikan',
    'terdaftar',
    'sudah_rekaman',
];
const entryActive = computed(
    () => props.entry !== null && ACTIVE.includes(props.entry.status),
);
const showPicker = computed(() => props.ready && !entryActive.value);

// Tahap ditulis sebagai teks; penanda aktifnya juga tidak hanya warna (aria-current + tebal).
const stage = computed(() => {
    const status = props.entry?.status;
    if (status === 'menunggu_verifikasi' || status === 'perlu_perbaikan')
        return 0;
    if (status === 'terdaftar') return 1;
    if (status === 'sudah_rekaman') return 2;
    return null;
});
const steps = computed(() => [
    props.entry?.status === 'perlu_perbaikan'
        ? 'Perlu perbaikan'
        : 'Menunggu verifikasi',
    'Terdaftar',
    'Rekaman',
]);

const NEXT: Record<RegistrationStatus, string> = {
    menunggu_verifikasi:
        'Menunggu pemeriksaan petugas; data dan dokumen Anda terkunci sementara.',
    perlu_perbaikan:
        'Perbaiki data atau dokumen sesuai catatan petugas, lalu ajukan ulang sebelum batas waktu.',
    terdaftar: 'Anda terdaftar. Datang sesuai jadwal.',
    sudah_rekaman: 'Perekaman Anda sudah selesai.',
    tidak_hadir:
        'Anda tercatat tidak hadir. Anda dapat mendaftar ke sesi lain.',
    belum_rekaman:
        'Anda hadir tetapi belum sempat direkam. Anda dapat mendaftar ke sesi lain.',
    ditolak:
        'Pendaftaran ditolak. Baca catatan petugas, lalu pilih sesi lain bila perlu.',
    dibatalkan: 'Pendaftaran dibatalkan. Anda dapat memilih sesi lain.',
    kedaluwarsa: 'Pendaftaran kedaluwarsa. Anda dapat memilih sesi lain.',
};
const NOTED: RegistrationStatus[] = [
    'perlu_perbaikan',
    'ditolak',
    'dibatalkan',
];
const showNote = computed(
    () =>
        props.entry !== null &&
        NOTED.includes(props.entry.status) &&
        !!props.entry.note,
);
const CANCELLABLE: RegistrationStatus[] = [
    'menunggu_verifikasi',
    'perlu_perbaikan',
    'terdaftar',
];
// Tenggat lewat (atau sesi sudah hari-H): tombol ajukan ulang hilang, jadi alasannya harus tertulis.
const deadlinePassed = computed(
    () =>
        props.entry?.status === 'perlu_perbaikan' && !props.entry.can_resubmit,
);
const canViewTicket = computed(
    () =>
        props.entry?.status === 'terdaftar' ||
        props.entry?.status === 'sudah_rekaman',
);
const cancelHint = computed(
    () =>
        props.entry !== null &&
        CANCELLABLE.includes(props.entry.status) &&
        !props.entry.can_cancel,
);

// Alasan yang sama di semua sesi (mis. dokumen belum diunggah) cukup tampil sekali.
const commonReasons = computed(() => {
    const [first, ...rest] = props.schedules.map((s) => s.reasons);
    if (!first) return [];

    return first.filter((r) => rest.every((reasons) => reasons.includes(r)));
});
const specificReasons = (s: OpenSchedule) =>
    s.reasons.filter((r) => !commonReasons.value.includes(r));

// Banyak sesi dikelompokkan per bulan agar mudah dipindai.
const groups = computed(() => {
    if (props.schedules.length <= 4)
        return [
            {
                key: 'semua',
                heading: null as string | null,
                items: props.schedules,
            },
        ];

    const byMonth = new Map<string, OpenSchedule[]>();
    for (const s of props.schedules) {
        const key = s.date.slice(0, 7);
        byMonth.set(key, [...(byMonth.get(key) ?? []), s]);
    }

    return [...byMonth.entries()].map(([key, items]) => ({
        key,
        heading: formatMonth(items[0].date) as string | null,
        items,
    }));
});

const busy = ref(false);
// Sesi yang sedang dikirim, agar hanya tombolnya yang menampilkan spinner (dialog menutup dan mengosongkan pilihan lebih dulu).
const sendingId = ref<number | null>(null);
const entryHeading = ref<HTMLElement | null>(null);

function send(name: string, id: number) {
    router.post(
        route(name, id),
        {},
        {
            preserveScroll: true,
            onStart: () => (busy.value = true),
            onFinish: () => {
                busy.value = false;
                sendingId.value = null;
            },
            // Hasil langsung terbaca: fokus pindah ke judul kartu pendaftaran.
            onSuccess: () => void nextTick(() => entryHeading.value?.focus()),
        },
    );
}

const picked = ref<OpenSchedule | null>(null);
const registerOpen = computed({
    get: () => picked.value !== null,
    set: (open: boolean) => {
        if (!open) picked.value = null;
    },
});
const cancelOpen = ref(false);
const resubmitOpen = ref(false);

function register() {
    if (!picked.value) return;
    sendingId.value = picked.value.id;
    send('saya.sesi.daftar', picked.value.id);
}
</script>

<template>
    <AppLayout title="Sesi rekaman">
        <PageHeader
            title="Sesi rekaman"
            description="Pilih sesi perekaman KTP-el untuk sekolah Anda."
        />

        <div class="flex max-w-[720px] flex-col gap-6">
            <InlineAlert v-if="flash.error">{{ flash.error }}</InlineAlert>

            <InlineAlert v-if="!ready" variant="warning">
                Lengkapi data diri dan dokumen dulu sebelum memilih sesi.
            </InlineAlert>
            <Button v-if="!ready" as-child class="h-10 self-start px-4">
                <Link :href="route('saya.show')">Lengkapi data saya</Link>
            </Button>

            <section
                v-if="entry"
                aria-labelledby="pendaftaran-saya"
                class="bg-card flex flex-col gap-4 rounded-lg border p-6"
            >
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="flex min-w-0 flex-col gap-1">
                        <p class="text-muted-foreground text-xs leading-4">
                            Pendaftaran saya
                        </p>
                        <h2
                            id="pendaftaran-saya"
                            ref="entryHeading"
                            tabindex="-1"
                            class="text-lg leading-7 font-semibold focus:outline-none"
                        >
                            {{ formatDateWithDay(entry.schedule.date) }} ·
                            {{
                                formatTimeRange(
                                    entry.schedule.start_time,
                                    entry.schedule.end_time,
                                )
                            }}
                        </h2>
                        <p class="text-muted-foreground text-sm leading-5">
                            {{ entry.schedule.location }}
                        </p>
                    </div>
                    <StatusBadge :status="entry.status" kind="registration" />
                </div>

                <ol
                    v-if="stage !== null"
                    aria-label="Tahap pendaftaran"
                    class="flex flex-wrap gap-x-4 gap-y-1 text-sm leading-5"
                >
                    <li
                        v-for="(label, i) in steps"
                        :key="label"
                        :aria-current="i === stage ? 'step' : undefined"
                        :class="
                            i === stage
                                ? 'font-semibold'
                                : 'text-muted-foreground'
                        "
                    >
                        <span v-if="i < stage" aria-hidden="true">✓ </span
                        >{{ label
                        }}<span v-if="i < stage" class="sr-only">
                            (selesai)</span
                        >
                    </li>
                </ol>

                <InlineAlert v-if="showNote" variant="warning">
                    <span class="font-medium">Catatan petugas:</span>
                    {{ entry.note }}
                </InlineAlert>

                <p class="text-sm leading-5">
                    <span class="font-medium">Langkah berikutnya:</span>
                    {{ NEXT[entry.status] }}
                </p>

                <p
                    v-if="
                        entry.status === 'perlu_perbaikan' &&
                        entry.fix_deadline &&
                        !deadlinePassed
                    "
                    class="text-sm leading-5"
                >
                    <span class="font-medium">Batas perbaikan:</span>
                    {{ formatDateTime(entry.fix_deadline) }}
                    ({{ relativeTime(entry.fix_deadline) }})
                </p>
                <p v-if="deadlinePassed" class="text-warning text-sm leading-5">
                    Batas perbaikan sudah lewat; kursi akan dilepas otomatis.
                </p>

                <Button
                    v-if="canViewTicket"
                    as-child
                    class="h-10 self-start px-4"
                >
                    <Link :href="route('saya.tiket', entry.id)"
                        >Lihat tiket</Link
                    >
                </Button>

                <div
                    v-if="entry.can_resubmit || entry.can_cancel"
                    class="flex flex-col gap-2 sm:flex-row"
                >
                    <Button
                        v-if="entry.status === 'perlu_perbaikan'"
                        as-child
                        variant="outline"
                        class="h-10 px-4"
                    >
                        <Link :href="route('saya.show')">Perbaiki data</Link>
                    </Button>
                    <Button
                        v-if="entry.can_resubmit"
                        type="button"
                        class="h-10 px-4"
                        :disabled="busy"
                        @click="resubmitOpen = true"
                    >
                        Ajukan ulang
                    </Button>
                    <Button
                        v-if="entry.can_cancel"
                        type="button"
                        variant="outline"
                        class="text-danger h-10 px-4 sm:ml-auto"
                        :disabled="busy"
                        @click="cancelOpen = true"
                    >
                        Batalkan pendaftaran
                    </Button>
                </div>
                <p
                    v-if="cancelHint"
                    class="text-muted-foreground text-xs leading-4"
                >
                    Pembatalan hanya sampai sehari sebelum sesi.
                </p>
            </section>

            <section v-if="showPicker" class="flex flex-col gap-4">
                <div class="flex flex-col gap-1">
                    <h2 class="text-base leading-6 font-semibold">
                        Pilih sesi
                    </h2>
                    <p class="text-muted-foreground text-xs leading-4">
                        Pendaftaran ditutup {{ close_days }} hari sebelum sesi.
                    </p>
                </div>

                <InlineAlert v-if="commonReasons.length" variant="warning">
                    Sebelum bisa mendaftar:
                    <ul class="mt-1 list-disc pl-4">
                        <li v-for="reason in commonReasons" :key="reason">
                            {{ reason }}
                        </li>
                    </ul>
                    <Link
                        :href="route('saya.show')"
                        class="mt-2 inline-block font-medium underline"
                        >Lengkapi data saya</Link
                    >
                </InlineAlert>

                <div v-if="schedules.length" class="flex flex-col gap-6">
                    <div
                        v-for="group in groups"
                        :key="group.key"
                        class="flex flex-col gap-3"
                    >
                        <h3
                            v-if="group.heading"
                            class="text-sm leading-5 font-semibold"
                        >
                            {{ group.heading }}
                        </h3>
                        <ul class="flex flex-col gap-3">
                            <li
                                v-for="s in group.items"
                                :key="s.id"
                                class="bg-card flex flex-col gap-3 rounded-lg border p-4 sm:flex-row sm:items-center sm:justify-between"
                            >
                                <div class="flex min-w-0 flex-col gap-1">
                                    <p class="text-sm leading-5 font-semibold">
                                        {{ formatDateWithDay(s.date) }}
                                    </p>
                                    <p
                                        class="text-muted-foreground text-sm leading-5"
                                    >
                                        {{
                                            formatTimeRange(
                                                s.start_time,
                                                s.end_time,
                                            )
                                        }}
                                        · {{ s.location }}
                                    </p>
                                    <p
                                        class="text-xs leading-4"
                                        :class="
                                            s.full
                                                ? 'text-danger font-medium'
                                                : 'text-muted-foreground'
                                        "
                                    >
                                        {{
                                            s.full
                                                ? 'Penuh'
                                                : `Sisa ${s.quota - s.taken} dari ${s.quota} kursi`
                                        }}
                                    </p>
                                    <ul
                                        v-if="specificReasons(s).length"
                                        class="text-warning list-disc pl-4 text-xs leading-4"
                                    >
                                        <li
                                            v-for="reason in specificReasons(s)"
                                            :key="reason"
                                        >
                                            {{ reason }}
                                        </li>
                                    </ul>
                                </div>
                                <Button
                                    type="button"
                                    class="h-10 px-4"
                                    :disabled="s.full || !s.eligible || busy"
                                    @click="picked = s"
                                >
                                    <LoaderCircle
                                        v-if="busy && sendingId === s.id"
                                        class="size-4 animate-spin"
                                        aria-hidden="true"
                                    />
                                    Daftar<span class="sr-only">
                                        sesi
                                        {{ formatDateWithDay(s.date) }}</span
                                    >
                                </Button>
                            </li>
                        </ul>
                    </div>
                </div>
                <EmptyState
                    v-else-if="ready"
                    :icon="CalendarDays"
                    title="Belum ada sesi yang dibuka untuk sekolah Anda"
                    description="Sesi rekaman dibuat oleh admin kecamatan. Cek halaman ini lagi nanti."
                />
            </section>
        </div>

        <ConfirmDialog
            v-model:open="registerOpen"
            :title="`Daftar sesi ${picked ? formatDateWithDay(picked.date) : ''}?`"
            :description="
                picked
                    ? `${formatDateWithDay(picked.date)}, ${formatTimeRange(picked.start_time, picked.end_time)} di ${picked.location}. Data dan dokumen Anda terkunci selama diperiksa petugas. Bila ada yang salah, batalkan pendaftaran untuk mengubah data, atau tunggu petugas meminta perbaikan.`
                    : ''
            "
            confirm-label="Daftar"
            @confirm="register"
        />

        <ConfirmDialog
            v-model:open="cancelOpen"
            title="Batalkan pendaftaran?"
            description="Kursi Anda dilepas. Anda dapat mendaftar ke sesi lain selama pendaftarannya masih dibuka."
            confirm-label="Batalkan pendaftaran"
            destructive
            @confirm="entry && send('saya.pendaftaran.batal', entry.id)"
        />

        <ConfirmDialog
            v-model:open="resubmitOpen"
            title="Ajukan ulang pendaftaran?"
            description="Petugas akan memeriksa data dan dokumen Anda kembali. Data Anda terkunci lagi selama diperiksa."
            confirm-label="Ajukan ulang"
            @confirm="entry && send('saya.pendaftaran.ajukan-ulang', entry.id)"
        />
    </AppLayout>
</template>
