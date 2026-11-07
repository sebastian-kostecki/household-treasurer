<script setup lang="ts">
import OwnTransferController from '@/actions/App/Http/Controllers/OwnTransferController';
import OperationRowMenu from '@/components/OperationRowMenu.vue';
import { useTranslations } from '@/composables/useTranslations';

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
</script>

<template>
    <OperationRowMenu
        :destroy-form="OwnTransferController.destroy.form(transfer)"
        :delete-title="t.operations.delete_transfer_confirm_title"
        :delete-description="t.operations.delete_transfer_confirm_body"
        @edit="emit('edit')"
    />
</template>
