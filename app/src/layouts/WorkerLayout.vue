<template>
  <div class="app-layout">
    <WorkerSidebar />
    <main class="main-content">
      <RouterView />
    </main>
  </div>
</template>

<script setup>
import WorkerSidebar from '../components/layout/WorkerSidebar.vue'
</script>
