<script setup lang="ts">
import { useId } from 'vue';
import InputError from '@/components/InputError.vue';
import { Checkbox } from '@/components/ui/checkbox';
import { useTranslations } from '@/composables/useTranslations';

const modelValue = defineModel<boolean>({ required: true });

const { error } = defineProps<{
    error?: string;
}>();

const t = useTranslations();
const checkboxId = useId();
const errorId = `${checkboxId}-error`;
</script>

<template>
    <div class="flex flex-col gap-1">
        <div class="form-check-row">
            <Checkbox
                :id="checkboxId"
                v-model="modelValue"
                name="duplicate_warning_acknowledged"
                value="1"
                :aria-describedby="error ? errorId : undefined"
                :aria-invalid="error ? 'true' : undefined"
            />
            <label :for="checkboxId">
                {{ t.operations.import_duplicate_acknowledge }}
            </label>
        </div>
        <InputError :id="errorId" :message="error" size="xs" />
    </div>
</template>
