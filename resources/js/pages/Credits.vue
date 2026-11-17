<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import Amount from '@/components/Amount.vue';
import SpendingChart from '@/components/SpendingChart.vue';
import { credits } from '@/routes';
import { store as buyPack } from '@/routes/credits/packs';

type LedgerLine = {
    id: number;
    at: string;
    label: string;
    prompt: string | null;
    /** Signed nanodollars. */
    amount: number;
};

/** All amounts in nanodollars. */
const props = defineProps<{
    summary: {
        available: number;
        balance: number;
        setAside: number;
        running: number;
    };
    spending: { days: { date: string; amount: number }[]; total: number };
    packs: number[];
    ledger: {
        data: LedgerLine[];
        current_page: number;
        last_page: number;
        from: number | null;
        to: number | null;
        total: number;
    };
}>();

const SECTIONS = [
    { id: 'overview', label: 'Overview' },
    { id: 'packs', label: 'Add credits' },
    { id: 'ledger', label: 'Ledger' },
];

const page = usePage();
/** The ledger line just added by buying a pack. */
const highlight = computed(() => page.flash.highlight ?? null);
const current = ref('overview');
const buying = ref<number | null>(null);

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

function buy(pack: number): void {
    buying.value = pack;
    router.post(
        buyPack().url,
        { pack },
        {
            preserveScroll: true,
            onFinish: () => (buying.value = null),
        },
    );
}

function showLedgerPage(number: number): void {
    router.get(
        credits.url({ query: { page: number > 1 ? number : undefined } }),
        {},
        { only: ['ledger'], preserveState: true, preserveScroll: true },
    );
}

const shortDate = (date: string) =>
    new Date(`${date}T00:00:00Z`).toLocaleDateString(undefined, {
        month: 'short',
        day: 'numeric',
        timeZone: 'UTC',
    });
const chartEnds = computed(() => {
    const days = props.spending.days;

    return [days[0], days[Math.floor(days.length / 2)], days[days.length - 1]]
        .filter(Boolean)
        .map((d) => shortDate(d.date));
});
const onlyBonus = computed(
    () => props.ledger.total <= 1 && props.ledger.current_page === 1,
);
</script>

