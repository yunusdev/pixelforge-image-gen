<script setup lang="ts">
import { Head, usePoll } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import GenerationPanel from '@/components/GenerationPanel.vue';
import GenerationTile from '@/components/GenerationTile.vue';
import PromptBar from '@/components/PromptBar.vue';
import type { PricingRules } from '@/lib/pricing';
import type { Generation } from '@/types/generation';
import { isFinal } from '@/types/generation';

const props = defineProps<{
    /** Pixelforge's current rates and token rules; null if they couldn't be loaded. */
    pricing: PricingRules | null;
    /** The user's newest generations, newest first. */
    generations: Generation[];
}>();

/** How often the feed refreshes while something is still running. */
const POLL_MILLISECONDS = 2500;

const promptBar = ref<InstanceType<typeof PromptBar> | null>(null);
const selectedId = ref<number | null>(null);
const selected = computed(
    () => props.generations.find((g) => g.id === selectedId.value) ?? null,
);

// Poll only the generations prop, and only while any of them isn't final.
const running = computed(() => props.generations.some((g) => !isFinal(g)));
const poll = usePoll(
    POLL_MILLISECONDS,
    { only: ['generations'] },
    { autoStart: false },
);
watch(running, (now) => (now ? poll.start() : poll.stop()), {
    immediate: true,
});

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
    </section>

    <main
        v-if="generations.length"
        class="columns-[150px_2] gap-3.5 px-3 pt-5 pb-60 sm:columns-[260px_4] sm:px-7"
        aria-label="Your images"
    >
        <GenerationTile
            v-for="generation in generations"
            :key="generation.id"
            :generation="generation"
            @open="selectedId = generation.id"
        />
    </main>
    <main v-else class="flex justify-center px-3 pt-5 pb-60 sm:px-7">
        <div
            class="mx-auto my-5 max-w-160 rounded-xl border border-border bg-card p-10 text-center"
        >
            <h2 class="m-0 mb-2 text-[30px]">No images yet</h2>
            <p class="text-xs text-muted-foreground">
                Write a prompt to make your first image. You'll see the price
                range before anything runs, and failed images are free.
            </p>
        </div>
    </main>

    <PromptBar ref="promptBar" :pricing="pricing" />
    <GenerationPanel
        :generation="selected"
        @close="selectedId = null"
        @reuse="reuse"
    />
</template>
