<script setup lang="ts">
import { computed, inject } from 'vue';
import OwnTransferController from '@/actions/App/Http/Controllers/OwnTransferController';
import OperationRowMenu from '@/components/OperationRowMenu.vue';
import { useTranslations } from '@/composables/useTranslations';
import { OPERATIONS_RETURN_QUERY } from '@/lib/operations-list-query';

type TransferRow = {
    id: number;
};

const { transfer } = defineProps<{
    transfer: TransferRow;
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
            OwnTransferController.destroy.form(transfer, writeOptions)
        "
        :delete-title="t.operations.delete_transfer_confirm_title"
        :delete-description="t.operations.delete_transfer_confirm_body"
        @edit="emit('edit')"
    />
</template>
