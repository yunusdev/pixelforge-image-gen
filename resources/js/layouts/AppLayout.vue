<script setup lang="ts">
import AppHeader from '@/components/AppHeader.vue';
import { Toaster } from '@/components/ui/sonner';
</script>

<template>
    <div class="min-h-svh bg-background">
        <AppHeader />
        <slot />
        <Toaster position="top-right" />
    </div>
</template>
