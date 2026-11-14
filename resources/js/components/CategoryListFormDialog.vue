<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import CategoryListController from '@/actions/App/Http/Controllers/CategoryListController';
import CategoryAppearanceFields from '@/components/CategoryAppearanceFields.vue';
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
} from '@/components/ui/dialog';
import SegmentedControl from '@/components/SegmentedControl.vue';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { useTranslations } from '@/composables/useTranslations';
import {
    DEFAULT_CATEGORY_COLOR,
    DEFAULT_CATEGORY_ICON,
    type CategoryAppearanceOptions,
} from '@/lib/category-appearance';
import { CATEGORY_DESCRIPTION_MAX_LENGTH } from '@/lib/category-list';
import {
    minorUnitsToMoneyInput,
    parseMoneyInputToMinorUnits,
} from '@/lib/format';
import { MAX_PLAN_AMOUNT_MINOR_UNITS } from '@/lib/month-plan-amount';
import type { CategoryListItem } from '@/types/categories';
import type { Rhythm } from '@/types/rhythm';

type FormBinding = InstanceType<typeof Form>['$props'];

const props = defineProps<{
    open: boolean;
    mode: 'create' | 'edit';
    defaultType: 'income' | 'expense';
    category?: CategoryListItem | null;
    categoryAppearanceOptions: CategoryAppearanceOptions;
    /** The month a Rhythm change starts in (ADR-0072), shown in the hint. */
    rhythmChangeMonth: { year: number; month: number };
    /** Overrides the Wayfinder binding; used only by the dialogs kitchen sink. */
    form?: Pick<FormBinding, 'action' | 'method'>;
    /** Pre-fills the Opening Collected input; used only by the dialogs kitchen sink. */
    initialOpeningCollectedInput?: string;
}>();

const emit = defineEmits<{
    'update:open': [value: boolean];
}>();

const t = useTranslations();

const name = ref('');
const icon = ref(DEFAULT_CATEGORY_ICON);
const color = ref<string | null>(DEFAULT_CATEGORY_COLOR);
const type = ref<'income' | 'expense'>('expense');
const description = ref('');
const rhythm = ref<Rhythm>('running');
const openingCollectedInput = ref('0,00');
const busy = ref(false);

const lockType = computed(
    () =>
        props.mode === 'edit' &&
        props.category != null &&
        !props.category.can_change_type,
);

const formBinding = computed(() => {
    if (props.form) {
        return props.form;
    }

    return props.mode === 'create' || !props.category
        ? CategoryListController.store.form()
        : CategoryListController.update.form(props.category.id);
});

const rhythmOptions = computed(() => [
    {
        value: 'fixed_charge' as const,
        label: t.categories.rhythm_option_fixed_charge,
    },
    { value: 'running' as const, label: t.categories.rhythm_option_running },
    {
        value: 'even_annual' as const,
        label: t.categories.rhythm_option_even_annual,
    },
    {
        value: 'irregular' as const,
        label: t.categories.rhythm_option_irregular,
    },
]);

/** The Opening Collected field is shown, and sent, only for an Irregular expense (ADR-0073). */
const showsOpeningCollected = computed(
    () => type.value === 'expense' && rhythm.value === 'irregular',
);

/** An empty input is 0; an unreadable, negative or too large one blocks saving. */
const openingCollectedMinorUnits = computed<number | null>(() => {
    if (openingCollectedInput.value.trim() === '') {
        return 0;
    }

    const parsed = parseMoneyInputToMinorUnits(openingCollectedInput.value);

    return parsed !== null &&
        parsed >= 0 &&
        parsed <= MAX_PLAN_AMOUNT_MINOR_UNITS
        ? parsed
        : null;
});
const openingCollectedInvalid = computed(
    () =>
        showsOpeningCollected.value &&
        openingCollectedMinorUnits.value === null,
);

/** A Category that already is Irregular names the month its period started in. */
const openingCollectedHint = computed(() => {
    const since = props.category?.irregular_since;

    if (
        props.mode === 'edit' &&
        props.category?.rhythm === 'irregular' &&
        since
    ) {
        return t.categories.opening_collected_hint_edit
            .replace(':month', t.categories[`month_genitive_${since.month}`])
            .replace(':year', String(since.year));
    }

    return t.categories.opening_collected_hint_new;
});

const rhythmHint = computed(
    () =>
        `${t.categories[`rhythm_description_${rhythm.value}`]} ${t.categories.rhythm_hint
            .replace(
                ':month',
                t.categories[`month_genitive_${props.rhythmChangeMonth.month}`],
            )
            .replace(':year', String(props.rhythmChangeMonth.year))}`,
);

