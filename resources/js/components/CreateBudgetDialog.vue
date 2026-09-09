<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { computed } from 'vue';
import BudgetController from '@/actions/App/Http/Controllers/BudgetController';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useShell, useTranslations } from '@/composables/useTranslations';

const props = withDefaults(
    defineProps<{
        open: boolean;
        yearInputId?: string;
    }>(),
    {
        yearInputId: 'create-budget-year',
    },
);

const emit = defineEmits<{
    'update:open': [value: boolean];
}>();

const shell = useShell();
const t = useTranslations();
const currentYear = new Date().getFullYear();

const defaultYear = computed(() =>
    shell.budgetYears.length > 0 ? currentYear + 1 : currentYear,
);

function closeDialog(): void {
    emit('update:open', false);
}
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogTrigger v-if="$slots.trigger" as-child>
            <slot name="trigger" />
        </DialogTrigger>
        <DialogContent>
            <Form
                v-bind="BudgetController.store.form()"
                class="space-y-4"
                reset-on-success
                @success="closeDialog"
                v-slot="{ errors, processing }"
            >
                <DialogHeader>
                    <DialogTitle>
                        {{ t.budget.create_budget_title }}
                    </DialogTitle>
                    <DialogDescription>
                        {{ t.budget.create_budget_description }}
                    </DialogDescription>
                </DialogHeader>
                <div class="grid gap-2">
                    <Label :for="yearInputId">{{ t.budget.year }}</Label>
                    <Input
                        :id="yearInputId"
                        name="year"
                        type="number"
                        :default-value="defaultYear"
                        min="2000"
                        max="2100"
                        required
                    />
                    <InputError :message="errors.year" />
                </div>
                <DialogFooter class="gap-2">
                    <DialogClose as-child>
                        <Button type="button" variant="secondary">
                            {{ t.budget.cancel }}
                        </Button>
                    </DialogClose>
                    <Button type="submit" :disabled="processing">
                        {{ t.budget.create_budget }}
                    </Button>
                </DialogFooter>
            </Form>
        </DialogContent>
    </Dialog>
</template>
