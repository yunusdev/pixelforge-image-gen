<script setup lang="ts">
import { computed } from 'vue';
import Amount from '@/components/Amount.vue';
import GenerationSkeleton from '@/components/GenerationSkeleton.vue';
import StatusTag from '@/components/StatusTag.vue';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetTitle,
} from '@/components/ui/sheet';
import { formatCents, formatFourDecimals } from '@/lib/money';
import type { Generation } from '@/types/generation';
import { aspect, imageExpired, isFinal } from '@/types/generation';

/** The side panel for one image: its status, settings and what it cost (or will cost at most). */
const props = defineProps<{ generation: Generation | null }>();
const emit = defineEmits<{
    close: [];
    /** Copy this generation's settings into the prompt bar. */
    reuse: [generation: Generation, retry: boolean];
}>();

const open = computed({
    get: () => props.generation !== null,
    set: (value) => {
        if (!value) {
            emit('close');
        }
    },
});

const g = computed(() => props.generation);
const expired = computed(() => (g.value ? imageExpired(g.value) : false));
/** Where the charge landed between the low estimate and the max, 0–1. */
const position = computed(() => {
    if (!g.value || g.value.status !== 'succeeded') {
        return 0;
    }

    const span = Math.max(1, g.value.estimate_high - g.value.estimate_low);

    return Math.min(
        1,
        Math.max(0, (g.value.charged - g.value.estimate_low) / span),
    );
});
</script>

<template>
    <Sheet v-model:open="open">
        <SheetContent
            v-if="g"
            side="right"
            class="w-full gap-0 overflow-auto bg-card p-6 sm:max-w-[460px]"
        >
            <div><StatusTag :status="g.status" /></div>

            <div class="mt-3.5 overflow-hidden rounded-lg">
                <img
                    v-if="g.status === 'succeeded' && g.image_url && !expired"
                    :src="g.image_url"
                    :alt="g.prompt"
                    class="w-full rounded-lg border border-border"
                />
                <div
                    v-else-if="!isFinal(g)"
                    :style="{ aspectRatio: 1 / aspect(g.size) }"
                >
                    <GenerationSkeleton :generation="g" />
                </div>
                <div
                    v-else
                    class="grid place-items-center rounded-lg bg-[#f1f0ec] p-4 text-center text-xs text-muted-foreground"
                    :style="{ aspectRatio: 1 / aspect(g.size) }"
                >
                    {{
                        g.status === 'failed'
                            ? 'No image'
                            : 'Image expired (Pixelforge keeps images 24 hours)'
                    }}
                </div>
            </div>

            <SheetTitle
                class="mt-4 mb-1.5 font-serif text-2xl/[1.25] font-normal tracking-[-0.02em] break-words"
            >
                {{ g.prompt }}
            </SheetTitle>
            <SheetDescription class="text-xs text-muted-foreground">
                {{ g.size }} · {{ g.quality }} quality<template
                    v-if="g.reference_images.length"
                >
                    · {{ g.reference_images.length }} reference{{
                        g.reference_images.length > 1 ? 's' : ''
                    }}</template
                >
                · {{ new Date(g.created_at).toLocaleString() }}
            </SheetDescription>
            <ul
                v-if="g.reference_images.length"
                class="m-0 mt-2 list-none space-y-0.5 p-0 font-mono text-[11px] text-muted-foreground"
            >
                <li
                    v-for="url in g.reference_images"
                    :key="url"
                    class="truncate"
                    :title="url"
                >
                    {{ url }}
                </li>
            </ul>

            <div v-if="g.status === 'succeeded'" class="mt-5 grid gap-1">
                <div class="text-xs text-muted-foreground">Charged</div>
                <div class="font-serif text-[32px] tracking-[-0.02em]">
                    <Amount :nanodollars="g.charged" />
                </div>
                <div class="relative mt-3.5 mb-1.5 h-1 rounded-xs bg-[#f1f0ec]">
                    <i
                        class="absolute -top-[3px] h-2.5 w-0.5 bg-foreground"
                        :style="{ left: `${position * 100}%` }"
                    ></i>
                </div>
                <div
                    class="flex justify-between font-mono text-[11px] text-muted-foreground"
                >
                    <span
                        >est. low
                        {{ formatFourDecimals(g.estimate_low, 'down') }}</span
                    >
                    <span
                        >max
                        {{ formatFourDecimals(g.estimate_high, 'up') }}</span
                    >
                </div>
                <p
                    v-if="g.capped"
                    class="m-0 mt-1 text-xs text-muted-foreground"
                >
                    <span
                        class="rounded-full bg-tag-red px-2 py-0.5 text-[10.5px] font-semibold tracking-wider text-tag-red-foreground uppercase"
                        >capped</span
                    >
                    Pixelforge raised prices after you submitted. You paid the
                    max you were shown.
                </p>
                <p v-else class="m-0 mt-1 text-xs text-muted-foreground">
                    Landed {{ Math.round(position * 100) }}% of the way from the
                    low estimate to the max.
                </p>
            </div>

            <div v-else-if="g.status === 'failed'" class="mt-5 grid gap-1">
                <div class="text-xs text-muted-foreground">Charged</div>
                <div class="font-serif text-[32px] tracking-[-0.02em]">
                    $0.00
                </div>
                <p v-if="g.failure" class="m-0 mt-1 text-xs text-destructive">
                    <b>{{ g.failure.title }}.</b> {{ g.failure.body }}
                </p>
                <div class="mt-3">
                    <button
                        type="button"
                        class="cursor-pointer rounded-sm border-0 bg-primary px-4 py-2.5 font-medium text-primary-foreground hover:bg-[#333] active:scale-[.98]"
                        @click="emit('reuse', g, true)"
                    >
                        Try again
                    </button>
                </div>
            </div>

            <div v-else class="mt-5 grid gap-1">
                <div class="text-xs text-muted-foreground">
                    Set aside until it finishes
                </div>
                <div
                    class="font-serif text-[32px] tracking-[-0.02em] text-muted-foreground"
                >
                    ≤ <Amount :nanodollars="g.estimate_high" rounding="up" />
                </div>
                <p class="m-0 mt-1 text-xs text-muted-foreground">
                    Estimate {{ formatCents(g.estimate_low, 'down') }}–{{
                        formatCents(g.estimate_high, 'up')
                    }}.
                    <template v-if="g.status === 'waiting'"
                        >Pixelforge is busy; we keep trying for up to 10
                        minutes.</template
                    >
                </p>
            </div>

            <div v-if="g.status !== 'failed'" class="mt-6">
                <button
                    type="button"
                    class="cursor-pointer rounded-sm border border-border bg-transparent px-4 py-2.5 font-medium text-foreground hover:bg-accent"
                    @click="emit('reuse', g, false)"
                >
                    Use these settings
                </button>
            </div>
        </SheetContent>
    </Sheet>
</template>
