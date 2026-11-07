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
import type { CopyableBudgetSource } from '@/types/shell';

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
    }>(),
    {
        yearInputId: 'create-budget-year',
        suggestedYear: undefined,
        initialYear: undefined,
        form: undefined,
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
const sourceBudgetId = ref('');
const busy = ref(false);

const availableSources = computed((): CopyableBudgetSource[] =>
    shell.value.copyableBudgetSources.filter(
        (source) => source.year < targetYear.value,
    ),
);

const showSourceSelect = computed(() => availableSources.value.length > 0);

function resolveDefaultSourceBudgetId(year: number): string {
    const sources = shell.value.copyableBudgetSources.filter(
        (source) => source.year < year,
    );

    if (sources.length === 0) {
        return '';
    }

    const previousYearSource = sources.find(
        (source) => source.year === year - 1,
    );

    if (previousYearSource !== undefined) {
        return String(previousYearSource.id);
    }

    const newestSource = sources.reduce((newest, source) =>
        source.year > newest.year ? source : newest,
    );

    return String(newestSource.id);
}

watch(
    defaultYear,
    (year) => {
        targetYear.value = year;
        sourceBudgetId.value = resolveDefaultSourceBudgetId(year);
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

watch(targetYear, (year) => {
    sourceBudgetId.value = resolveDefaultSourceBudgetId(year);
});

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
                <FormField
                    v-if="showSourceSelect"
                    id="create-budget-source"
                    :label="t.budget.copy_source_label"
                    :hint="t.budget.copy_source_description"
                    :error="errors.source_budget_id"
                    v-slot="{ id, describedBy, invalid }"
                >
                    <select
                        :id="id"
                        v-model="sourceBudgetId"
                        class="native-select"
                        :aria-describedby="describedBy"
                        :aria-invalid="invalid"
                    >
                        <option value="">
                            {{ t.budget.copy_source_none }}
                        </option>
                        <option
                            v-for="source in availableSources"
                            :key="source.id"
                            :value="String(source.id)"
                        >
                            {{ source.year }}
                        </option>
                    </select>
                    <input
                        v-if="sourceBudgetId !== ''"
                        type="hidden"
                        name="source_budget_id"
                        :value="sourceBudgetId"
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
