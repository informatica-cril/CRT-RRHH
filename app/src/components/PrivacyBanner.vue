<template>
  <div class="privacy-banner" v-if="visible">
    <div class="privacy-banner-content">
      <div class="privacy-banner-text">
        <h3>🔒 {{ t('privacy_banner_title') }}</h3>
        <p>{{ t('privacy_banner_text') }}</p>
        <p style="margin-top: 4px; font-size: 0.78rem;">
          {{ t('data_controller') }}<br/>
          {{ t('legal_basis') }}
        </p>
      </div>
      <div class="privacy-banner-actions">
        <button class="btn btn-outline" @click="$emit('reject')">{{ t('reject_optional') }}</button>
        <button class="btn btn-primary" @click="$emit('accept')">{{ t('accept_all') }}</button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { i18n } from '../i18n'
const t = (key) => i18n.t(key)
const props = defineProps({ visible: { type: Boolean, default: true } })
defineEmits(['accept', 'reject'])
</script>
