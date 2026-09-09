<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { Equal } from '@lucide/vue';
import { ref } from 'vue';
import CategoryController from '@/actions/App/Http/Controllers/CategoryController';
import type { BudgetCategoryRow } from '@/components/BudgetCategoryList.vue';
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

const props = defineProps<{
    category: BudgetCategoryRow;
}>();

const t = useTranslations();

const dialogOpen = ref(false);

function openDialog(): void {
    dialogOpen.value = true;
}
</script>

<template>
    <IconAction
        v-if="category.can_align_current_plan"
        :icon="Equal"
        :label="t.budget.align_current_plan"
        @click="openDialog"
    />

    <Dialog v-model:open="dialogOpen">
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
