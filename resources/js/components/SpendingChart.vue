<script setup lang="ts">
import { computed } from 'vue';

/** A sparkline of what was charged per day. Amounts in nanodollars; only used for drawing. */
const props = defineProps<{ days: { date: string; amount: number }[] }>();

const WIDTH = 520;
const HEIGHT = 90;

const line = computed(() => {
    const max = Math.max(...props.days.map((d) => d.amount), 1);
    const step = WIDTH / Math.max(props.days.length - 1, 1);

    return props.days
        .map(
            (d, i) =>
                `${(i * step).toFixed(1)},${(HEIGHT - 6 - (d.amount / max) * (HEIGHT - 16)).toFixed(1)}`,
        )
        .join(' ');
});
</script>

<template>
    <svg
        :viewBox="`0 0 ${WIDTH} ${HEIGHT}`"
        width="100%"
        :height="HEIGHT"
        preserveAspectRatio="none"
        role="img"
        aria-label="Spending over the last 30 days"
    >
        <polyline
            :points="`0,${HEIGHT - 6} ${line} ${WIDTH},${HEIGHT - 6}`"
            fill="#f4f3ef"
        />
        <polyline
            :points="line"
            fill="none"
            stroke="currentColor"
            stroke-width="1.5"
            vector-effect="non-scaling-stroke"
        />
    </svg>
</template>
