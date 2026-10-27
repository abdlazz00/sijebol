<script setup lang="ts">
import KtpLangsungBadge from '@/Components/shared/KtpLangsungBadge.vue';
import InfoList from '@/Components/shared/InfoList.vue';
import InlineAlert from '@/Components/shared/InlineAlert.vue';
import PageHeader from '@/Components/shared/PageHeader.vue';
import { Button } from '@/Components/ui/button';
import AppLayout from '@/Layouts/AppLayout.vue';
import { formatDateWithDay, formatTimeRange } from '@/lib/date';
import { downloadDataUrl, renderTicket } from '@/lib/ticketImage';
import type { Ticket } from '@/types/registration';
import { Link } from '@inertiajs/vue3';
import { Download, LoaderCircle } from '@lucide/vue';
import QRCode from 'qrcode';
import { computed, onMounted, ref } from 'vue';

const props = defineProps<{ ticket: Ticket }>();

const qrUrl = ref<string | null>(null);
const qrFailed = ref(false);
const saving = ref(false);
const saveFailed = ref(false);

const day = computed(() => formatDateWithDay(props.ticket.schedule.date));
const time = computed(() =>
    formatTimeRange(
        props.ticket.schedule.start_time,
        props.ticket.schedule.end_time,
    ),
);

// QR dibuat di peramban dari kode acak; kode tidak pernah ditampilkan sebagai teks.
onMounted(async () => {
    if (!props.ticket.code) return;
    try {
        qrUrl.value = await QRCode.toDataURL(props.ticket.code, {
            margin: 2,
            width: 640,
            errorCorrectionLevel: 'M',
            color: { dark: '#000000', light: '#ffffff' },
        });
    } catch {
        qrFailed.value = true;
    }
});

async function save() {
    if (!qrUrl.value) return;
    saving.value = true;
    saveFailed.value = false;
    try {
        const image = await renderTicket(qrUrl.value, {
            studentName: props.ticket.student_name,
            school: props.ticket.school,
            day: day.value,
            time: time.value,
            location: props.ticket.schedule.location,
        });
        downloadDataUrl(
            image,
            `tiket-rekaman-${props.ticket.schedule.date}.png`,
        );
    } catch {
        saveFailed.value = true;
    } finally {
        saving.value = false;
    }
}

const info = computed(() => [
    { label: 'Nama', value: props.ticket.student_name },
    { label: 'Sekolah', value: props.ticket.school },
    { label: 'Hari, tanggal', value: day.value },
    { label: 'Jam', value: time.value },
    { label: 'Lokasi', value: props.ticket.schedule.location },
]);

const crumbs = [
    { label: 'Sesi rekaman', href: route('me.sessions.index') },
    { label: 'Tiket' },
];
</script>

<template>
    <AppLayout title="Tiket rekaman" :breadcrumbs="crumbs">
        <PageHeader
            title="Tiket rekaman"
            :description="
                ticket.code
                    ? 'Tunjukkan QR ini kepada petugas saat sesi.'
                    : 'Tiket untuk sesi yang sudah Anda ikuti.'
            "
        />

        <div class="flex max-w-[480px] flex-col gap-6">
            <InlineAlert
                v-if="ticket.status === 'sudah_rekaman'"
                variant="success"
            >
                Perekaman Anda sudah selesai.
            </InlineAlert>

            <template v-if="ticket.code">
                <!-- Selalu putih dengan modul gelap di tema apa pun agar mudah dipindai. -->
                <div
                    v-if="qrUrl"
                    class="mx-auto w-[min(80vw,320px)] rounded-lg border bg-white p-3"
                >
                    <img
                        :src="qrUrl"
                        :alt="`Kode QR tiket rekaman ${ticket.student_name}`"
                        class="h-auto w-full"
                    />
                </div>
                <InlineAlert v-else-if="qrFailed">
                    QR gagal dibuat. Muat ulang halaman.
                </InlineAlert>
                <p
                    v-if="qrUrl"
                    class="text-muted-foreground text-center text-sm leading-5"
                >
                    Naikkan kecerahan layar saat dipindai.
                </p>
            </template>

            <div
                v-if="ticket.ktp_langsung"
                class="bg-primary-soft text-primary flex flex-col items-start gap-2 rounded-lg px-3 py-3 text-sm leading-5"
            >
                <KtpLangsungBadge />
                <p>
                    KTP-el dapat langsung diterbitkan: Anda sudah lebih dari 17
                    tahun pada tanggal sesi.
                </p>
            </div>

            <section class="bg-card flex flex-col gap-4 rounded-lg border p-6">
                <h2 class="text-base leading-6 font-semibold">Detail sesi</h2>
                <InfoList :items="info" />
            </section>

            <InlineAlert v-if="saveFailed">
                Gambar tiket gagal dibuat. Coba lagi, atau tangkap layar halaman
                ini.
            </InlineAlert>

            <div class="flex flex-col gap-2 sm:flex-row">
                <Button
                    v-if="qrUrl"
                    type="button"
                    class="h-10 px-4"
                    :disabled="saving"
                    @click="save"
                >
                    <LoaderCircle
                        v-if="saving"
                        class="size-4 animate-spin"
                        aria-hidden="true"
                    />
                    <Download v-else class="size-4" aria-hidden="true" />
                    Simpan sebagai gambar
                </Button>
                <Button as-child variant="outline" class="h-10 px-4">
                    <Link :href="route('me.sessions.index')">Kembali ke sesi saya</Link>
                </Button>
            </div>
        </div>
    </AppLayout>
</template>
