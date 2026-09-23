<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
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
import type { CopyableBudgetSource } from '@/types/shell';

const props = withDefaults(
    defineProps<{
        open: boolean;
        yearInputId?: string;
        suggestedYear?: number;
    }>(),
    {
        yearInputId: 'create-budget-year',
        suggestedYear: undefined,
    },
);

const emit = defineEmits<{
    'update:open': [value: boolean];
}>();

const shell = useShell();
const t = useTranslations();
const currentYear = new Date().getFullYear();

const defaultYear = computed(() => {
    if (props.suggestedYear !== undefined) {
        return props.suggestedYear;
    }

    return shell.budgetYears.length > 0 ? currentYear + 1 : currentYear;
});

const targetYear = ref(defaultYear.value);
const sourceBudgetId = ref('');

const availableSources = computed((): CopyableBudgetSource[] =>
    shell.copyableBudgetSources.filter(
        (source) => source.year < targetYear.value,
    ),
);

const showSourceSelect = computed(() => availableSources.value.length > 0);

function resolveDefaultSourceBudgetId(year: number): string {
    const sources = shell.copyableBudgetSources.filter(
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

watch(targetYear, (year) => {
    sourceBudgetId.value = resolveDefaultSourceBudgetId(year);
});

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
                        v-model.number="targetYear"
                        min="2000"
                        max="2100"
                        required
                    />
                    <InputError :message="errors.year" />
                </div>
                <div v-if="showSourceSelect" class="grid gap-2">
                    <Label for="create-budget-source">
                        {{ t.budget.copy_source_label }}
                    </Label>
                    <p class="text-muted-foreground text-sm">
                        {{ t.budget.copy_source_description }}
                    </p>
                    <select
                        id="create-budget-source"
                        v-model="sourceBudgetId"
                        class="native-select"
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
                    <InputError :message="errors.source_budget_id" />
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
