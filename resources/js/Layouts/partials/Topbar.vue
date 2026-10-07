<script setup lang="ts">
import Breadcrumbs, { type Crumb } from '@/Components/shared/Breadcrumbs.vue';
import { Avatar, AvatarFallback } from '@/Components/ui/avatar';
import { Button } from '@/Components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/Components/ui/dropdown-menu';
import { humanize, initials } from '@/lib/format';
import type { PageProps } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import {
    Bell,
    ChevronDown,
    LogOut,
    Menu,
    PanelLeftClose,
    UserRound,
} from '@lucide/vue';
import { computed } from 'vue';

defineProps<{ breadcrumbs: Crumb[] }>();
defineEmits<{ toggleSidebar: []; openDrawer: [] }>();

const page = usePage<PageProps>();
const user = computed(() => page.props.auth.user);
</script>

<template>
    <header
        class="bg-card flex h-16 items-center justify-between border-b px-4 md:px-6"
    >
        <div class="flex min-w-0 items-center gap-2 md:gap-4">
            <Button
                variant="ghost"
                size="icon"
                class="text-muted-foreground size-10 lg:hidden"
                aria-label="Buka menu"
                @click="$emit('openDrawer')"
            >
                <Menu class="size-4" />
            </Button>
            <Button
                variant="ghost"
                size="icon"
                class="text-muted-foreground hidden size-10 lg:inline-flex"
                aria-label="Lipat sidebar"
                @click="$emit('toggleSidebar')"
            >
                <PanelLeftClose class="size-4" />
            </Button>
            <Breadcrumbs :items="breadcrumbs" />
        </div>

        <div class="flex items-center gap-2 md:gap-4">
            <DropdownMenu>
                <DropdownMenuTrigger as-child>
                    <Button
                        variant="ghost"
                        size="icon"
                        class="text-muted-foreground size-10"
                        aria-label="Notifikasi"
                    >
                        <Bell class="size-4" />
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent
                    align="end"
                    class="w-[min(380px,calc(100vw-2rem))] p-0"
                >
                    <div class="flex h-16 items-center border-b px-4">
                        <p class="text-sm font-semibold">Notifikasi</p>
                    </div>
                    <div
                        class="flex flex-col items-center gap-2 px-6 py-8 text-center"
                    >
                        <span
                            class="bg-secondary flex size-10 items-center justify-center rounded-full"
                        >
                            <Bell
                                class="text-muted-foreground size-5"
                                aria-hidden="true"
                            />
                        </span>
                        <p class="text-sm font-medium">Belum ada notifikasi</p>
                        <p class="text-muted-foreground text-xs">
                            Pemberitahuan sistem akan muncul di sini.
                        </p>
                    </div>
                </DropdownMenuContent>
            </DropdownMenu>

            <DropdownMenu v-if="user">
                <DropdownMenuTrigger as-child>
                    <Button
                        variant="ghost"
                        class="h-10 gap-3 px-1 md:px-0"
                        :aria-label="`Menu pengguna ${user.name}`"
                    >
                        <Avatar class="size-8">
                            <AvatarFallback
                                class="bg-primary-soft text-primary text-xs font-semibold"
                            >
                                {{ initials(user.name) }}
                            </AvatarFallback>
                        </Avatar>
                        <span
                            class="hidden flex-col items-start text-left md:flex"
                        >
                            <span class="text-sm leading-5 font-medium">{{
                                user.name
                            }}</span>
                            <span
                                class="text-muted-foreground text-xs leading-4 font-normal"
                                >{{ humanize(user.role) }}</span
                            >
                        </span>
                        <ChevronDown
                            class="text-muted-foreground hidden size-4 md:block"
                            aria-hidden="true"
                        />
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end" class="w-60 p-2">
                    <DropdownMenuItem
                        as-child
                        class="h-10 gap-3 px-3 text-sm font-medium"
                    >
                        <Link :href="route('profile.edit')">
                            <UserRound class="size-4" aria-hidden="true" />
                            Profil
                        </Link>
                    </DropdownMenuItem>
                    <DropdownMenuItem
                        as-child
                        class="text-danger h-10 gap-3 px-3 text-sm font-medium"
                    >
                        <Link
                            :href="route('logout')"
                            method="post"
                            as="button"
                            class="w-full"
                        >
                            <LogOut class="size-4" aria-hidden="true" />
                            Keluar
                        </Link>
                    </DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>
        </div>
    </header>
</template>