const descriptionHint = computed(() =>
    t.categories.description_counter
        .replace(':count', String(description.value.length))
        .replace(':max', String(CATEGORY_DESCRIPTION_MAX_LENGTH)),
);

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
            description.value = props.category.description ?? '';
            rhythm.value = props.category.rhythm ?? 'running';
            openingCollectedInput.value =
                props.initialOpeningCollectedInput ??
                minorUnitsToMoneyInput(
                    props.category.opening_collected_minor_units ?? 0,
                );

            return;
        }

        name.value = '';
        icon.value = DEFAULT_CATEGORY_ICON;
        color.value = DEFAULT_CATEGORY_COLOR;
        type.value = props.defaultType;
        description.value = '';
        rhythm.value = 'running';
        openingCollectedInput.value =
            props.initialOpeningCollectedInput ?? '0,00';
    },
    { immediate: true },
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
        <DialogContent :show-close-button="!busy">
            <Form
                v-bind="formBinding"
                class="grid gap-4"
                reset-on-success
                @start="busy = true"
                @finish="busy = false"
                @success="closeDialog"
                v-slot="{ errors, processing, reset, clearErrors }"
            >
                <DialogHeader>
                    <DialogTitle>
                        {{
                            mode === 'create'
                                ? t.categories.create_title
                                : t.categories.edit_title
                        }}
                    </DialogTitle>
                    <DialogDescription>
                        {{
                            mode === 'create'
                                ? t.categories.create_description
                                : t.categories.edit_description
                        }}
                    </DialogDescription>
                </DialogHeader>

                <CategoryAppearanceFields
                    v-model:name="name"
                    v-model:type="type"
                    v-model:color="color"
                    v-model:icon="icon"
                    :category-appearance-options="categoryAppearanceOptions"
                    :errors="errors"
                    :fallback-name="
                        mode === 'edit' && category
                            ? category.name
                            : t.budget.category_preview
                    "
                    :lock-type="lockType"
                    :type-hint="t.categories.type_locked_hint"
                />

                <FormField
                    id="category-description"
                    :label="t.categories.description"
                    :hint="descriptionHint"
                    :error="errors.description"
                    v-slot="{ id, describedBy, invalid }"
                >
                    <Textarea
                        :id="id"
                        v-model="description"
                        name="description"
                        rows="3"
                        :maxlength="CATEGORY_DESCRIPTION_MAX_LENGTH"
                        :aria-describedby="describedBy"
                        :aria-invalid="invalid"
                    />
                </FormField>

                <FormField
                    v-if="type === 'expense'"
                    id="category-rhythm"
                    :label="t.categories.rhythm"
                    group
                    :hint="rhythmHint"
                    :error="errors.rhythm"
                    v-slot="{ labelId, describedBy, invalid }"
                >
                    <SegmentedControl
                        v-model="rhythm"
                        :options="rhythmOptions"
                        :ariaLabelledby="labelId"
                        :ariaDescribedby="describedBy"
                        :ariaInvalid="invalid"
                        name="rhythm"
                        block
                    />
                </FormField>

                <FormField
                    v-if="showsOpeningCollected"
                    id="category-opening-collected"
                    :label="t.categories.opening_collected"
                    money
                    :hint="openingCollectedHint"
                    :error="
                        openingCollectedInvalid
                            ? t.categories.opening_collected_invalid
                            : errors.opening_collected_minor_units
                    "
                    v-slot="{ id, describedBy, invalid, controlClass }"
                >
                    <Input
                        :id="id"
                        v-model="openingCollectedInput"
                        :class="controlClass"
                        inputmode="decimal"
                        autocomplete="off"
                        :aria-describedby="describedBy"
                        :aria-invalid="invalid"
                    />
                    <input
                        type="hidden"
                        name="opening_collected_minor_units"
                        :value="openingCollectedMinorUnits ?? 0"
                    />
                </FormField>

                <DialogFooter class="gap-2">
                    <DialogClose as-child>
                        <Button
                            type="button"
                            variant="ghost"
                            :disabled="processing"
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
                    <Button
                        type="submit"
                        :disabled="processing || openingCollectedInvalid"
                    >
                        <Spinner v-if="processing" />
                        {{ t.budget.save }}
                    </Button>
                </DialogFooter>
            </Form>
        </DialogContent>
    </Dialog>
</template>
