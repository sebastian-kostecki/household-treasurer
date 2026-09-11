<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import CategoryController from '@/actions/App/Http/Controllers/CategoryController';
import type { BudgetCategoryRow } from '@/components/BudgetCategoryList.vue';
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

defineProps<{
    category: BudgetCategoryRow;
}>();

const open = defineModel<boolean>('open', { required: true });

const t = useTranslations();
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent>
            <Form
                v-bind="CategoryController.alignCurrentPlan.form(category)"
                v-slot="{ processing }"
            >
                <DialogHeader>
                    <DialogTitle>
                        {{ t.budget.align_current_plan_confirm_title }}
                    </DialogTitle>
                    <DialogDescription>
                        {{ t.budget.align_current_plan_confirm_body }}
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter class="gap-2">
                    <DialogClose as-child>
                        <Button type="button" variant="secondary">
                            {{ t.budget.cancel }}
                        </Button>
                    </DialogClose>
                    <Button type="submit" :disabled="processing">
                        {{ t.budget.align_current_plan }}
                    </Button>
                </DialogFooter>
            </Form>
        </DialogContent>
    </Dialog>
</template>
