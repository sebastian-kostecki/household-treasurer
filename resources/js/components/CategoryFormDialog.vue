<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import CategoryController from '@/actions/App/Http/Controllers/CategoryController';
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
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTranslations } from '@/composables/useTranslations';
import {
    groszeToMoneyInput,
    parseMoneyInputToGrosze,
} from '@/lib/format';

export type CategoryFormCategory = {
    id: number;
    name: string;
    type: 'income' | 'expense';
    planned_amount_grosze: number;
};

const props = defineProps<{
    open: boolean;
    mode: 'create' | 'edit';
    budgetId: number;
    defaultType: 'income' | 'expense';
    category?: CategoryFormCategory | null;
}>();

const emit = defineEmits<{
    'update:open': [value: boolean];
}>();

const t = useTranslations();

const name = ref('');
const type = ref<'income' | 'expense'>('income');
const plannedAmountInput = ref('0,00');
const plannedAmountGrosze = ref(0);

const formBinding = computed(() =>
    props.mode === 'create'
        ? CategoryController.store.form(props.budgetId)
        : CategoryController.update.form(props.category ?? { id: 0 }),
);

watch(
    () => [props.open, props.mode, props.category, props.defaultType] as const,
    ([open]) => {
        if (!open) {
            return;
        }

        if (props.mode === 'edit' && props.category) {
            name.value = props.category.name;
            type.value = props.category.type;
            plannedAmountGrosze.value = props.category.planned_amount_grosze;
            plannedAmountInput.value = groszeToMoneyInput(
                props.category.planned_amount_grosze,
            );

            return;
        }

        name.value = '';
        type.value = props.defaultType;
        plannedAmountGrosze.value = 0;
        plannedAmountInput.value = '0,00';
    },
    { immediate: true },
);

function syncPlannedAmount(): void {
    const parsed = parseMoneyInputToGrosze(plannedAmountInput.value);

    if (parsed !== null) {
        plannedAmountGrosze.value = parsed;
    }
}

function closeDialog(): void {
    emit('update:open', false);
}
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent>
            <Form
                v-bind="formBinding"
                class="space-y-4"
                reset-on-success
                @success="closeDialog"
                v-slot="{ errors, processing, reset, clearErrors }"
            >
                <DialogHeader>
                    <DialogTitle>
                        {{
                            mode === 'create'
                                ? t.budget.create_category_title
                                : t.budget.edit_category_title
                        }}
                    </DialogTitle>
                    <DialogDescription>
                        {{
                            mode === 'create'
                                ? t.budget.create_category_description
                                : t.budget.edit_category_description
                        }}
                    </DialogDescription>
                </DialogHeader>

                <div class="grid gap-2">
                    <Label for="category-name">{{ t.budget.name }}</Label>
                    <Input
                        id="category-name"
                        name="name"
                        v-model="name"
                        required
                        autocomplete="off"
                    />
                    <InputError :message="errors.name" />
                </div>

                <div class="grid gap-2">
                    <Label for="category-type">{{ t.budget.type }}</Label>
                    <select
                        id="category-type"
                        name="type"
                        v-model="type"
                        class="border-input bg-background ring-offset-background focus-visible:ring-ring flex h-9 w-full rounded-md border px-3 py-1 text-sm shadow-xs focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-hidden"
                    >
                        <option value="income">
                            {{ t.budget.type_income }}
                        </option>
                        <option value="expense">
                            {{ t.budget.type_expense }}
                        </option>
                    </select>
                    <InputError :message="errors.type" />
                </div>

                <div class="grid gap-2">
                    <Label for="category-planned-amount">
                        {{
                            type === 'income'
                                ? t.budget.planned_amount
                                : t.budget.limit
                        }}
                    </Label>
                    <Input
                        id="category-planned-amount"
                        v-model="plannedAmountInput"
                        inputmode="decimal"
                        autocomplete="off"
                        @blur="syncPlannedAmount"
                    />
                    <input
                        type="hidden"
                        name="planned_amount_grosze"
                        :value="plannedAmountGrosze"
                    />
                    <InputError :message="errors.planned_amount_grosze" />
                </div>

                <DialogFooter class="gap-2">
                    <DialogClose as-child>
                        <Button
                            type="button"
                            variant="secondary"
                            @click="
                                () => {
                                    clearErrors();
                                    reset();
                                    closeDialog();
                                }
                            "
                        >
                            {{ t.budget.cancel }}
                        </Button>
                    </DialogClose>
                    <Button type="submit" :disabled="processing">
                        {{ t.budget.save }}
                    </Button>
                </DialogFooter>
            </Form>
        </DialogContent>
    </Dialog>
</template>
