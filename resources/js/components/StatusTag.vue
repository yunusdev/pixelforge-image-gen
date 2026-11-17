<script setup lang="ts">
import { computed } from 'vue';

export type GenerationStatus =
    | 'waiting'
    | 'queued'
    | 'processing'
    | 'succeeded'
    | 'failed';

const props = defineProps<{ status: GenerationStatus }>();

const tags: Record<GenerationStatus, { label: string; class: string }> = {
    waiting: {
        label: 'Waiting for a slot',
        class: 'bg-tag-gray text-tag-gray-foreground',
    },
    queued: { label: 'Queued', class: 'bg-tag-blue text-tag-blue-foreground' },
    processing: {
        label: 'Processing',
        class: 'bg-tag-yellow text-tag-yellow-foreground',
    },
    succeeded: {
        label: 'Done',
        class: 'bg-tag-green text-tag-green-foreground',
    },
    failed: { label: 'Failed', class: 'bg-tag-red text-tag-red-foreground' },
};

const tag = computed(() => tags[props.status]);
</script>

<template>
    <span
        :class="[
            'inline-block rounded-full px-2 py-0.5 text-[10.5px] font-semibold tracking-wider uppercase',
            tag.class,
        ]"
        >{{ tag.label }}</span
    >
</template>