<template>
    <Head title="Credits" />
    <main class="px-3 pt-9 pb-30 sm:px-7">
        <div
            class="grid items-start justify-center gap-10 min-[1001px]:grid-cols-[220px_minmax(0,1040px)]"
        >
            <nav
                class="flex gap-0.5 overflow-auto min-[1001px]:sticky min-[1001px]:top-21 min-[1001px]:flex-col"
                aria-label="Credits sections"
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
                <h1
                    id="overview"
                    class="m-0 mb-1.5 text-4xl leading-[1.1] tracking-[-0.03em]"
                >
                    Credits
                </h1>

                <div class="grid gap-3.5 min-[1001px]:grid-cols-2">
                    <section
                        class="rounded-xl border border-border bg-card p-7"
                        aria-labelledby="credits-card"
                    >
                        <div class="mb-2.5 flex items-center justify-between">
                            <h2
                                id="credits-card"
                                class="m-0 font-sans text-xs text-muted-foreground"
                            >
                                Credits
                            </h2>
                            <a
                                href="#packs"
                                class="chip no-underline"
                                @click.prevent="scrollToSection('packs')"
                                >Top up</a
                            >
                        </div>
                        <div
                            class="font-serif text-6xl leading-none tracking-[-0.04em]"
                        >
                            <Amount
                                :nanodollars="summary.available"
                                rounding="down"
                            />
                        </div>
                        <p class="m-0 text-xs text-muted-foreground">
                            available to spend
                        </p>
                        <dl class="mt-6 mb-0 border-t border-border">
                            <div
                                class="grid grid-cols-[1fr_auto] border-b border-border py-2.75"
                            >
                                <dt>Balance</dt>
                                <dd
                                    class="m-0 text-right font-mono text-[13px]"
                                >
                                    <Amount
                                        :nanodollars="summary.balance"
                                        rounding="down"
                                    />
                                </dd>
                            </div>
                            <div class="grid grid-cols-[1fr_auto] py-2.75">
                                <dt>
                                    Set aside for {{ summary.running }} running
                                    <small
                                        class="block font-mono text-[11px] text-muted-foreground"
                                        >max price, released when they
                                        finish</small
                                    >
                                </dt>
                                <dd
                                    class="m-0 text-right font-mono text-[13px]"
                                >
                                    −
                                    <Amount
                                        :nanodollars="summary.setAside"
                                        rounding="up"
                                    />
                                </dd>
                            </div>
                        </dl>
                    </section>

                    <section
                        class="rounded-xl border border-border bg-card p-7"
                        aria-labelledby="spending-card"
                    >
                        <div class="mb-2.5 flex items-center justify-between">
                            <h2
                                id="spending-card"
                                class="m-0 font-sans text-xs text-muted-foreground"
                            >
                                Spending · last 30 days
                            </h2>
                            <a
                                href="#ledger"
                                class="chip no-underline"
                                @click.prevent="scrollToSection('ledger')"
                                >See ledger</a
                            >
                        </div>
                        <div class="font-serif text-3xl tracking-[-0.02em]">
                            <Amount :nanodollars="spending.total" />
                        </div>
                        <SpendingChart
                            v-if="spending.total > 0"
                            :days="spending.days"
                            class="text-foreground"
                        />
                        <div
                            v-else
                            class="grid h-[90px] place-items-center rounded-md bg-accent px-4 text-center text-[12.5px] text-muted-foreground"
                        >
                            Nothing spent yet. Charges show up here once an
                            image succeeds.
                        </div>
                        <div
                            class="mt-1 flex justify-between font-mono text-[11px] text-muted-foreground"
                        >
                            <span v-for="end in chartEnds" :key="end">{{
                                end
                            }}</span>
                        </div>
                    </section>
                </div>

                <section
                    id="packs"
                    class="rounded-xl border border-border bg-card p-7"
                    aria-labelledby="packs-title"
                >
                    <h2
                        id="packs-title"
                        class="m-0 mb-2 font-sans text-xs text-muted-foreground"
                    >
                        Add credits
                    </h2>
                    <div class="grid gap-2.5 min-[1001px]:grid-cols-3">
                        <button
                            v-for="pack in packs"
                            :key="pack"
                            type="button"
                            class="flex cursor-pointer items-baseline justify-between rounded-lg border border-border bg-card p-4 text-left hover:border-[#cfcdc8] hover:bg-accent disabled:cursor-wait disabled:opacity-60"
                            :disabled="buying !== null"
                            @click="buy(pack)"
                        >
                            <b class="font-serif text-[26px] font-normal"
                                >${{ pack }}</b
                            >
                            <span class="text-xs text-muted-foreground">{{
                                buying === pack ? 'Adding…' : 'Add'
                            }}</span>
                        </button>
                    </div>
                    <p class="m-0 mt-2.5 text-xs text-muted-foreground">
                        No payment needed. This is a demo.
                    </p>
                </section>

                <section
                    id="ledger"
                    class="rounded-xl border border-border bg-card"
                    aria-labelledby="ledger-title"
                >
                    <h2
                        id="ledger-title"
                        class="m-0 px-7 pt-7 pb-1.5 font-sans text-xs text-muted-foreground"
                    >
                        Ledger
                    </h2>
                    <div class="overflow-auto">
                        <table class="w-full border-collapse text-[13px]">
                            <thead>
                                <tr>
                                    <th
                                        class="border-b border-border px-3.5 py-2.5 text-left text-xs font-medium text-muted-foreground"
                                    >
                                        Date
                                    </th>
                                    <th
                                        class="border-b border-border px-3.5 py-2.5 text-left text-xs font-medium text-muted-foreground"
                                    >
                                        Entry
                                    </th>
                                    <th
                                        class="border-b border-border px-3.5 py-2.5 text-right text-xs font-medium text-muted-foreground"
                                    >
                                        Amount
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="line in ledger.data"
                                    :key="line.id"
                                    :class="{
                                        'bg-tag-green': line.id === highlight,
                                    }"
                                    :data-fresh="
                                        line.id === highlight ? '' : undefined
                                    "
                                >
                                    <td
                                        class="border-b border-border px-3.5 py-2.75 text-xs whitespace-nowrap text-muted-foreground"
                                    >
                                        {{
                                            new Date(
                                                line.at,
                                            ).toLocaleDateString()
                                        }}
                                    </td>
                                    <td
                                        class="border-b border-border px-3.5 py-2.75"
                                    >
                                        {{ line.label }}
                                        <div
                                            v-if="line.prompt"
                                            class="line-clamp-1 text-xs text-muted-foreground"
                                        >
                                            {{ line.prompt }}
                                        </div>
                                    </td>
                                    <td
                                        class="border-b border-border px-3.5 py-2.75 text-right font-mono text-[12.5px] whitespace-nowrap"
                                        :class="
                                            line.amount > 0
                                                ? 'text-tag-green-foreground'
                                                : 'text-foreground'
                                        "
                                    >
                                        {{ line.amount > 0 ? '+' : '−'
                                        }}<Amount
                                            :nanodollars="Math.abs(line.amount)"
                                        />
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div
                        class="flex flex-wrap items-center justify-between gap-3 px-3.5 py-3 text-xs text-muted-foreground"
                    >
                        <span v-if="onlyBonus"
                            >Your sign-up bonus is the only entry so far.
                            Purchases and image charges will appear here.</span
                        >
                        <template v-else>
                            <span
                                >Newest first · showing {{ ledger.from }}–{{
                                    ledger.to
                                }}
                                of {{ ledger.total }} entries</span
                            >
                            <span
                                v-if="ledger.last_page > 1"
                                class="flex items-center gap-2.5"
                            >
                                <button
                                    type="button"
                                    class="chip disabled:cursor-not-allowed disabled:opacity-45"
                                    :disabled="ledger.current_page <= 1"
                                    @click="
                                        showLedgerPage(ledger.current_page - 1)
                                    "
                                >
                                    Previous
                                </button>
                                <span
                                    >Page {{ ledger.current_page }} of
                                    {{ ledger.last_page }}</span
                                >
                                <button
                                    type="button"
                                    class="chip disabled:cursor-not-allowed disabled:opacity-45"
                                    :disabled="
                                        ledger.current_page >= ledger.last_page
                                    "
                                    @click="
                                        showLedgerPage(ledger.current_page + 1)
                                    "
                                >
                                    Next
                                </button>
                            </span>
                        </template>
                    </div>
                </section>
            </div>
        </div>
    </main>
</template>
