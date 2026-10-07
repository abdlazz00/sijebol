<script setup lang="ts" generic="T extends Record<string, unknown>">
import { Skeleton } from '@/Components/ui/skeleton';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/Components/ui/table';
import EmptyState from './EmptyState.vue';

interface Column {
    key: string;
    label: string;
    class?: string;
}

defineProps<{ columns: Column[]; rows: T[]; loading?: boolean }>();
</script>

<template>
    <div class="bg-card overflow-x-auto rounded-lg border">
        <Table>
            <TableHeader class="bg-secondary">
                <TableRow>
                    <TableHead
                        v-for="column in columns"
                        :key="column.key"
                        :class="[
                            'text-muted-foreground text-xs font-medium',
                            column.class,
                        ]"
                    >
                        {{ column.label }}
                    </TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                <template v-if="loading">
                    <TableRow v-for="n in 5" :key="n" data-skeleton-row>
                        <TableCell v-for="column in columns" :key="column.key">
                            <Skeleton class="h-4 w-full" />
                        </TableCell>
                    </TableRow>
                </template>
                <template v-else-if="rows.length > 0">
                    <TableRow
                        v-for="(row, index) in rows"
                        :key="index"
                        class="hover:bg-primary-soft h-12"
                    >
                        <TableCell
                            v-for="column in columns"
                            :key="column.key"
                            :class="column.class"
                        >
                            <slot :name="`cell-${column.key}`" :row="row">{{
                                row[column.key]
                            }}</slot>
                        </TableCell>
                    </TableRow>
                </template>
                <TableRow v-else>
                    <TableCell :colspan="columns.length">
                        <slot name="empty">
                            <EmptyState title="Tidak ada data" />
                        </slot>
                    </TableCell>
                </TableRow>
            </TableBody>
        </Table>
    </div>
</template>
