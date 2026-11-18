<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import Amount from '@/components/Amount.vue';
import AppLogo from '@/components/AppLogo.vue';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { getInitials } from '@/composables/useInitials';
import { create, credits, home, logout } from '@/routes';
import { report as adminReport } from '@/routes/admin';
import { edit as editProfile } from '@/routes/profile';

const page = usePage();
const user = computed(() => page.props.auth.user);
/** Nanodollars the user can start generations with right now. */
const available = computed(() => page.props.credits?.available ?? 0);
const { isCurrentUrl } = useCurrentUrl();

const navItems = computed(() => [
    { title: 'Create', href: create() },
    { title: 'Credits', href: credits() },
    ...(user.value.is_admin
        ? [{ title: 'Admin report', href: adminReport() }]
        : []),
]);

const mobileOpen = ref(false);

function logOut(): void {
    router.flushAll();
}
</script>

<template>
    <header
        class="sticky top-0 z-20 border-b border-border bg-background/85 backdrop-blur-md"
    >
        <div
            class="flex items-center gap-3 px-3.5 py-2.5 sm:gap-6 sm:px-7 sm:py-3"
        >
            <Link :href="home()" aria-label="Pixelforge home">
                <AppLogo />
            </Link>

            <nav class="hidden gap-1 min-[721px]:flex" aria-label="Main">
                <Link
                    v-for="item in navItems"
                    :key="item.title"
                    :href="item.href"
                    :aria-current="isCurrentUrl(item.href) ? 'page' : undefined"
                    class="rounded-sm px-2.5 py-1.5 text-muted-foreground no-underline aria-[current=page]:bg-secondary aria-[current=page]:text-foreground"
                    >{{ item.title }}</Link
                >
            </nav>

            <Link
                :href="credits()"
                class="ml-auto hidden items-center gap-1.5 rounded-sm border border-border bg-card px-2.5 py-1.5 whitespace-nowrap text-foreground no-underline min-[721px]:inline-flex"
                >Available
                <b class="font-medium"
                    ><Amount :nanodollars="available" rounding="down" /></b
            ></Link>

            <DropdownMenu>
                <DropdownMenuTrigger
                    class="hidden size-[34px] cursor-pointer items-center justify-center rounded-full border border-border bg-secondary text-xs font-semibold text-foreground min-[721px]:inline-flex"
                    aria-label="Account menu"
                >
                    {{ getInitials(user.name) }}
                </DropdownMenuTrigger>
                <DropdownMenuContent
                    align="end"
                    :side-offset="8"
                    class="min-w-60 rounded-lg p-1.5"
                >
                    <DropdownMenuLabel
                        class="flex flex-col px-2.5 pt-2.5 pb-3 font-normal"
                    >
                        <b class="font-semibold">{{ user.name }}</b>
                        <span class="text-xs text-muted-foreground">{{
                            user.email
                        }}</span>
                    </DropdownMenuLabel>
                    <DropdownMenuSeparator />
                    <DropdownMenuItem as-child>
                        <Link :href="credits()" class="w-full"
                            >Credits ·
                            <Amount :nanodollars="available" rounding="down" />
                            available</Link
                        >
                    </DropdownMenuItem>
                    <DropdownMenuItem as-child>
                        <Link :href="editProfile()" class="w-full"
                            >Settings</Link
                        >
                    </DropdownMenuItem>
                    <DropdownMenuItem as-child>
                        <Link
                            :href="logout()"
                            as="button"
                            class="w-full text-left"
                            data-test="logout-button"
                            @click="logOut"
                            >Log out</Link
                        >
                    </DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>

            <button
                type="button"
                class="ml-auto inline-flex cursor-pointer items-center rounded-sm border border-border bg-card px-2.5 py-1.5 text-foreground min-[721px]:hidden"
                aria-controls="mobile-nav"
                :aria-expanded="mobileOpen"
                @click="mobileOpen = !mobileOpen"
            >
                Menu
            </button>
        </div>

        <nav
            v-show="mobileOpen"
            id="mobile-nav"
            class="flex flex-col border-t border-border px-3.5 pt-1.5 pb-3 min-[721px]:hidden"
            aria-label="Mobile"
        >
            <div class="px-1.5 py-2.5 text-[13px] text-muted-foreground">
                {{ user.name }} · {{ user.email }}
            </div>
            <div class="px-1.5 pb-2.5 text-[13px] text-muted-foreground">
                Available
                <b class="font-medium text-foreground"
                    ><Amount :nanodollars="available" rounding="down"
                /></b>
            </div>
            <Link
                v-for="item in navItems"
                :key="item.title"
                :href="item.href"
                :aria-current="isCurrentUrl(item.href) ? 'page' : undefined"
                class="border-b border-border px-1.5 py-2.5 text-foreground no-underline aria-[current=page]:font-semibold"
                @click="mobileOpen = false"
                >{{ item.title }}</Link
            >
            <Link
                :href="editProfile()"
                class="border-b border-border px-1.5 py-2.5 text-foreground no-underline"
                @click="mobileOpen = false"
                >Settings</Link
            >
            <Link
                :href="logout()"
                as="button"
                class="cursor-pointer px-1.5 py-2.5 text-left text-foreground"
                @click="logOut"
                >Log out</Link
            >
        </nav>
    </header>
</template>
