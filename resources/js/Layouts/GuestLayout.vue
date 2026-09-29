<script setup>
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <div class="guest-layout">
        <div class="guest-card">
            <div>
                <Link href="/">
                    <ApplicationLogo class="w-20 h-10 fill-current text-gray-500 my-6" style="width: 360px; height: 120px;" />
                </Link>
            </div>
            <slot />
        </div>
    </div>
</template>
