<script setup lang="ts">
import type { Form } from '@inertiajs/vue3';
import { MoreVertical, Pencil, Trash2 } from '@lucide/vue';
import { ref } from 'vue';
import ConfirmActionDialog from '@/components/ConfirmActionDialog.vue';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useTranslations } from '@/composables/useTranslations';

type FormBinding = InstanceType<typeof Form>['$props'];

/**
 * Row actions of the Operations list behind one "⋯" button, like the Budget
 * rows. The delete dialog lives outside the menu, because the menu content
 * unmounts when it closes.
 */
defineProps<{
    destroyForm: Pick<FormBinding, 'action' | 'method'>;
    deleteTitle: string;
    deleteDescription: string;
}>();

const emit = defineEmits<{
    edit: [];
}>();

const t = useTranslations();
const deleteDialogOpen = ref(false);
</script>

<template>
    <div class="flex justify-end">
        <DropdownMenu>
            <DropdownMenuTrigger as-child>
                <Button
                    type="button"
                    variant="ghost"
                    size="icon-sm"
                    :aria-label="t.budget.more_actions"
                >
                    <MoreVertical class="size-4" aria-hidden="true" />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end">
                <DropdownMenuItem @select="emit('edit')">
                    <Pencil class="size-4" aria-hidden="true" />
                    {{ t.accounts.edit }}
                </DropdownMenuItem>
                <DropdownMenuItem
                    class="text-destructive focus:text-destructive"
                    @select="deleteDialogOpen = true"
                >
                    <Trash2 class="size-4" aria-hidden="true" />
                    {{ t.accounts.delete }}
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>

        <ConfirmActionDialog
            v-model:open="deleteDialogOpen"
            :form="destroyForm"
            :title="deleteTitle"
            :description="deleteDescription"
            :confirm-label="t.accounts.delete"
            :cancel-label="t.accounts.cancel"
            destructive
        />
    </div>
</template>
