<script setup lang="ts">
import DataTable from '@/Components/shared/DataTable.vue';
import EmptyState from '@/Components/shared/EmptyState.vue';
import FormField from '@/Components/shared/FormField.vue';
import PageHeader from '@/Components/shared/PageHeader.vue';
import Pagination from '@/Components/shared/Pagination.vue';
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
import { Input } from '@/Components/ui/input';
import { useDataTable } from '@/composables/useDataTable';
import AppLayout from '@/Layouts/AppLayout.vue';
import { formatDateTime, formatWhen } from '@/lib/date';
import type { Paginator } from '@/types/access';
import type { ActivityChange, ActivityRow } from '@/types/notification';
import { ScrollText, Search } from '@lucide/vue';
import { computed, ref, watch } from 'vue';

const props = defineProps<{
    activities: Paginator<ActivityRow>;
    filters: Record<string, string | undefined>;
    types: { value: string; label: string }[];
    users: { id: number; name: string }[];
}>();

const { filters, loading, apply, reset } = useDataTable('/log', {
    user: props.filters.user ?? '',
    type: props.filters.type ?? '',
    from: props.filters.from ?? '',
    to: props.filters.to ?? '',
});

watch(() => [filters.user, filters.type, filters.from, filters.to], apply);

const hasFilters = computed(() =>
    Object.values(filters).some((v) => v !== '' && v !== null),
);
const userOptions = computed(() =>
    props.users.map((u) => ({ value: u.id, label: u.name })),
);

const columns = [
    { key: 'created_at', label: 'Waktu', class: 'w-[168px]' },
    { key: 'causer', label: 'Pengguna', class: 'w-[176px]' },
    { key: 'action', label: 'Aksi', class: 'w-[200px]' },
    { key: 'subject', label: 'Subjek', class: 'w-[200px]' },
    { key: 'summary', label: 'Ringkasan' },
    { key: 'detail', label: 'Detail', class: 'w-[88px] text-right' },
];

const show = (v: unknown) =>
    v === null || v === undefined || v === ''
        ? '—'
        : typeof v === 'object'
          ? JSON.stringify(v)
          : String(v);

const summary = (changes: ActivityChange[]) =>
    changes
        .slice(0, 2)
        .map((c) => `${c.field}: ${show(c.old)} → ${show(c.new)}`)
        .join('; ') || '—';

const detailOpen = ref(false);
const detail = ref<ActivityRow | null>(null);

function openDetail(row: ActivityRow) {
    detail.value = row;
    detailOpen.value = true;
}
</script>

