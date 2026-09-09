<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { Pencil, Trash2 } from '@lucide/vue';
import CapitalMovementController from '@/actions/App/Http/Controllers/CapitalMovementController';
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
                    v-bind="CapitalMovementController.destroy.form(movement)"
                    v-slot="{ processing }"
                >
                    <DialogHeader>
                        <DialogTitle>
                            {{
                                t.operations
                                    .delete_capital_movement_confirm_title
                            }}
                        </DialogTitle>
                        <DialogDescription>
                            {{
                                t.operations
                                    .delete_capital_movement_confirm_body
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
                            {{ t.accounts.delete }}
                        </Button>
                    </DialogFooter>
                </Form>
            </DialogContent>
        </Dialog>
    </div>
</template>
