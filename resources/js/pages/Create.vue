<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import PromptBar from '@/components/PromptBar.vue';
import type { PricingRules } from '@/lib/pricing';

defineProps<{
    /** Pixelforge's current rates and token rules; null if they couldn't be loaded. */
    pricing: PricingRules | null;
}>();
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
    <main class="flex justify-center px-3 pt-5 pb-60 sm:px-7">
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
    <PromptBar :pricing="pricing" />
</template>