<template>
    <AppLayout title="Log Aktivitas">
        <PageHeader
            title="Log Aktivitas"
            description="Rekam jejak aktivitas di SIJEBOL."
        />

        <div class="flex flex-col gap-6">
            <div class="grid grid-cols-2 gap-4 lg:flex lg:items-start">
                <FormField
                    v-slot="{ id }"
                    label="Pengguna"
                    class="col-span-2 lg:w-[240px]"
                >
                    <SelectField
                        :id="id"
                        v-model="filters.user"
                        :options="userOptions"
                        all-label="Semua pengguna"
                    />
                </FormField>
                <FormField
                    v-slot="{ id }"
                    label="Jenis"
                    class="col-span-2 lg:w-[180px]"
                >
                    <SelectField
                        :id="id"
                        v-model="filters.type"
                        :options="types"
                        all-label="Semua jenis"
                    />
                </FormField>
                <FormField
                    v-slot="{ id }"
                    label="Dari tanggal"
                    class="lg:w-[180px]"
                >
                    <Input
                        :id="id"
                        :model-value="String(filters.from ?? '')"
                        @update:model-value="filters.from = String($event)"
                        type="date"
                        class="h-10"
                    />
                </FormField>
                <FormField
                    v-slot="{ id }"
                    label="Sampai tanggal"
                    class="lg:w-[180px]"
                >
                    <Input
                        :id="id"
                        :model-value="String(filters.to ?? '')"
                        @update:model-value="filters.to = String($event)"
                        type="date"
                        class="h-10"
                    />
                </FormField>
                <Button
                    v-if="hasFilters"
                    type="button"
                    variant="ghost"
                    class="text-primary col-span-2 h-10 self-end px-3 lg:col-span-1"
                    @click="reset"
                >
                    Reset filter
                </Button>
            </div>

            <div class="flex flex-col gap-4">
                <p
                    class="text-muted-foreground flex items-center gap-2 text-xs leading-4 md:hidden"
                >
                    <span aria-hidden="true">↔</span> Geser tabel untuk melihat
                    kolom lainnya
                </p>
                <DataTable
                    :columns="columns"
                    :rows="activities.data"
                    :loading="loading"
                    row-class="h-16"
                    table-class="min-w-[960px]"
                >
                    <template #cell-created_at="{ row }">
                        <span
                            class="text-muted-foreground text-sm"
                            :title="formatDateTime(row.created_at)"
                            >{{ formatWhen(row.created_at) }}</span
                        >
                    </template>
                    <template #cell-causer="{ row }">
                        {{ row.causer?.name ?? 'Sistem' }}
                    </template>
                    <template #cell-action="{ row }">
                        <span class="font-medium">{{ row.action }}</span>
                    </template>
                    <template #cell-subject="{ row }">
                        <div class="min-w-0">
                            <p class="text-sm leading-5">
                                {{ row.subject.type }}
                            </p>
                            <p
                                class="text-muted-foreground truncate text-xs leading-4"
                            >
                                {{ row.subject.label }}
                            </p>
                        </div>
                    </template>
                    <template #cell-summary="{ row }">
                        <span class="block max-w-[320px] truncate text-sm">{{
                            summary(row.changes)
                        }}</span>
                    </template>
                    <template #cell-detail="{ row }">
                        <div class="flex justify-end">
                            <Button
                                type="button"
                                variant="ghost"
                                class="text-primary h-10 min-w-10 px-2"
                                :aria-label="`Detail ${row.action}`"
                                @click="openDetail(row)"
                            >
                                Detail
                            </Button>
                        </div>
                    </template>
                    <template #empty>
                        <EmptyState
                            v-if="hasFilters"
                            :icon="Search"
                            title="Tidak ada hasil"
                            description="Tidak ada aktivitas yang sesuai dengan filter Anda."
                        >
                            <template #action>
                                <Button
                                    variant="outline"
                                    class="h-9"
                                    @click="reset"
                                    >Reset filter</Button
                                >
                            </template>
                        </EmptyState>
                        <EmptyState
                            v-else
                            :icon="ScrollText"
                            title="Belum ada aktivitas"
                            description="Aktivitas pengguna akan tercatat di sini."
                        />
                    </template>
                </DataTable>
                <Pagination :paginator="activities" />
            </div>
        </div>

        <Dialog v-model:open="detailOpen">
            <DialogContent class="rounded-xl sm:max-w-[560px]">
                <DialogHeader v-if="detail">
                    <DialogTitle>{{ detail.action }}</DialogTitle>
                    <DialogDescription>
                        {{ detail.causer?.name ?? 'Sistem' }} ·
                        {{ formatDateTime(detail.created_at) }}
                    </DialogDescription>
                </DialogHeader>
                <div v-if="detail" class="flex flex-col gap-3 text-sm">
                    <p>
                        <span class="text-muted-foreground">Subjek: </span>
                        {{ detail.subject.type }} · {{ detail.subject.label }}
                    </p>
                    <div
                        v-if="detail.changes.length > 0"
                        class="max-h-[320px] overflow-auto rounded-lg border"
                    >
                        <table class="w-full text-left text-sm">
                            <thead
                                class="bg-secondary text-muted-foreground text-xs"
                            >
                                <tr>
                                    <th
                                        scope="col"
                                        class="px-3 py-2 font-medium"
                                    >
                                        Kolom
                                    </th>
                                    <th
                                        scope="col"
                                        class="px-3 py-2 font-medium"
                                    >
                                        Sebelum
                                    </th>
                                    <th
                                        scope="col"
                                        class="px-3 py-2 font-medium"
                                    >
                                        Sesudah
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="c in detail.changes"
                                    :key="c.field"
                                    class="border-t align-top"
                                >
                                    <th
                                        scope="row"
                                        class="px-3 py-2 font-medium"
                                    >
                                        {{ c.field }}
                                    </th>
                                    <td
                                        class="text-muted-foreground px-3 py-2 break-all"
                                    >
                                        {{ show(c.old) }}
                                    </td>
                                    <td class="px-3 py-2 break-all">
                                        {{ show(c.new) }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <p v-else class="text-muted-foreground">
                        Tidak ada perubahan data.
                    </p>
                </div>
                <DialogFooter>
                    <Button variant="outline" @click="detailOpen = false"
                        >Tutup</Button
                    >
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
