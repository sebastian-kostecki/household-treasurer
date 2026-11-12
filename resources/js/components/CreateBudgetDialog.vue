<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import BudgetController from '@/actions/App/Http/Controllers/BudgetController';
import FormField from '@/components/FormField.vue';
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
import { Spinner } from '@/components/ui/spinner';
import { useShell, useTranslations } from '@/composables/useTranslations';
import { activeCountForm } from '@/lib/category-list';
import { createBudgetSummary } from '@/lib/create-budget-summary';

type FormBinding = InstanceType<typeof Form>['$props'];

const props = withDefaults(
    defineProps<{
        open: boolean;
        yearInputId?: string;
        suggestedYear?: number;
        /** Replaces the computed default year each time the dialog opens. */
        initialYear?: number;
        /** Overrides the Wayfinder binding; used only by the dialogs kitchen sink. */
        form?: Pick<FormBinding, 'action' | 'method'>;
        /** Overrides the shell's budget years; used only by the dialogs kitchen sink. */
        budgetYears?: number[];
        /** Overrides the shell's active category count; used only by the dialogs kitchen sink. */
        activeCategoryCount?: number;
    }>(),
    {
        yearInputId: 'create-budget-year',
        suggestedYear: undefined,
        initialYear: undefined,
        form: undefined,
        budgetYears: undefined,
        activeCategoryCount: undefined,
    },
);

const emit = defineEmits<{
    'update:open': [value: boolean];
}>();

const shell = useShell();
const t = useTranslations();
const currentYear = new Date().getFullYear();

const defaultYear = computed(() => {
    if (props.initialYear !== undefined) {
        return props.initialYear;
    }

    if (props.suggestedYear !== undefined) {
        return props.suggestedYear;
    }

    return shell.value.budgetYears.length > 0 ? currentYear + 1 : currentYear;
});

const targetYear = ref(defaultYear.value);
const busy = ref(false);

const summaryText = computed((): string | undefined => {
    const summary = createBudgetSummary(
        targetYear.value,
        props.budgetYears ?? shell.value.budgetYears,
        props.activeCategoryCount ?? shell.value.activeCategoryCount,
    );

    if (summary === null) {
        return undefined;
    }

    if (summary.kind === 'empty') {
        return t.budget.create_budget_empty;
    }

    const form = activeCountForm(summary.count);
    const key =
        summary.kind === 'from-source'
            ? `create_budget_from_source_${form}`
            : `create_budget_no_source_${form}`;

    return t.budget[key]
        .replace(':count', String(summary.count))
        .replace(
            ':year',
            summary.kind === 'from-source' ? String(summary.sourceYear) : '',
        );
});

watch(
    defaultYear,
    (year) => {
        targetYear.value = year;
    },
    { immediate: true },
);

watch(
    () => props.open,
    (isOpen) => {
        if (isOpen && props.initialYear !== undefined) {
            targetYear.value = props.initialYear;
        }
    },
);

function onOpenChange(next: boolean): void {
    if (!next && busy.value) {
        return;
    }

    emit('update:open', next);
}

function closeDialog(): void {
    emit('update:open', false);
}
</script>

<template>
    <Dialog :open="open" @update:open="onOpenChange">
        <DialogTrigger v-if="$slots.trigger" as-child>
            <slot name="trigger" />
        </DialogTrigger>
        <DialogContent :show-close-button="!busy">
            <Form
                v-bind="form ?? BudgetController.store.form()"
                class="grid gap-4"
                reset-on-success
                @start="busy = true"
                @finish="busy = false"
                @success="closeDialog"
                v-slot="{ errors, processing, clearErrors }"
            >
                <DialogHeader>
                    <DialogTitle>
                        {{ t.budget.create_budget_title }}
                    </DialogTitle>
                    <DialogDescription>
                        {{ t.budget.create_budget_description }}
                    </DialogDescription>
                </DialogHeader>
                <FormField
                    :id="yearInputId"
                    :label="t.budget.year"
                    :hint="summaryText"
                    :error="errors.year"
                    v-slot="{ id, describedBy, invalid }"
                >
                    <Input
                        :id="id"
                        name="year"
                        type="number"
                        v-model.number="targetYear"
                        min="2000"
                        max="2100"
                        required
                        :aria-describedby="describedBy"
                        :aria-invalid="invalid"
                    />
                </FormField>
                <DialogFooter class="gap-2">
                    <DialogClose as-child>
                        <Button
                            type="button"
                            variant="ghost"
                            :disabled="processing"
                            @click="clearErrors()"
                        >
                            {{ t.budget.cancel }}
                        </Button>
                    </DialogClose>
                    <Button type="submit" :disabled="processing">
                        <Spinner v-if="processing" />
                        {{ t.budget.create_budget }}
                    </Button>
                </DialogFooter>
            </Form>
        </DialogContent>
    </Dialog>
</template>
