<script setup lang="ts">
import { Avatar, AvatarFallback } from '@/Components/ui/avatar';
import { Button } from '@/Components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/Components/ui/dropdown-menu';
import { initials } from '@/lib/format';
import type { PageProps } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import { Menu, PanelLeft } from '@lucide/vue';
import { computed } from 'vue';

defineEmits<{ toggleSidebar: []; openDrawer: [] }>();

const page = usePage<PageProps>();
const user = computed(() => page.props.auth.user);
</script>

<template>
    <header
        class="bg-card flex h-16 items-center justify-between border-b px-4"
    >
        <div class="flex items-center gap-2">
            <Button
                variant="ghost"
                size="icon"
                class="lg:hidden"
                aria-label="Buka menu"
                @click="$emit('openDrawer')"
            >
                <Menu class="size-5" />
            </Button>
            <Button
                variant="ghost"
                size="icon"
                class="hidden lg:inline-flex"
                aria-label="Lipat sidebar"
                @click="$emit('toggleSidebar')"
            >
                <PanelLeft class="size-5" />
            </Button>
            <div class="text-muted-foreground text-xs">
                <slot name="breadcrumb" />
            </div>
        </div>
        <DropdownMenu v-if="user">
            <DropdownMenuTrigger as-child>
                <Button variant="ghost" class="h-10 gap-2 px-2">
                    <Avatar class="size-8">
                        <AvatarFallback class="bg-primary-soft text-primary">
                            {{ initials(user.name) }}
                        </AvatarFallback>
                    </Avatar>
                    <span class="hidden text-sm font-medium sm:inline">{{
                        user.name
                    }}</span>
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end">
                <DropdownMenuItem as-child>
                    <Link :href="route('profile.edit')">Profil</Link>
                </DropdownMenuItem>
                <DropdownMenuItem as-child>
                    <Link
                        :href="route('logout')"
                        method="post"
                        as="button"
                        class="w-full"
                    >
                        Keluar
                    </Link>
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>
    </header>
</template>
