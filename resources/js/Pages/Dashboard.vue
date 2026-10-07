<script setup lang="ts">
import EmptyState from '@/Components/shared/EmptyState.vue';
import PageHeader from '@/Components/shared/PageHeader.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
</script>

<template>
    <AppLayout title="Beranda">
        <PageHeader
            title="Beranda"
            description="Ringkasan akan tampil di sini."
        />
        <div class="bg-card rounded-lg border">
            <EmptyState
                title="Belum ada data"
                description="Blok beranda dibangun bersama modul terkait."
            />
        </div>
    </AppLayout>
</template>
