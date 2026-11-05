<script setup lang="ts">
import { computed } from 'vue';
import CategoryChip from '@/components/CategoryChip.vue';
import type { CategoryPickerOption } from '@/lib/category-appearance';
import {
    Select,
    SelectContent,
    SelectItem,
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
    }>(),
    {
        required: false,
        disabled: false,
    },
);

const emit = defineEmits<{
    'update:modelValue': [value: number | ''];
}>();

const selectedCategoryId = computed({
    get: () => (props.modelValue === '' ? undefined : String(props.modelValue)),
    set: (value: string | undefined) => {
        emit('update:modelValue', value ? Number(value) : '');
    },
});

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
                        :name="selectedCategory.name"
                        :icon="selectedCategory.icon"
                        :color="selectedCategory.color"
                        size="sm"
                    />
                </SelectValue>
            </SelectTrigger>
            <SelectContent>
                <SelectItem
                    v-for="category in categories"
                    :key="category.id"
                    :value="String(category.id)"
                >
                    <CategoryChip
                        :name="category.name"
                        :icon="category.icon"
                        :color="category.color"
                        size="sm"
                    />
                </SelectItem>
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
