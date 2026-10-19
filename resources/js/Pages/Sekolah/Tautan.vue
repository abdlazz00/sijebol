<script setup lang="ts">
import ConfirmDialog from '@/Components/shared/ConfirmDialog.vue';
import CopyField from '@/Components/shared/CopyField.vue';
import EmptyState from '@/Components/shared/EmptyState.vue';
import PageHeader from '@/Components/shared/PageHeader.vue';
import SectionCard from '@/Components/shared/SectionCard.vue';
import { Button } from '@/Components/ui/button';
import { usePermission } from '@/composables/usePermission';
import AppLayout from '@/Layouts/AppLayout.vue';
import { PERMISSIONS } from '@/lib/permissions';
import { whatsappShareUrl } from '@/lib/whatsapp';
import type { SchoolLink } from '@/types/access';
import { router } from '@inertiajs/vue3';
import { MessageCircle, RotateCcw, School } from '@lucide/vue';
import { ref } from 'vue';

defineProps<{ schools: SchoolLink[] }>();

const { can } = usePermission();
const confirmOpen = ref(false);
const target = ref<SchoolLink | null>(null);

function askReset(school: SchoolLink) {
    target.value = school;
    confirmOpen.value = true;
}

function reset() {
    if (target.value)
        router.post(
            route('tautan.reset', target.value.id),
            {},
            { preserveScroll: true },
        );
}

const shareMessage = (school: SchoolLink) =>
    `Halo siswa ${school.name}, buat akun SIJEBOL untuk perekaman KTP-el lewat link ini: ${school.url}`;
</script>

<template>
    <AppLayout title="Link pendaftaran">
        <PageHeader
            title="Link pendaftaran"
            description="Bagikan link sekolah Anda kepada siswa agar mereka dapat membuat akun sendiri."
        />

        <div v-if="schools.length" class="flex flex-col gap-6">
            <SectionCard
                v-for="school in schools"
                :key="school.id"
                :title="school.name"
            >
                <div class="flex flex-col gap-4 px-4 pb-4">
                    <p class="text-muted-foreground text-sm leading-5">
                        {{ school.students_count }} siswa sudah mengisi data
                    </p>
                    <CopyField
                        :value="school.url"
                        :label="`Link pendaftaran ${school.name}`"
                    />
                    <div class="flex flex-wrap items-center gap-2">
                        <Button as-child variant="outline" class="h-10 px-4">
                            <a
                                :href="whatsappShareUrl(shareMessage(school))"
                                target="_blank"
                                rel="noopener"
                            >
                                <MessageCircle
                                    class="size-4"
                                    aria-hidden="true"
                                />
                                Bagikan via WhatsApp
                            </a>
                        </Button>
                        <Button
                            v-if="can(PERMISSIONS.SekolahResetTautan)"
                            type="button"
                            variant="ghost"
                            class="text-danger hover:text-danger h-10 px-4 sm:ml-auto"
                            @click="askReset(school)"
                        >
                            <RotateCcw class="size-4" aria-hidden="true" />
                            Reset link
                        </Button>
                    </div>
                </div>
            </SectionCard>
        </div>

        <EmptyState
            v-else
            :icon="School"
            title="Belum ada sekolah"
            description="Link pendaftaran muncul setelah sekolah ditambahkan."
        />

        <ConfirmDialog
            v-model:open="confirmOpen"
            :title="`Reset link ${target?.name ?? ''}?`"
            description="Link yang sudah dibagikan di grup tidak berlaku lagi; bagikan link baru. Siswa yang sudah mendaftar tidak terpengaruh."
            confirm-label="Reset link"
            destructive
            @confirm="reset"
        />
    </AppLayout>
</template>
