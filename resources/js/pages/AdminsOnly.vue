<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { create } from '@/routes';
</script>

<template>
    <Head title="Admins only" />
    <main class="px-3 pt-9 pb-30 sm:px-7">
        <section
            class="mx-auto mt-10 flex max-w-160 flex-col items-center rounded-xl border border-border bg-card p-10 text-center"
        >
            <h1 class="m-0 mb-2 text-3xl font-normal tracking-[-0.02em]">
                Admins only
            </h1>
            <p class="m-0 mb-5 text-muted-foreground">
                This report shows every user's spending, so it's limited to
                operators. If you need access, ask an admin to run the
                make-admin command for your account.
            </p>
            <Link
                :href="create()"
                class="rounded-sm bg-primary px-4 py-2.5 font-medium text-primary-foreground no-underline hover:bg-[#333]"
                >Back to your images</Link
            >
        </section>
    </main>
</template>
