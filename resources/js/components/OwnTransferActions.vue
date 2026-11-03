<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { Pencil, Trash2 } from '@lucide/vue';
import OwnTransferController from '@/actions/App/Http/Controllers/OwnTransferController';
import IconAction from '@/components/IconAction.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';
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
    <div class="flex justify-end gap-1">
        <IconAction
            :icon="Pencil"
            :label="t.accounts.edit"
            @click="emit('edit')"
        />
        <Dialog>
            <IconAction
                as-dialog-trigger
                :icon="Trash2"
                :label="t.accounts.delete"
                destructive
            />
            <DialogContent>
                <Form
                    v-bind="OwnTransferController.destroy.form(transfer)"
                    v-slot="{ processing }"
                >
                    <DialogHeader>
                        <DialogTitle>
                            {{ t.operations.delete_transfer_confirm_title }}
                        </DialogTitle>
                        <DialogDescription>
                            {{ t.operations.delete_transfer_confirm_body }}
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter class="gap-2">
                        <DialogClose as-child>
                            <Button type="button" variant="secondary">
                                {{ t.accounts.cancel }}
                            </Button>
                        </DialogClose>
                        <Button
                            type="submit"
                            variant="destructive"
                            :disabled="processing"
                        >
                            <Spinner
                                v-if="processing"
                                :aria-label="t.common.loading"
                            />
                            {{ t.accounts.delete }}
                        </Button>
                    </DialogFooter>
                </Form>
            </DialogContent>
        </Dialog>
    </div>
</template>
