<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, nextTick, ref, watch } from 'vue';
import Amount from '@/components/Amount.vue';
import TablePager from '@/components/TablePager.vue';
import { formatCents, formatExact, formatFourDecimals } from '@/lib/money';
import { report } from '@/routes/admin';

/** Sums over a group of succeeded images. Amounts in nanodollars. */
type Figures = {
    images: number;
    charged: number;
    providerCost: number;
    profit: number;
    capped: number;
    absorbed: number;
    estimateLow: number;
    estimateHigh: number;
    /** Where the actual charges landed between estimate low and high, in whole percent. */
    position: number | null;
};

type Day = Figures & {
    date: string;
    pixelforgeCost: number | null;
    check: 'match' | 'mismatch' | 'not_checked';
    /** Pixelforge's cost minus ours. */
    difference: number | null;
};

type UserRow = Figures & { id: number; name: string; email: string };

type ImageRow = {
    id: number;
    prompt: string;
    user: { id: number; name: string };
    completedAt: string;
    estimateLow: number;
    estimateHigh: number;
    charged: number;
    providerCost: number;
    capped: boolean;
    absorbed: number;
    position: number | null;
};

type Paginator<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
};

const props = defineProps<{
    range: { from: string; to: string; maxDays: number };
    usageChecked: boolean;
    totals: Figures & { days: number; matchingDays: number };
    days: Day[];
    users: Paginator<UserRow>;
    images: Paginator<ImageRow>;
    filter: { day: string | null; user: { id: number; name: string } | null };
}>();

const SECTIONS = [
    { id: 'overview', label: 'Overview' },
    { id: 'by-day', label: 'By day' },
    { id: 'by-user', label: 'By user' },
    { id: 'by-image', label: 'Images' },
];

const th =
    'border-b border-border px-3.5 py-2.5 text-left text-xs font-medium whitespace-nowrap text-muted-foreground';
const thNumber =
    'border-b border-border px-3.5 py-2.5 text-right text-xs font-medium whitespace-nowrap text-muted-foreground';
const td = 'border-b border-border px-3.5 py-2.75';
const tdNumber =
    'border-b border-border px-3.5 py-2.75 text-right font-mono text-[12.5px] whitespace-nowrap tabular-nums';
const tag =
    'inline-block rounded-full px-2 py-0.5 align-middle text-[10.5px] font-semibold tracking-wider uppercase';
const linkButton =
    'cursor-pointer border-0 bg-transparent p-0 text-left text-tag-blue-foreground underline underline-offset-2 disabled:cursor-default disabled:text-muted-foreground disabled:no-underline';

const page = usePage();
const errors = computed(
    () => page.props.errors as Record<string, string | undefined>,
);
const current = ref('overview');
const from = ref(props.range.from);
const to = ref(props.range.to);
const checking = ref(false);

watch(
    () => props.range,
    (range) => {
        from.value = range.from;
        to.value = range.to;
    },
);

const mismatches = computed(
    () => props.days.filter((day) => day.check === 'mismatch').length,
);
const empty = computed(
    () => props.totals.images === 0 && mismatches.value === 0,
);
/** Days with no images and nothing to flag are still checked, just not listed. */
const shownDays = computed(() =>
    props.days.filter((day) => day.images > 0 || day.check === 'mismatch'),
);
const hiddenDays = computed(() => props.days.length - shownDays.value.length);
const imagesTitle = computed(() => {
    if (props.filter.day) {
        return `Images that finished on ${props.filter.day}`;
    }

    if (props.filter.user) {
        return `Images by ${props.filter.user.name}`;
    }

    return 'All images in range';
});

function scrollToSection(id: string): void {
    current.value = id;
    const element = document.getElementById(id);

    if (element) {
        window.scrollTo({
            top: element.getBoundingClientRect().top + window.scrollY - 80,
            behavior: 'smooth',
        });
    }
}

/** Visits the report with these query changes, keeping the rest of the current query. */
function visit(
    changes: Record<string, string | number | null>,
    only?: string[],
    onSuccess?: () => void,
): void {
    const query: Record<string, string> = Object.fromEntries(
        new URLSearchParams(window.location.search),
    );

    for (const [key, value] of Object.entries(changes)) {
        if (value === null) {
            delete query[key];
        } else {
            query[key] = String(value);
        }
    }

    router.get(
        report.url({ query }),
        {},
        { only, preserveState: true, preserveScroll: true, onSuccess },
    );
}

