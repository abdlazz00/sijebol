<script setup lang="ts">
import CopyField from '@/Components/shared/CopyField.vue';
import StatusBadge from '@/Components/shared/StatusBadge.vue';
import { Button } from '@/Components/ui/button';
import AppLayout from '@/Layouts/AppLayout.vue';
import { humanize } from '@/lib/format';
import { whatsappUrl } from '@/lib/whatsapp';
import { Link } from '@inertiajs/vue3';
import { Check, Info, MessageCircle } from '@lucide/vue';
import { computed } from 'vue';

const props = defineProps<{
    user: {
        id: number;
        name: string;
        phone: string | null;
        role: string | null;
        school: string | null;
    };
    link: string;
    mode: 'dibuat' | 'ulang';
}>();

const resend = computed(() => props.mode === 'ulang');
const whatsapp = computed(() =>
    whatsappUrl(props.user.phone, props.link, props.user.name),
);
const subtitle = computed(() =>
    [props.user.role ? humanize(props.user.role) : null, props.user.school]
        .filter(Boolean)
        .join(' · '),
);
const pageTitle = computed(() =>
    resend.value ? 'Kirim ulang aktivasi' : 'Tambah pengguna',
);
const crumbs = computed(() => [
    { label: 'Pengguna', href: route('users.index') },
    { label: pageTitle.value },
    { label: 'Berhasil' },
]);
</script>

<template>
    <AppLayout :title="pageTitle" :breadcrumbs="crumbs">
        <div class="mb-6 flex flex-col gap-1">
            <h1 class="text-2xl leading-8 font-semibold">{{ pageTitle }}</h1>
            <p class="text-muted-foreground text-sm leading-5">
                {{
                    resend
                        ? 'Bagikan link baru agar pengguna dapat mengaktifkan akun.'
                        : 'Bagikan link agar pengguna dapat mengaktifkan akun.'
                }}
            </p>
        </div>

        <div class="max-w-[720px]">
            <div class="bg-card flex flex-col gap-6 rounded-lg border p-6">
                <div class="flex flex-col gap-4">
                    <div class="flex gap-4">
                        <div
                            class="bg-success-soft text-success flex size-10 shrink-0 items-center justify-center rounded-full"
                        >
                            <Check class="size-6" aria-hidden="true" />
                        </div>
                        <div class="flex min-w-0 flex-col gap-1">
                            <h2 class="text-2xl leading-8 font-semibold">
                                {{
                                    resend
                                        ? 'Link aktivasi baru dibuat'
                                        : `Akun dibuat untuk ${user.name}`
                                }}
                            </h2>
                            <p v-if="resend" class="text-sm font-semibold">
                                {{ user.name }}
                            </p>
                            <p
                                v-if="subtitle"
                                class="text-muted-foreground text-sm leading-5"
                            >
                                {{ subtitle }}
                            </p>
                        </div>
                    </div>
                    <div><StatusBadge status="menunggu" /></div>
                    <p class="text-muted-foreground text-sm leading-5">
                        {{
                            resend
                                ? 'Link sebelumnya tidak berlaku lagi.'
                                : 'Pengguna perlu membuka link aktivasi dan membuat kata sandi sebelum dapat masuk.'
                        }}
                    </p>
                </div>

                <hr />

                <div class="flex flex-col gap-4">
                    <div class="flex flex-col gap-2">
                        <p class="text-sm leading-5 font-medium">
                            Link aktivasi
                        </p>
                        <CopyField :value="link" label="Link aktivasi" />
                    </div>

                    <p
                        class="bg-secondary text-muted-foreground flex items-start gap-2 rounded-lg p-3 text-xs leading-4"
                    >
                        <Info class="size-4 shrink-0" aria-hidden="true" />
                        Berlaku 3 hari. Link tidak dapat ditampilkan lagi
                        setelah halaman ini ditutup.
                    </p>

                    <div class="flex flex-col items-start gap-2">
                        <p
                            v-if="resend"
                            class="text-muted-foreground text-xs leading-4"
                        >
                            Link hanya dapat digunakan sekali.
                        </p>
                        <Button
                            v-if="whatsapp"
                            as-child
                            variant="outline"
                            class="h-10 px-4"
                        >
                            <a
                                :href="whatsapp"
                                target="_blank"
                                rel="noopener noreferrer"
                            >
                                <MessageCircle
                                    class="size-4"
                                    aria-hidden="true"
                                />
                                Kirim via WhatsApp
                            </a>
                        </Button>
                        <Button
                            v-else
                            variant="outline"
                            class="h-10 px-4"
                            disabled
                        >
                            <MessageCircle class="size-4" aria-hidden="true" />
                            Kirim via WhatsApp
                        </Button>
                        <p
                            v-if="!whatsapp"
                            class="text-muted-foreground text-xs leading-4"
                        >
                            {{
                                user.phone
                                    ? 'Nomor HP tidak dapat dipakai untuk WhatsApp.'
                                    : 'Nomor HP belum diisi.'
                            }}
                        </p>
                        <p class="text-muted-foreground text-xs leading-4">
                            Membuka chat dengan pesan berisi link. Kirim pesan
                            secara manual.
                        </p>
                    </div>
                </div>
            </div>

            <div class="mt-6 flex justify-end">
                <Button as-child variant="outline" class="h-10 px-4">
                    <Link :href="route('users.index')"
                        >Kembali ke daftar</Link
                    >
                </Button>
            </div>
        </div>
    </AppLayout>
</template>
