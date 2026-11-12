<script setup lang="ts">
import { computed } from 'vue';
import CategoryChip from '@/components/CategoryChip.vue';
import CategoryColorPicker from '@/components/CategoryColorPicker.vue';
import CategoryIconPicker from '@/components/CategoryIconPicker.vue';
import FormField from '@/components/FormField.vue';
import SegmentedControl from '@/components/SegmentedControl.vue';
import { Input } from '@/components/ui/input';
import { useTranslations } from '@/composables/useTranslations';
import {
    DEFAULT_CATEGORY_COLOR,
    type CategoryAppearanceOptions,
} from '@/lib/category-appearance';

/**
 * The fields every Category dialog shares: the chip preview, name, type, colour
 * and icon (one source for the Budget and the „Kategorie” dialogs).
 */
const props = defineProps<{
    categoryAppearanceOptions: CategoryAppearanceOptions;
    errors: Record<string, string | undefined>;
    /** The preview name while the name input is empty. */
    fallbackName: string;
    lockType?: boolean;
    typeHint?: string;
}>();

const name = defineModel<string>('name', { required: true });
const type = defineModel<'income' | 'expense'>('type', { required: true });
const color = defineModel<string | null>('color', { required: true });
const icon = defineModel<string>('icon', { required: true });

const t = useTranslations();

const typeOptions = computed(() => [
    { value: 'income' as const, label: t.budget.type_income },
    { value: 'expense' as const, label: t.budget.type_expense },
]);

const previewName = computed(() => {
    const trimmed = name.value.trim();

    return trimmed !== '' ? trimmed : props.fallbackName;
});
</script>

<template>
    <div class="grid gap-4">
        <div class="bg-background flex items-center gap-4 rounded-md p-4">
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

        <FormField
            id="category-name"
            :label="t.budget.name"
            :error="errors.name"
            v-slot="{ id, describedBy, invalid }"
        >
            <Input
                :id="id"
                name="name"
                v-model="name"
                required
                autocomplete="off"
                :aria-describedby="describedBy"
                :aria-invalid="invalid"
            />
        </FormField>

        <FormField
            id="category-type"
            :label="t.budget.type"
            group
            :hint="lockType ? typeHint : undefined"
            :error="errors.type"
            v-slot="{ labelId, describedBy, invalid }"
        >
            <SegmentedControl
                v-model="type"
                :options="typeOptions"
                :ariaLabelledby="labelId"
                :ariaDescribedby="describedBy"
                :ariaInvalid="invalid"
                name="type"
                block
                :disabled="lockType"
            />
        </FormField>

        <FormField
            id="category-color"
            :label="t.budget.category_color"
            group
            :error="errors.color"
            v-slot="{ labelId, describedBy, invalid }"
        >
            <CategoryColorPicker
                :colors="categoryAppearanceOptions.colors"
                v-model="color"
                :aria-labelledby="labelId"
                :aria-describedby="describedBy"
                :aria-invalid="invalid"
            />
            <input type="hidden" name="color" :value="color ?? ''" />
        </FormField>

        <FormField
            id="category-icon"
            :label="t.budget.category_icon"
            group
            :error="errors.icon"
            v-slot="{ labelId, describedBy, invalid }"
        >
            <CategoryIconPicker
                :icons="categoryAppearanceOptions.icons"
                v-model="icon"
                :color="color ?? DEFAULT_CATEGORY_COLOR"
                :aria-labelledby="labelId"
                :aria-describedby="describedBy"
                :aria-invalid="invalid"
            />
            <input type="hidden" name="icon" :value="icon" />
        </FormField>
    </div>
</template>