function pickRange(): void {
    if (!from.value || !to.value) {
        return;
    }

    visit({
        from: from.value,
        to: to.value,
        users_page: null,
        images_page: null,
        day: null,
        user: null,
    });
}

function showImages(changes: { day?: string; user?: number }): void {
    visit(
        {
            day: changes.day ?? null,
            user: changes.user ?? null,
            images_page: null,
        },
        ['images', 'filter'],
        () => nextTick(() => scrollToSection('by-image')),
    );
}

function checkAgain(): void {
    router.reload({
        only: ['usageChecked', 'totals', 'days'],
        onStart: () => (checking.value = true),
        onFinish: () => (checking.value = false),
    });
}

const estimate = (low: number, high: number) =>
    `${formatFourDecimals(low, 'down')}–${formatFourDecimals(high, 'up')}`;
const landed = (position: number | null) =>
    position === null ? '—' : `landed at ${position}% of range`;
</script>

<template>
    <Head title="Admin report" />
    <main class="px-3 pt-9 pb-30 sm:px-7">
        <div
            class="grid items-start justify-center gap-10 min-[1001px]:grid-cols-[220px_minmax(0,1040px)]"
        >
            <nav
                class="flex gap-0.5 overflow-auto min-[1001px]:sticky min-[1001px]:top-21 min-[1001px]:flex-col"
                aria-label="Report sections"
            >
                <a
                    v-for="section in SECTIONS"
                    :key="section.id"
                    :href="`#${section.id}`"
                    :aria-current="current === section.id ? 'true' : undefined"
                    class="rounded-md px-2.5 py-2 whitespace-nowrap text-muted-foreground no-underline aria-[current=true]:bg-secondary aria-[current=true]:text-foreground"
                    @click.prevent="scrollToSection(section.id)"
                    >{{ section.label }}</a
                >
            </nav>

            <div class="flex min-w-0 flex-col gap-3.5">
                <div
                    id="overview"
                    class="mb-1.5 flex flex-wrap items-end justify-between gap-4"
                >
                    <div>
                        <h1
                            class="m-0 text-4xl leading-[1.1] tracking-[-0.03em]"
                        >
                            Admin report
                        </h1>
                        <p class="m-0 text-xs text-muted-foreground">
                            Only admins see this page. Days are UTC, by
                            completion.
                        </p>
                    </div>
                    <form
                        class="flex flex-col items-end gap-1"
                        @submit.prevent="pickRange"
                    >
                        <div
                            class="flex flex-wrap items-center gap-x-2.5 gap-y-2 text-[13px] text-muted-foreground"
                        >
                            <input
                                v-model="from"
                                type="date"
                                aria-label="From"
                                :max="to"
                                :aria-invalid="errors.from ? 'true' : undefined"
                                class="rounded-sm border border-border bg-card px-2 py-1.5 text-foreground"
                                @change="pickRange"
                            />
                            –
                            <input
                                v-model="to"
                                type="date"
                                aria-label="To"
                                :min="from"
                                :aria-invalid="errors.to ? 'true' : undefined"
                                class="rounded-sm border border-border bg-card px-2 py-1.5 text-foreground"
                                @change="pickRange"
                            />
                            <span>max {{ range.maxDays }} days</span>
                        </div>
                        <p
                            v-if="errors.from || errors.to"
                            class="m-0 text-xs text-destructive"
                            role="alert"
                        >
                            {{ errors.from ?? errors.to }}
                        </p>
                    </form>
                </div>

                <div
                    class="grid gap-3.5 min-[721px]:grid-cols-2 min-[1001px]:grid-cols-4"
                >
                    <section
                        class="rounded-xl border border-border bg-card p-5"
                    >
                        <h2
                            class="m-0 mb-2 font-sans text-xs text-muted-foreground"
                        >
                            Charged to users
                        </h2>
                        <div class="font-serif text-3xl tracking-[-0.03em]">
                            <Amount :nanodollars="totals.charged" />
                        </div>
                    </section>
                    <section
                        class="rounded-xl border border-border bg-card p-5"
                    >
                        <h2
                            class="m-0 mb-2 font-sans text-xs text-muted-foreground"
                        >
                            Pixelforge cost
                        </h2>
                        <div class="font-serif text-3xl tracking-[-0.03em]">
                            <Amount :nanodollars="totals.providerCost" />
                        </div>
                    </section>
                    <section
                        class="rounded-xl border border-border bg-card p-5"
                    >
                        <h2
                            class="m-0 mb-2 font-sans text-xs text-muted-foreground"
                        >
                            Profit
                        </h2>
                        <div class="font-serif text-3xl tracking-[-0.03em]">
                            <Amount :nanodollars="totals.profit" />
                        </div>
                    </section>
                    <section
                        v-if="usageChecked"
                        class="rounded-xl border border-border bg-card p-5"
                    >
                        <h2
                            class="m-0 mb-2 font-sans text-xs text-muted-foreground"
                        >
                            Days matching Pixelforge
                        </h2>
                        <div class="font-serif text-3xl tracking-[-0.03em]">
                            {{ totals.matchingDays }}/{{ totals.days }}
                        </div>
                        <span
                            :class="[
                                tag,
                                mismatches
                                    ? 'bg-tag-yellow text-tag-yellow-foreground'
                                    : 'bg-tag-green text-tag-green-foreground',
                            ]"
                            >{{
                                mismatches
                                    ? `${mismatches} ${mismatches === 1 ? 'day' : 'days'} off`
                                    : 'all match'
                            }}</span
                        >
                    </section>
                    <section
                        v-else
                        class="rounded-xl border border-[#f1e3b5] bg-tag-yellow p-5"
                        role="status"
                    >
                        <h2
                            class="m-0 mb-2 font-sans text-xs text-muted-foreground"
                        >
                            Days matching Pixelforge
                        </h2>
                        <div class="font-serif text-xl">Couldn't check</div>
                        <p class="m-0 text-xs text-muted-foreground">
                            Pixelforge's usage report didn't respond. Our own
                            numbers are unaffected.
                        </p>
                        <button
                            type="button"
                            class="chip mt-2 disabled:cursor-wait disabled:opacity-60"
                            :disabled="checking"
                            @click="checkAgain"
                        >
                            {{ checking ? 'Checking…' : 'Try again' }}
                        </button>
                    </section>
                </div>

                <section
                    id="by-day"
                    class="rounded-xl border border-border bg-card"
                    aria-labelledby="by-day-title"
                >
                    <h2
                        id="by-day-title"
                        class="m-0 px-7 pt-7 pb-1.5 font-sans text-xs text-muted-foreground"
                    >
                        By day (UTC, by completion) · click a day to see its
                        images
                    </h2>
                    <div class="overflow-auto">
                        <table class="w-full border-collapse text-[13px]">
                            <thead>
                                <tr>
                                    <th :class="th">Day</th>
                                    <th :class="thNumber">Images</th>
                                    <th :class="thNumber">Charged</th>
                                    <th :class="thNumber">Pixelforge cost</th>
                                    <th :class="thNumber">
                                        Pixelforge reports
                                    </th>
                                    <th :class="thNumber">Profit</th>
                                    <th :class="thNumber">Estimated</th>
                                    <th :class="th">
                                        <span class="sr-only">Checks</span>
                                    </th>
                                </tr>
                            </thead>
                            <tbody v-if="empty">
                                <tr>
                                    <td
                                        colspan="8"
                                        class="px-3.5 py-7 text-center text-muted-foreground"
                                    >
                                        No images finished between
                                        {{ range.from }} and {{ range.to }}.
                                        Pick a wider range or check back after
                                        users generate.
                                    </td>
                                </tr>
                            </tbody>
                            <tbody v-else>
                                <tr
                                    v-for="day in shownDays"
                                    :key="day.date"
                                    :class="{
                                        'bg-accent': filter.day === day.date,
                                    }"
                                >
                                    <td :class="[td, 'whitespace-nowrap']">
                                        <button
                                            type="button"
                                            :class="linkButton"
                                            :disabled="day.images === 0"
                                            @click="
                                                showImages({ day: day.date })
                                            "
                                        >
                                            {{ day.date }}
                                        </button>
                                    </td>
                                    <td :class="tdNumber">{{ day.images }}</td>
                                    <td :class="tdNumber">
                                        {{ formatExact(day.charged) }}
                                    </td>
                                    <td :class="tdNumber">
                                        {{ formatExact(day.providerCost) }}
                                    </td>
                                    <td :class="tdNumber">
                                        <span
                                            v-if="day.pixelforgeCost === null"
                                            class="text-muted-foreground"
                                            >—</span
                                        >
                                        <template v-else>{{
                                            formatExact(day.pixelforgeCost)
                                        }}</template>
                                    </td>
                                    <td :class="tdNumber">
                                        {{ formatExact(day.profit) }}
                                    </td>
                                    <td :class="tdNumber">
                                        <template v-if="day.images">
                                            {{
                                                estimate(
                                                    day.estimateLow,
                                                    day.estimateHigh,
                                                )
                                            }}
                                            <small
                                                class="block text-[11px] text-muted-foreground"
                                                >{{
                                                    landed(day.position)
                                                }}</small
                                            >
                                        </template>
                                        <span
                                            v-else
                                            class="text-muted-foreground"
                                            >—</span
                                        >
                                    </td>
                                    <td
                                        :class="[
                                            td,
                                            'space-x-1.5 whitespace-nowrap',
                                        ]"
                                    >
                                        <span
                                            v-if="day.check === 'not_checked'"
                                            :class="[
                                                tag,
                                                'bg-tag-gray text-tag-gray-foreground',
                                            ]"
                                            >not checked</span
                                        >
                                        <span
                                            v-else-if="day.check === 'match'"
                                            :class="[
                                                tag,
                                                'bg-tag-green text-tag-green-foreground',
                                            ]"
                                            >match</span
                                        >
                                        <span
                                            v-else
                                            :class="[
                                                tag,
                                                'bg-tag-yellow text-tag-yellow-foreground',
                                            ]"
                                            :title="`Pixelforge reports ${formatExact(day.difference ?? 0)} ${(day.difference ?? 0) > 0 ? 'more' : 'less'} than our records`"
                                            >mismatch
                                            <span class="sr-only"
                                                >: off by
                                                {{
                                                    formatExact(
                                                        day.difference ?? 0,
                                                    )
                                                }}</span
                                            ></span
                                        >
                                        <span
                                            v-if="day.capped"
                                            :class="[
                                                tag,
                                                'bg-tag-red text-tag-red-foreground',
                                            ]"
                                            :title="`${formatExact(day.absorbed)} absorbed`"
                                            >{{ day.capped }} capped</span
                                        >
                                    </td>
                                </tr>
                                <tr v-if="hiddenDays > 0">
                                    <td
                                        colspan="8"
                                        class="px-3.5 py-3 text-xs text-muted-foreground"
                                    >
                                        {{ hiddenDays }}
                                        {{ hiddenDays === 1 ? 'day' : 'days' }}
                                        with no images not shown{{
                                            usageChecked
                                                ? ', each checked against Pixelforge'
                                                : ''
                                        }}.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>

                <section
                    id="by-user"
                    class="rounded-xl border border-border bg-card"
                    aria-labelledby="by-user-title"
                >
                    <h2
                        id="by-user-title"
                        class="m-0 px-7 pt-7 pb-1.5 font-sans text-xs text-muted-foreground"
                    >
                        By user · click a user to see their images
                    </h2>
                    <div class="overflow-auto">
                        <table class="w-full border-collapse text-[13px]">
                            <thead>
                                <tr>
                                    <th :class="th">User</th>
                                    <th :class="thNumber">Images</th>
                                    <th :class="thNumber">Charged</th>
                                    <th :class="thNumber">Pixelforge cost</th>
                                    <th :class="thNumber">Profit</th>
                                    <th :class="thNumber">Estimated</th>
                                    <th :class="th">
                                        <span class="sr-only">Capped</span>
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-if="users.total === 0">
                                    <td
                                        colspan="7"
                                        class="px-3.5 py-7 text-center text-muted-foreground"
                                    >
                                        No users generated images in this range.
                                    </td>
                                </tr>
                                <tr
                                    v-for="user in users.data"
                                    :key="user.id"
                                    :class="{
                                        'bg-accent':
                                            filter.user?.id === user.id,
                                    }"
                                >
                                    <td :class="td">
                                        <button
                                            type="button"
                                            :class="linkButton"
                                            @click="
                                                showImages({ user: user.id })
                                            "
                                        >
                                            {{ user.name }}
                                        </button>
                                        <div
                                            class="text-xs text-muted-foreground"
                                        >
                                            {{ user.email }}
                                        </div>
                                    </td>
                                    <td :class="tdNumber">{{ user.images }}</td>
                                    <td :class="tdNumber">
                                        {{ formatExact(user.charged) }}
                                    </td>
                                    <td :class="tdNumber">
                                        {{ formatExact(user.providerCost) }}
                                    </td>
                                    <td :class="tdNumber">
                                        {{ formatExact(user.profit) }}
                                    </td>
                                    <td :class="tdNumber">
                                        {{
                                            estimate(
                                                user.estimateLow,
                                                user.estimateHigh,
                                            )
                                        }}
                                        <small
                                            class="block text-[11px] text-muted-foreground"
                                            >{{ landed(user.position) }}</small
                                        >
                                    </td>
                                    <td :class="[td, 'whitespace-nowrap']">
                                        <span
                                            v-if="user.capped"
                                            :class="[
                                                tag,
                                                'bg-tag-red text-tag-red-foreground',
                                            ]"
                                            :title="`${formatExact(user.absorbed)} absorbed`"
                                            >{{ user.capped }} capped</span
                                        >
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <TablePager
                        v-if="users.total"
                        :paginator="users"
                        order="Highest charged first"
                        noun="users"
                        @page="
                            (n) =>
                                visit({ users_page: n > 1 ? n : null }, [
                                    'users',
                                ])
                        "
                    />
                </section>

                <section
                    id="by-image"
                    class="rounded-xl border border-border bg-card"
                    aria-labelledby="by-image-title"
                >
                    <div
                        class="flex items-center justify-between px-7 pt-7 pb-1.5"
                    >
                        <h2
                            id="by-image-title"
                            class="m-0 font-sans text-xs text-muted-foreground"
                        >
                            {{ imagesTitle }}
                        </h2>
                        <button
                            v-if="filter.day || filter.user"
                            type="button"
                            class="chip"
                            @click="showImages({})"
                        >
                            Show all
                        </button>
                    </div>
                    <div class="overflow-auto">
                        <table class="w-full border-collapse text-[13px]">
                            <thead>
                                <tr>
                                    <th :class="th">Prompt</th>
                                    <th v-if="!filter.user" :class="th">
                                        User
                                    </th>
                                    <th :class="thNumber">Estimate</th>
                                    <th :class="[th, 'w-40']">Landed at</th>
                                    <th :class="thNumber">Charged</th>
                                    <th :class="thNumber">Pixelforge cost</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-if="images.total === 0">
                                    <td
                                        :colspan="filter.user ? 5 : 6"
                                        class="px-3.5 py-7 text-center text-muted-foreground"
                                    >
                                        No images to show.
                                    </td>
                                </tr>
                                <tr
                                    v-for="image in images.data"
                                    :key="image.id"
                                >
                                    <td :class="[td, 'min-w-56']">
                                        <span class="line-clamp-2">{{
                                            image.prompt
                                        }}</span>
                                        <span
                                            v-if="image.capped"
                                            :class="[
                                                tag,
                                                'mt-1 bg-tag-red text-tag-red-foreground',
                                            ]"
                                            :title="`${formatExact(image.absorbed)} absorbed`"
                                            >capped</span
                                        >
                                        <div
                                            class="text-xs text-muted-foreground"
                                        >
                                            {{
                                                new Date(
                                                    image.completedAt,
                                                ).toLocaleString(undefined, {
                                                    timeZone: 'UTC',
                                                    dateStyle: 'medium',
                                                    timeStyle: 'short',
                                                })
                                            }}
                                            UTC
                                        </div>
                                    </td>
                                    <td
                                        v-if="!filter.user"
                                        :class="[
                                            td,
                                            'text-xs whitespace-nowrap text-muted-foreground',
                                        ]"
                                    >
                                        {{ image.user.name }}
                                    </td>
                                    <td :class="tdNumber">
                                        {{
                                            estimate(
                                                image.estimateLow,
                                                image.estimateHigh,
                                            )
                                        }}
                                    </td>
                                    <td :class="td">
                                        <template
                                            v-if="image.position !== null"
                                        >
                                            <div
                                                class="relative mt-1.5 mb-1.5 h-1 rounded-xs bg-tag-gray"
                                                aria-hidden="true"
                                            >
                                                <i
                                                    class="absolute -top-0.75 h-2.5 w-0.5 bg-foreground"
                                                    :style="{
                                                        left: `calc(${image.position}% - 1px)`,
                                                    }"
                                                />
                                            </div>
                                            <span
                                                class="text-xs whitespace-nowrap text-muted-foreground"
                                                >{{ image.position }}% of
                                                range</span
                                            >
                                        </template>
                                    </td>
                                    <td :class="tdNumber">
                                        {{ formatExact(image.charged) }}
                                    </td>
                                    <td :class="tdNumber">
                                        {{ formatExact(image.providerCost) }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <TablePager
                        v-if="images.total"
                        :paginator="images"
                        order="Newest first"
                        noun="images"
                        @page="
                            (n) =>
                                visit({ images_page: n > 1 ? n : null }, [
                                    'images',
                                ])
                        "
                    />
                </section>
            </div>
        </div>
    </main>
</template>
