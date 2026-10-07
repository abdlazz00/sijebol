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
import { cn } from '@/lib/utils';
import EmptyState from './EmptyState.vue';

interface Column {
    key: string;
    label: string;
    class?: string;
}

withDefaults(
    defineProps<{
        columns: Column[];
        rows: T[];
        loading?: boolean;
        rowClass?: string;
        tableClass?: string;
    }>(),
    { rowClass: 'h-12', tableClass: '' },
);
</script>

<template>
    <div class="bg-card overflow-x-auto rounded-lg border">
        <Table :class="tableClass">
            <TableHeader class="bg-secondary">
                <TableRow>
                    <TableHead
                        v-for="column in columns"
                        :key="column.key"
                        :class="
                            cn(
                                'text-muted-foreground h-10 px-4 text-xs font-medium',
                                column.class,
                            )
                        "
                    >
                        {{ column.label }}
                    </TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                <template v-if="loading">
                    <TableRow
                        v-for="n in 8"
                        :key="n"
                        data-skeleton-row
                        :class="rowClass"
                    >
                        <TableCell
                            v-for="column in columns"
                            :key="column.key"
                            class="px-4"
                        >
                            <Skeleton class="h-4 w-full" />
                        </TableCell>
                    </TableRow>
                </template>
                <template v-else-if="rows.length > 0">
                    <TableRow
                        v-for="(row, index) in rows"
                        :key="index"
                        :class="cn('hover:bg-primary-soft', rowClass)"
                    >
                        <TableCell
                            v-for="column in columns"
                            :key="column.key"
                            :class="cn('px-4', column.class)"
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
