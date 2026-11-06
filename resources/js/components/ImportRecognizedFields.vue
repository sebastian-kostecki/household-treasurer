<script setup lang="ts">
import { computed } from 'vue';
import { useTranslations } from '@/composables/useTranslations';

const props = defineProps<{
    format: 'mbank' | 'bnp_paribas';
}>();

const t = useTranslations();

const title = computed(() =>
    props.format === 'mbank'
        ? t.operations.import_mbank_fields_title
        : t.operations.import_bnp_paribas_fields_title,
);

const fields = computed(() =>
    props.format === 'mbank'
        ? [
              t.operations.import_mbank_field_date,
              t.operations.import_mbank_field_description,
              t.operations.import_mbank_field_amount,
          ]
        : [
              t.operations.import_bnp_paribas_field_date,
              t.operations.import_bnp_paribas_field_counterparty,
              t.operations.import_bnp_paribas_field_description,
              t.operations.import_bnp_paribas_field_amount,
          ],
);
</script>

<template>
    <div class="form-preview">
        <p class="text-sm font-medium">{{ title }}</p>
        <ul class="form-preview-list">
            <li v-for="field in fields" :key="field">{{ field }}</li>
        </ul>
    </div>
</template>
