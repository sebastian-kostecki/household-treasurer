<script setup lang="ts">
import { computed, useId } from 'vue';
import InputError from '@/components/InputError.vue';
import { MONEY_UNIT } from '@/lib/format';

const props = defineProps<{
    label: string;
    id?: string;
    hint?: string;
    error?: string;
    group?: boolean;
    money?: boolean;
}>();

const generatedId = useId();
const fieldId = computed(() => props.id ?? generatedId);
const labelId = computed(() => `${fieldId.value}-label`);
const hintId = computed(() => `${fieldId.value}-hint`);
const errorId = computed(() => `${fieldId.value}-error`);

const describedBy = computed(() => {
    const ids = [
        props.hint ? hintId.value : null,
        props.error ? errorId.value : null,
    ].filter((value) => value !== null);

    return ids.length > 0 ? ids.join(' ') : undefined;
});

const invalid = computed(() => (props.error ? ('true' as const) : undefined));
const controlClass = computed(() =>
    props.money ? 'pr-8 text-right font-mono tabular-nums' : '',
);
</script>

<template>
    <div class="form-field">
        <label
            :id="labelId"
            :for="group ? undefined : fieldId"
            class="form-field-label"
            >{{ label }}</label
        >
        <div v-if="money" class="relative">
            <slot
                :id="fieldId"
                :label-id="labelId"
                :described-by="describedBy"
                :invalid="invalid"
                :control-class="controlClass"
            />
            <span class="form-field-unit" aria-hidden="true">{{
                MONEY_UNIT
            }}</span>
        </div>
        <slot
            v-else
            :id="fieldId"
            :label-id="labelId"
            :described-by="describedBy"
            :invalid="invalid"
            :control-class="controlClass"
        />
        <p v-if="hint" :id="hintId" class="text-muted-foreground text-xs/4">
            {{ hint }}
        </p>
        <InputError :id="errorId" :message="error" size="xs" />
    </div>
</template>
