<script setup lang="ts">
import { computed, inject } from 'vue';
import CapitalMovementController from '@/actions/App/Http/Controllers/CapitalMovementController';
import OperationRowMenu from '@/components/OperationRowMenu.vue';
import { useTranslations } from '@/composables/useTranslations';
import { OPERATIONS_RETURN_QUERY } from '@/lib/operations-list-query';

type MovementRow = {
    id: number;
};

const { movement } = defineProps<{
    movement: MovementRow;
}>();

const emit = defineEmits<{
    edit: [];
}>();

const t = useTranslations();
const operationsReturnQuery = inject(OPERATIONS_RETURN_QUERY, undefined);
const writeOptions = computed(() =>
    operationsReturnQuery ? { query: operationsReturnQuery.value } : undefined,
);
</script>

<template>
    <OperationRowMenu
        :destroy-form="
            CapitalMovementController.destroy.form(movement, writeOptions)
        "
        :delete-title="t.operations.delete_capital_movement_confirm_title"
        :delete-description="t.operations.delete_capital_movement_confirm_body"
        @edit="emit('edit')"
    />
</template>
