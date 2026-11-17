<script setup lang="ts">
import { useNow } from '@vueuse/core';
import { computed } from 'vue';
import type { Generation } from '@/types/generation';

/**
 * The loading tile for a generation still running: a slow sweep while waiting for a
 * slot, a faster sweep when queued, drifting colour blobs while processing, and a
 * pill with how long it has been at it. Motion stops under reduced motion.
 */
const props = defineProps<{
    generation: Pick<Generation, 'status' | 'created_at' | 'started_at'>;
}>();

const labels: Partial<Record<Generation['status'], string>> = {
    waiting: 'Pixelforge busy, retrying',
    queued: 'Starting soon',
    processing: 'Drawing',
};

const now = useNow({ interval: 1000 });

const elapsed = computed(() => {
    const since =
        props.generation.status === 'processing'
            ? (props.generation.started_at ?? props.generation.created_at)
            : props.generation.created_at;
    const seconds = Math.max(
        0,
        Math.round((now.value.getTime() - new Date(since).getTime()) / 1000),
    );

    return seconds < 60
        ? `${seconds}s`
        : `${Math.floor(seconds / 60)}m ${seconds % 60}s`;
});
</script>

<template>
    <div class="skeleton" :class="`skeleton-${generation.status}`">
        <template v-if="generation.status === 'processing'">
            <span class="blob blob-1"></span>
            <span class="blob blob-2"></span>
            <span class="blob blob-3"></span>
        </template>
        <span
            class="absolute bottom-3 left-1/2 z-2 flex -translate-x-1/2 items-center gap-1.5 rounded-full bg-white/80 px-3 py-1 text-xs whitespace-nowrap text-foreground backdrop-blur-xs"
        >
            {{ labels[generation.status] }}
            <span class="dots" aria-hidden="true"><i></i><i></i><i></i></span>
            <span class="font-mono text-[11px] text-muted-foreground">{{
                elapsed
            }}</span>
        </span>
    </div>
</template>

<style scoped>
.skeleton {
    position: relative;
    overflow: hidden;
    width: 100%;
    height: 100%;
    background: #eceae4;
}
.skeleton::after {
    content: '';
    position: absolute;
    inset: 0;
    background: linear-gradient(
        100deg,
        transparent 25%,
        rgba(255, 255, 255, 0.7) 50%,
        transparent 75%
    );
    transform: translateX(-100%);
    animation: sweep 1.8s cubic-bezier(0.4, 0, 0.2, 1) infinite;
}
.skeleton-waiting {
    background: #efeeea;
}
.skeleton-waiting::after {
    animation-duration: 2.8s;
    opacity: 0.7;
}
.skeleton-processing {
    background: #e9e5dd;
}
.blob {
    position: absolute;
    width: 62%;
    aspect-ratio: 1;
    border-radius: 50%;
    filter: blur(30px);
    opacity: 0.85;
    will-change: transform;
}
.blob-1 {
    background: #e2cfb4;
    left: -10%;
    top: -15%;
    animation: drift-1 7s ease-in-out infinite alternate;
}
.blob-2 {
    background: #c9d8e3;
    right: -15%;
    top: 20%;
    animation: drift-2 8s ease-in-out infinite alternate;
}
.blob-3 {
    background: #e3cad3;
    left: 20%;
    bottom: -25%;
    animation: drift-3 9s ease-in-out infinite alternate;
}
.dots {
    display: inline-flex;
    gap: 3px;
}
.dots i {
    width: 4px;
    height: 4px;
    border-radius: 50%;
    background: currentColor;
    opacity: 0.25;
    animation: dot 1.2s infinite;
}
.dots i:nth-child(2) {
    animation-delay: 0.2s;
}
.dots i:nth-child(3) {
    animation-delay: 0.4s;
}
@keyframes sweep {
    to {
        transform: translateX(100%);
    }
}
@keyframes drift-1 {
    to {
        transform: translate(28%, 22%) scale(1.15);
    }
}
@keyframes drift-2 {
    to {
        transform: translate(-30%, -12%) scale(0.9);
    }
}
@keyframes drift-3 {
    to {
        transform: translate(12%, -30%) scale(1.2);
    }
}
@keyframes dot {
    40% {
        opacity: 1;
    }
}
@media (prefers-reduced-motion: reduce) {
    .skeleton::after {
        display: none;
    }
    .blob,
    .dots i {
        animation: none;
    }
}
</style>
