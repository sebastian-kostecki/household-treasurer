<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { Pencil, Trash2 } from '@lucide/vue';
import ExpenseController from '@/actions/App/Http/Controllers/ExpenseController';
import IncomeController from '@/actions/App/Http/Controllers/IncomeController';
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

type OperationRow = {
    id: number;
    type: 'income' | 'expense';
};

const { operation } = defineProps<{
    operation: OperationRow;
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
                    v-bind="
                        operation.type === 'income'
                            ? IncomeController.destroy.form(operation)
                            : ExpenseController.destroy.form(operation)
                    "
                    v-slot="{ processing }"
                >
                    <DialogHeader>
                        <DialogTitle>
                            {{
                                operation.type === 'income'
                                    ? t.operations.delete_income_confirm_title
                                    : t.operations.delete_expense_confirm_title
                            }}
                        </DialogTitle>
                        <DialogDescription>
                            {{
                                operation.type === 'income'
                                    ? t.operations.delete_income_confirm_body
                                    : t.operations.delete_expense_confirm_body
                            }}
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
