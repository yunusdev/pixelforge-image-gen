<script setup lang="ts">
import { computed } from 'vue';
import Amount from '@/components/Amount.vue';
import GenerationSkeleton from '@/components/GenerationSkeleton.vue';
import StatusTag from '@/components/StatusTag.vue';
import type { Generation } from '@/types/generation';
import { aspect, imageExpired, isFinal } from '@/types/generation';

/** One image in the feed: picture or loading state, status tag, prompt and price. */
const props = defineProps<{ generation: Generation }>();
defineEmits<{ open: [] }>();

const running = computed(() => !isFinal(props.generation));
const expired = computed(() => imageExpired(props.generation));
const showImage = computed(
    () =>
        props.generation.status === 'succeeded' &&
        props.generation.image_url !== null &&
        !expired.value,
);
</script>

<template>
    <button
        type="button"
        class="block w-full cursor-pointer overflow-hidden rounded-[10px] border border-border bg-card p-0 text-left transition-shadow hover:shadow-[0_2px_8px_rgba(0,0,0,.04)]"
        :aria-label="`Open details: ${generation.prompt}`"
        @click="$emit('open')"
    >
        <div
            class="relative grid place-items-center bg-[#f1f0ec] text-center text-xs text-muted-foreground"
        >
            <img
                v-if="showImage"
                :src="generation.image_url!"
                :alt="generation.prompt"
                class="block w-full saturate-[.8]"
                loading="lazy"
            />
            <div
                v-else-if="running"
                class="w-full"
                :style="{ aspectRatio: 1 / aspect(generation.size) }"
            >
                <GenerationSkeleton :generation="generation" />
            </div>
            <div
                v-else
                class="grid w-full place-items-center p-4"
                :class="generation.status === 'failed' ? 'h-24' : ''"
                :style="
                    generation.status === 'failed'
                        ? undefined
                        : { aspectRatio: 1 / aspect(generation.size) }
                "
            >
                {{
                    generation.status === 'failed'
                        ? 'No image'
                        : 'Image expired'
                }}
            </div>
            <StatusTag
                :status="generation.status"
                class="absolute top-2.5 left-2.5"
            />
        </div>

        <div class="grid grid-cols-[1fr_auto] items-start gap-2.5 px-3.5 py-3">
            <p class="m-0 line-clamp-2 text-[13px]/[1.4]">
                {{ generation.prompt }}
            </p>
            <div class="text-right font-mono text-xs">
                <template v-if="generation.status === 'succeeded'">
                    <Amount :nanodollars="generation.charged" />
                    <small class="block text-[10.5px] text-muted-foreground">{{
                        generation.capped ? 'capped' : 'charged'
                    }}</small>
                </template>
                <template v-else-if="generation.status === 'failed'">
                    $0.00
                    <small class="block text-[10.5px] text-muted-foreground"
                        >free</small
                    >
                </template>
                <template v-else>
                    ≤
                    <Amount
                        :nanodollars="generation.estimate_high"
                        rounding="up"
                    />
                    <small class="block text-[10.5px] text-muted-foreground"
                        >max</small
                    >
                </template>
            </div>
        </div>

        <div
            v-if="generation.failure"
            class="border-t border-border bg-tag-red px-3.5 py-2.5 text-xs text-tag-red-foreground"
        >
            {{ generation.failure.title }}
        </div>
    </button>
</template>
