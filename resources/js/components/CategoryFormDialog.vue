<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import CategoryController from '@/actions/App/Http/Controllers/CategoryController';
import CategoryChip from '@/components/CategoryChip.vue';
import CategoryColorPicker from '@/components/CategoryColorPicker.vue';
import CategoryIconPicker from '@/components/CategoryIconPicker.vue';
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
    DEFAULT_CATEGORY_COLOR,
    DEFAULT_CATEGORY_ICON,
    type CategoryAppearanceOptions,
} from '@/lib/category-appearance';
import {
    minorUnitsToMoneyInput,
    parseMoneyInputToMinorUnits,
} from '@/lib/format';

export type CategoryFormCategory = {
    id: number;
    name: string;
    icon: string;
    color: string;
    type: 'income' | 'expense';
    planned_amount_minor_units: number;
    is_paced: boolean;
    category_variant?: 'paced' | 'one_off' | 'agenda' | 'ordinary';
};

const props = defineProps<{
    open: boolean;
    mode: 'create' | 'edit';
    budgetId: number;
    defaultType: 'income' | 'expense';
    category?: CategoryFormCategory | null;
    lockType?: boolean;
    categoryAppearanceOptions: CategoryAppearanceOptions;
}>();

const emit = defineEmits<{
    'update:open': [value: boolean];
}>();

const t = useTranslations();

const name = ref('');
const icon = ref(DEFAULT_CATEGORY_ICON);
const color = ref<string | null>(DEFAULT_CATEGORY_COLOR);
const type = ref<'income' | 'expense'>('income');
const plannedAmountInput = ref('0,00');
const plannedAmountMinorUnits = ref(0);
const categoryVariant = ref<'paced' | 'one_off' | 'agenda' | 'ordinary'>(
    'paced',
);

const formBinding = computed(() =>
    props.mode === 'create'
        ? CategoryController.store.form(props.budgetId)
        : CategoryController.update.form(props.category ?? { id: 0 }),
);

const previewName = computed(() => {
    const trimmed = name.value.trim();

    if (trimmed !== '') {
        return trimmed;
    }

    if (props.mode === 'edit' && props.category) {
        return props.category.name;
    }

    return t.budget.category_preview;
});

watch(
    () => [props.open, props.mode, props.category, props.defaultType] as const,
    ([open]) => {
        if (!open) {
            return;
        }

        if (props.mode === 'edit' && props.category) {
            name.value = props.category.name;
            icon.value = props.category.icon;
            color.value = props.category.color;
            type.value = props.category.type;
            plannedAmountMinorUnits.value =
                props.category.planned_amount_minor_units;
            plannedAmountInput.value = minorUnitsToMoneyInput(
                props.category.planned_amount_minor_units,
            );
            categoryVariant.value =
                props.category.category_variant ??
                (props.category.is_paced ? 'paced' : 'one_off');

            return;
        }

        name.value = '';
        icon.value = DEFAULT_CATEGORY_ICON;
        color.value = DEFAULT_CATEGORY_COLOR;
        type.value = props.defaultType;
        plannedAmountMinorUnits.value = 0;
        plannedAmountInput.value = '0,00';
        categoryVariant.value =
            props.defaultType === 'income' ? 'ordinary' : 'paced';
    },
    { immediate: true },
);

watch(type, (nextType) => {
    if (nextType === 'income') {
        if (
            categoryVariant.value !== 'agenda' &&
            categoryVariant.value !== 'ordinary'
        ) {
            categoryVariant.value = 'ordinary';
        }

        return;
    }

    if (
        categoryVariant.value !== 'paced' &&
        categoryVariant.value !== 'one_off' &&
        categoryVariant.value !== 'agenda'
    ) {
        categoryVariant.value = 'paced';
    }
});

function syncPlannedAmount(): void {
    const parsed = parseMoneyInputToMinorUnits(plannedAmountInput.value);

    if (parsed !== null) {
        plannedAmountMinorUnits.value = parsed;
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
                                : lockType
                                  ? t.budget.edit_category_description_execution
                                  : t.budget.edit_category_description
                        }}
                    </DialogDescription>
                </DialogHeader>

                <div
                    class="border-border/50 flex items-center gap-4 rounded-xl border p-4"
                >
                    <CategoryChip
                        v-if="color"
                        :name="previewName"
                        :icon="icon"
                        :color="color"
                    />
                    <p v-else class="text-muted-foreground text-sm">
                        {{ t.budget.category_preview_hint }}
                    </p>
                </div>

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
                        :disabled="lockType"
                        class="native-select"
                    >
                        <option value="income">
                            {{ t.budget.type_income }}
                        </option>
                        <option value="expense">
                            {{ t.budget.type_expense }}
                        </option>
                    </select>
                    <input
                        v-if="lockType"
                        type="hidden"
                        name="type"
                        :value="type"
                    />
                    <InputError :message="errors.type" />
                </div>

                <div class="grid gap-2">
                    <Label>{{ t.budget.category_color }}</Label>
                    <CategoryColorPicker
                        :colors="categoryAppearanceOptions.colors"
                        v-model="color"
                    />
                    <input type="hidden" name="color" :value="color ?? ''" />
                    <InputError :message="errors.color" />
                </div>

                <div class="grid gap-2">
                    <Label>{{ t.budget.category_icon }}</Label>
                    <CategoryIconPicker
                        :icons="categoryAppearanceOptions.icons"
                        v-model="icon"
                        :color="color ?? DEFAULT_CATEGORY_COLOR"
                    />
                    <input type="hidden" name="icon" :value="icon" />
                    <InputError :message="errors.icon" />
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
                        name="planned_amount_minor_units"
                        :value="plannedAmountMinorUnits"
                    />
                    <InputError :message="errors.planned_amount_minor_units" />
                </div>

                <div class="grid gap-2">
                    <Label for="category-variant">
                        {{ t.budget.category_variant_label }}
                    </Label>
                    <select
                        id="category-variant"
                        v-model="categoryVariant"
                        class="native-select w-full"
                        name="category_variant"
                    >
                        <template v-if="type === 'expense'">
                            <option value="paced">
                                {{ t.budget.category_variant_paced }}
                            </option>
                            <option value="one_off">
                                {{ t.budget.category_variant_one_off }}
                            </option>
                            <option value="agenda">
                                {{ t.budget.category_variant_agenda }}
                            </option>
                        </template>
                        <template v-else>
                            <option value="ordinary">
                                {{ t.budget.category_variant_ordinary }}
                            </option>
                            <option value="agenda">
                                {{ t.budget.category_variant_agenda }}
                            </option>
                        </template>
                    </select>
                    <p class="text-muted-foreground text-sm">
                        {{ t.budget.category_variant_hint }}
                    </p>
                    <InputError :message="errors.category_variant" />
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
