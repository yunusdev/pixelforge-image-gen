<script setup lang="ts">
import { computed } from 'vue';
import type { CentRounding } from '@/lib/money';
import { formatCents, formatExact } from '@/lib/money';

/** An amount in nanodollars, shown in cents with the exact value on hover. */
const props = withDefaults(
    defineProps<{ nanodollars: number; rounding?: CentRounding }>(),
    { rounding: 'nearest' },
);

const cents = computed(() => formatCents(props.nanodollars, props.rounding));
const exact = computed(() => formatExact(props.nanodollars));
</script>

<template>
    <span class="font-mono tabular-nums" :title="exact"
        >{{ cents }}<span class="sr-only"> (exactly {{ exact }})</span></span
    >
</template>
