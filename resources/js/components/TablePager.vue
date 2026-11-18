<script setup lang="ts">
/** "Newest first · showing 1–20 of 41 images" with Previous / Next for a Laravel paginator. */
defineProps<{
    paginator: {
        current_page: number;
        last_page: number;
        from: number | null;
        to: number | null;
        total: number;
    };
    order: string;
    noun: string;
}>();

defineEmits<{ page: [number: number] }>();
</script>

<template>
    <div
        class="flex flex-wrap items-center justify-between gap-3 px-3.5 py-3 text-xs text-muted-foreground"
    >
        <span
            >{{ order }} · showing {{ paginator.from }}–{{ paginator.to }} of
            {{ paginator.total }} {{ noun }}</span
        >
        <span v-if="paginator.last_page > 1" class="flex items-center gap-2.5">
            <button
                type="button"
                class="chip disabled:cursor-not-allowed disabled:opacity-45"
                :disabled="paginator.current_page <= 1"
                @click="$emit('page', paginator.current_page - 1)"
            >
                Previous
            </button>
            <span
                >Page {{ paginator.current_page }} of
                {{ paginator.last_page }}</span
            >
            <button
                type="button"
                class="chip disabled:cursor-not-allowed disabled:opacity-45"
                :disabled="paginator.current_page >= paginator.last_page"
                @click="$emit('page', paginator.current_page + 1)"
            >
                Next
            </button>
        </span>
    </div>
</template>
