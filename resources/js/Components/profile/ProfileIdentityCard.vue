<script setup lang="ts">
import { Avatar, AvatarFallback } from '@/Components/ui/avatar';
import { formatDate, formatDateTime } from '@/lib/date';
import { humanize, initials } from '@/lib/format';
import type { ProfileIdentity } from '@/types/profile';
import { Link } from '@inertiajs/vue3';
import { ChevronDown } from '@lucide/vue';
import { computed, ref, useId } from 'vue';

const props = defineProps<{
    name: string;
    role: string | null;
    identity: ProfileIdentity;
}>();

const nameId = useId();
const detailId = useId();
// Di bawah xl rincian dilipat; di xl selalu tampil (kelas `xl:block`).
const open = ref(false);

const lastLoginText = computed(() =>
    formatDateTime(props.identity.last_login_at),
);

// belowXlHidden: di bawah xl baris ini sudah tampil di header ringkas, jadi tidak diulang di rincian.
const rows = computed<
    { label: string; value: string; belowXlHidden?: boolean }[]
>(() => {
    const { student, school, access, created_at } = props.identity;
    const lastLogin = {
        label: 'Login terakhir',
        value: lastLoginText.value,
        belowXlHidden: true,
    };

    if (student) {
        return [
            { label: 'NIK', value: student.nik },
            { label: 'NISN', value: student.nisn },
            {
                label: 'Tanggal lahir',
                value: `${formatDate(student.birth_date)} · ${student.age} tahun`,
            },
            {
                label: 'Jenis kelamin',
                value: student.gender === 'L' ? 'Laki-laki' : 'Perempuan',
            },
            lastLogin,
        ];
    }

    const created = { label: 'Akun dibuat', value: formatDate(created_at) };
    if (school) {
        return [
            { label: 'Sekolah', value: school },
            ...(access ? [{ label: 'Akses', value: access }] : []),
            created,
            lastLogin,
        ];
    }

    return [
        { label: 'Wilayah', value: 'Seluruh sekolah di Kec. Sungai Beduk' },
        created,
        lastLogin,
    ];
});
</script>

<template>
    <section
        :aria-labelledby="nameId"
        class="bg-card rounded-lg border p-4 xl:sticky xl:top-24 xl:p-6"
    >
        <div
            class="flex items-center gap-3 xl:flex-col xl:items-start xl:gap-0"
        >
            <Avatar class="size-12 xl:size-16">
                <AvatarFallback
                    class="bg-primary-soft text-primary text-base font-semibold xl:text-xl"
                >
                    {{ initials(name) }}
                </AvatarFallback>
            </Avatar>
            <div class="flex min-w-0 flex-col items-start gap-1 xl:mt-3">
                <h2
                    :id="nameId"
                    class="text-lg leading-6 font-semibold break-words"
                >
                    {{ name }}
                </h2>
                <span
                    class="bg-primary-soft text-primary inline-flex h-6 items-center gap-1.5 rounded-full px-2.5 text-xs font-medium"
                >
                    <span
                        class="size-1.5 rounded-full bg-current"
                        aria-hidden="true"
                    />
                    {{ humanize(role) }}
                </span>
                <p
                    v-if="identity.school"
                    class="text-muted-foreground text-xs leading-4"
                >
                    {{ identity.school }}
                </p>
                <p class="text-muted-foreground text-xs leading-4 xl:hidden">
                    Login terakhir {{ lastLoginText }}
                </p>
            </div>
        </div>

        <button
            type="button"
            class="text-foreground mt-4 flex min-h-10 w-full items-center justify-between border-t pt-3 text-sm font-medium xl:hidden"
            :aria-expanded="open"
            :aria-controls="detailId"
            @click="open = !open"
        >
            Data diri
            <ChevronDown
                class="text-muted-foreground size-4 transition-transform"
                :class="{ 'rotate-180': open }"
                aria-hidden="true"
            />
        </button>

        <div
            :id="detailId"
            :class="open ? 'block' : 'hidden xl:block'"
            class="mt-2 xl:mt-4 xl:border-t xl:pt-4"
        >
            <dl
                class="grid grid-cols-[96px_1fr] gap-x-3 gap-y-2.5 text-sm leading-5"
            >
                <div
                    v-for="row in rows"
                    :key="row.label"
                    :class="
                        row.belowXlHidden ? 'hidden xl:contents' : 'contents'
                    "
                >
                    <dt class="text-muted-foreground text-xs leading-5">
                        {{ row.label }}
                    </dt>
                    <dd class="min-w-0 break-words tabular-nums">
                        {{ row.value }}
                    </dd>
                </div>
            </dl>
            <p
                v-if="identity.can_open_student_data"
                class="bg-secondary text-muted-foreground mt-4 rounded-lg border p-3 text-xs leading-4"
            >
                Data siswa tidak diubah di sini. Data terkunci saat pendaftaran
                sesi sedang diperiksa.
            </p>
        </div>

        <Link
            v-if="identity.can_open_student_data"
            :href="route('me.show')"
            class="text-primary mt-4 inline-flex min-h-6 items-center gap-1.5 text-sm font-medium hover:underline"
        >
            Lihat dan lengkapi data saya →
        </Link>
    </section>
</template>
