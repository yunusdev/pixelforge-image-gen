<script setup lang="ts">
import { Head, router, usePoll } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import Amount from '@/components/Amount.vue';
import GenerationPanel from '@/components/GenerationPanel.vue';
import GenerationTile from '@/components/GenerationTile.vue';
import PromptBar from '@/components/PromptBar.vue';
import { Button } from '@/components/ui/button';
import type { PricingRules } from '@/lib/pricing';
import { create } from '@/routes';
import type { Generation } from '@/types/generation';
import { isFinal } from '@/types/generation';

type FeedFilter = 'all' | 'running' | 'succeeded' | 'failed';

const props = defineProps<{
    /** Pixelforge's current rates and token rules; null if they couldn't be loaded. */
    pricing: PricingRules | null;
    /** The user's newest generations matching the filter, newest first. */
    generations: Generation[];
    /** The filter, how many are asked for, how many match, and how many the user has at all. */
    feed: {
        filter: FeedFilter;
        shown: number;
        total: number;
        everything: number;
    };
    /** The sign-up bonus a new user starts with, in nanodollars. */
    startingCredits: number;
}>();

/** How often the feed refreshes while something is still running. */
const POLL_MILLISECONDS = 2500;
/** "Load 20 more" asks for this many more. */
const STEP = 20;

const FILTERS: { value: FeedFilter; label: string }[] = [
    { value: 'all', label: 'All' },
    { value: 'running', label: 'Running' },
    { value: 'succeeded', label: 'Done' },
    { value: 'failed', label: 'Failed' },
];

const NOTHING_MATCHES: Record<Exclude<FeedFilter, 'all'>, string> = {
    running: 'Nothing running right now.',
    succeeded: 'No finished images yet.',
    failed: 'No failed images.',
};

const EXAMPLES = [
    'A lighthouse on a cliff at dawn, oil painting',
    'Isometric tiny greenhouse full of succulents',
    'Neon koi pond seen from above',
];

const promptBar = ref<InstanceType<typeof PromptBar> | null>(null);
const selectedId = ref<number | null>(null);
const selected = computed(
    () => props.generations.find((g) => g.id === selectedId.value) ?? null,
);
const loadingMore = ref(false);

// Poll only the feed (and the available balance), and only while any visible generation isn't final.
// A hidden tab pauses instead of throttling (browsers slow background timers to minutes),
// and polls at once when shown again, so a tab you come back to is never stale.
const running = computed(() => props.generations.some((g) => !isFinal(g)));
const poll = usePoll(
    POLL_MILLISECONDS,
    { only: ['generations', 'feed', 'credits'] },
    { autoStart: false, background: 'pause' },
);
watch(running, (now) => (now ? poll.start() : poll.stop()), {
    immediate: true,
});

/** Reload just the feed for a filter and how many to show; the URL keeps both so polling does too. */
function show(filter: FeedFilter, shown: number, onFinish?: () => void): void {
    router.get(
        create.url({
            query: {
                filter: filter === 'all' ? undefined : filter,
                shown: shown > STEP ? shown : undefined,
            },
        }),
        {},
        {
            only: ['generations', 'feed'],
            preserveState: true,
            preserveScroll: true,
            replace: true,
            onFinish,
        },
    );
}

function filterBy(filter: FeedFilter): void {
    show(filter, STEP);
}

function loadMore(): void {
    loadingMore.value = true;
    show(props.feed.filter, props.feed.shown + STEP, () => {
        loadingMore.value = false;
    });
}

function reuse(generation: Generation, retry: boolean): void {
    selectedId.value = null;
    promptBar.value?.fill(generation);
    toast.success(
        retry
            ? 'Settings copied. Press Generate to try again; the failed one stays free.'
            : 'Settings copied into the prompt bar.',
    );
}
</script>

<template>
    <Head title="Create" />
    <section
        class="flex flex-col items-start justify-between gap-5 px-3 pt-10 pb-2 min-[721px]:flex-row min-[721px]:items-end sm:px-7"
    >
        <div>
            <h1 class="m-0 text-[40px] leading-[1.1] tracking-[-0.03em]">
                Your images
            </h1>
            <p class="text-xs text-muted-foreground">
                Newest first. Anything still running updates on its own.
            </p>
        </div>
        <div
            v-if="feed.everything > 0"
            class="flex flex-wrap gap-1.5"
            role="group"
            aria-label="Filter by status"
        >
            <button
                v-for="option in FILTERS"
                :key="option.value"
                type="button"
                class="chip"
                :aria-pressed="feed.filter === option.value"
                @click="filterBy(option.value)"
            >
                {{ option.label }}
            </button>
        </div>
    </section>

    <!-- A new user: no images at all. -->
    <main
        v-if="feed.everything === 0"
        class="flex justify-center px-3 pt-5 pb-60 sm:px-7"
    >
        <div
            class="mx-auto my-5 max-w-160 rounded-xl border border-border bg-card p-10 text-center"
        >
            <h2 class="m-0 mb-2 text-[30px]">No images yet</h2>
            <p class="text-xs text-muted-foreground">
                You have <Amount :nanodollars="startingCredits" /> to start
                with. Write a prompt below, or try one of these:
            </p>
            <div class="mt-4 flex flex-wrap justify-center gap-2">
                <button
                    v-for="example in EXAMPLES"
                    :key="example"
                    type="button"
                    class="chip"
                    @click="promptBar?.fillPrompt(example)"
                >
                    {{ example }}
                </button>
            </div>
            <p class="mt-4.5 text-xs text-muted-foreground">
                You'll see the price range before anything runs, and failed
                images are free.
            </p>
        </div>
    </main>

    <!-- The filter matches nothing. -->
    <main
        v-else-if="generations.length === 0"
        class="flex justify-center px-3 pt-5 pb-60 sm:px-7"
    >
        <div
            class="mx-auto my-5 flex items-center justify-center gap-3.5 rounded-xl border border-border bg-card p-5.5"
        >
            <p class="m-0">
                {{
                    feed.filter === 'all'
                        ? 'Nothing to show.'
                        : NOTHING_MATCHES[feed.filter]
                }}
            </p>
            <button type="button" class="chip" @click="filterBy('all')">
                Show all
            </button>
        </div>
    </main>

    <template v-else>
        <main
            class="columns-[150px_2] gap-3.5 px-3 pt-5 sm:columns-[260px_4] sm:px-7"
            aria-label="Your images"
        >
            <GenerationTile
                v-for="generation in generations"
                :key="generation.id"
                :generation="generation"
                @open="selectedId = generation.id"
            />
        </main>
        <div
            class="flex items-center justify-center gap-3.5 px-3 pt-2 pb-60 text-xs text-muted-foreground sm:px-7"
        >
            <template v-if="feed.total > generations.length">
                <Button
                    variant="outline"
                    :disabled="loadingMore"
                    @click="loadMore"
                >
                    Load 20 more
                </Button>
                <span class="font-mono"
                    >Showing {{ generations.length }} of {{ feed.total }}</span
                >
            </template>
            <span v-else class="font-mono"
                >That's everything · {{ feed.total }}
                {{ feed.total === 1 ? 'image' : 'images' }}</span
            >
        </div>
    </template>

    <PromptBar ref="promptBar" :pricing="pricing" />
    <GenerationPanel
        :generation="selected"
        @close="selectedId = null"
        @reuse="reuse"
    />
</template>
