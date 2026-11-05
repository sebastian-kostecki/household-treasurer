<script setup lang="ts">
import CapitalMovementController from '@/actions/App/Http/Controllers/CapitalMovementController';
import OperationRowMenu from '@/components/OperationRowMenu.vue';
import { useTranslations } from '@/composables/useTranslations';

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
</script>

<template>
    <OperationRowMenu
        :destroy-form="CapitalMovementController.destroy.form(movement)"
        :delete-title="t.operations.delete_capital_movement_confirm_title"
        :delete-description="t.operations.delete_capital_movement_confirm_body"
        @edit="emit('edit')"
    />
</template>
