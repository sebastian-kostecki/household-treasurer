<script setup lang="ts">
import { computed } from 'vue';
import CategoryChip from '@/components/CategoryChip.vue';
import type { CategoryPickerOption } from '@/lib/category-appearance';
import {
    categoryOptionName,
    categorySections,
    type CategorySectionLabels,
} from '@/lib/category-filter-options';
import {
    groupSelectOptions,
    type SelectOptionBlock,
} from '@/lib/category-tree';
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectLabel,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

const props = withDefaults(
    defineProps<{
        modelValue: number | '';
        categories: CategoryPickerOption[];
        id?: string;
        name?: string;
        placeholder?: string;
        required?: boolean;
        disabled?: boolean;
        class?: string;
        ariaLabel?: string;
        ariaDescribedby?: string;
        ariaInvalid?: 'true';
        allOptionLabel?: string;
        archivedSuffix?: string;
        sectionLabels?: CategorySectionLabels;
    }>(),
    {
        required: false,
        disabled: false,
    },
);

const emit = defineEmits<{
    'update:modelValue': [value: number | ''];
}>();

const ALL_CATEGORIES_VALUE = '__all__';

const selectedCategoryId = computed({
    get: () => {
        if (props.modelValue === '') {
            return props.allOptionLabel === undefined
                ? undefined
                : ALL_CATEGORIES_VALUE;
        }

        return String(props.modelValue);
    },
    set: (value: string | undefined) => {
        emit(
            'update:modelValue',
            value && value !== ALL_CATEGORIES_VALUE ? Number(value) : '',
        );
    },
});

const rows = computed<
    (
        | { kind: 'section'; type: string; label: string }
        | SelectOptionBlock<CategoryPickerOption>
    )[]
>(() => {
    if (props.sectionLabels === undefined) {
        return groupSelectOptions(props.categories);
    }

    return categorySections(props.categories, props.sectionLabels).flatMap(
        (section) => [
            {
                kind: 'section' as const,
                type: section.type,
                label: section.label,
            },
            ...section.blocks,
        ],
    );
});

function optionName(category: CategoryPickerOption): string {
    return categoryOptionName(category, props.archivedSuffix);
}

const selectedCategory = computed(() =>
    props.categories.find((category) => category.id === props.modelValue),
);
</script>

<template>
    <div>
        <Select v-model="selectedCategoryId" :disabled="disabled">
            <SelectTrigger
                :id="id"
                :aria-label="ariaLabel"
                :aria-describedby="ariaDescribedby"
                :aria-invalid="ariaInvalid"
                :class="['w-full', props.class]"
            >
                <SelectValue :placeholder="placeholder">
                    <CategoryChip
                        v-if="selectedCategory"
                        :name="optionName(selectedCategory)"
                        :icon="selectedCategory.icon"
                        :color="selectedCategory.color"
                        size="sm"
                    />
                    <span v-else-if="allOptionLabel !== undefined">
                        {{ allOptionLabel }}
                    </span>
                </SelectValue>
            </SelectTrigger>
            <SelectContent>
                <SelectItem
                    v-if="allOptionLabel !== undefined"
                    :value="ALL_CATEGORIES_VALUE"
                >
                    {{ allOptionLabel }}
                </SelectItem>
                <template
                    v-for="row in rows"
                    :key="
                        row.kind === 'section'
                            ? `section-${row.type}`
                            : row.kind === 'group'
                              ? `group-${row.group.id}-${row.options[0].id}`
                              : row.option.id
                    "
                >
                    <SelectGroup v-if="row.kind === 'section'">
                        <SelectLabel>{{ row.label }}</SelectLabel>
                    </SelectGroup>
                    <SelectGroup v-else-if="row.kind === 'group'">
                        <SelectLabel>{{ row.group.name }}</SelectLabel>
                        <SelectItem
                            v-for="category in row.options"
                            :key="category.id"
                            :value="String(category.id)"
                            :text-value="optionName(category)"
                        >
                            <CategoryChip
                                :name="optionName(category)"
                                :icon="category.icon"
                                :color="category.color"
                                size="sm"
                            />
                        </SelectItem>
                    </SelectGroup>
                    <SelectItem
                        v-else
                        :value="String(row.option.id)"
                        :text-value="optionName(row.option)"
                    >
                        <CategoryChip
                            :name="optionName(row.option)"
                            :icon="row.option.icon"
                            :color="row.option.color"
                            size="sm"
                        />
                    </SelectItem>
                </template>
            </SelectContent>
        </Select>
        <input
            v-if="name"
            type="hidden"
            :name="name"
            :value="modelValue"
            :required="required"
        />
    </div>
</template>
