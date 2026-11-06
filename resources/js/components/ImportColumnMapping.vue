<script setup lang="ts">
import { useId } from 'vue';
import FormField from '@/components/FormField.vue';
import InputError from '@/components/InputError.vue';
import SegmentedControl from '@/components/SegmentedControl.vue';
import { Checkbox } from '@/components/ui/checkbox';
import { useTranslations } from '@/composables/useTranslations';

defineProps<{
    columnLabels: string[];
    errors: Partial<Record<string, string>>;
}>();

const hasHeader = defineModel<boolean>('hasHeader', { required: true });
const amountLayout = defineModel<'signed' | 'split'>('amountLayout', {
    required: true,
});
const dateColumn = defineModel<number | ''>('dateColumn', { required: true });
const descriptionColumn = defineModel<number | ''>('descriptionColumn', {
    required: true,
});
const counterpartyColumn = defineModel<number | ''>('counterpartyColumn', {
    required: true,
});
const amountColumn = defineModel<number | ''>('amountColumn', {
    required: true,
});
const inflowColumn = defineModel<number | ''>('inflowColumn', {
    required: true,
});
const outflowColumn = defineModel<number | ''>('outflowColumn', {
    required: true,
});

const t = useTranslations();

const headerId = useId();
const headerErrorId = `${headerId}-error`;

const amountLayoutOptions = [
    { value: 'signed' as const, label: t.operations.import_amount_signed },
    { value: 'split' as const, label: t.operations.import_amount_split },
];
</script>

<template>
    <div class="flex flex-col gap-4">
        <div class="flex flex-col gap-1">
            <div class="form-check-row">
                <Checkbox
                    :id="headerId"
                    v-model="hasHeader"
                    :aria-describedby="
                        errors.has_header ? headerErrorId : undefined
                    "
                    :aria-invalid="errors.has_header ? 'true' : undefined"
                />
                <label :for="headerId">
                    {{ t.operations.import_has_header }}
                </label>
            </div>
            <input
                type="hidden"
                name="has_header"
                :value="hasHeader ? '1' : '0'"
            />
            <InputError
                :id="headerErrorId"
                :message="errors.has_header"
                size="xs"
            />
        </div>

        <fieldset v-if="columnLabels.length > 0" class="form-fieldset">
            <legend class="section-title">
                {{ t.operations.import_columns }}
            </legend>

            <FormField
                :label="t.operations.date"
                :error="errors.date_column"
                v-slot="{ id, describedBy, invalid }"
            >
                <select
                    :id="id"
                    name="date_column"
                    v-model="dateColumn"
                    class="native-select"
                    required
                    :aria-describedby="describedBy"
                    :aria-invalid="invalid"
                >
                    <option
                        v-for="(label, index) in columnLabels"
                        :key="`date-${index}`"
                        :value="index"
                    >
                        {{ label }}
                    </option>
                </select>
            </FormField>

            <FormField
                :label="t.operations.description"
                :error="errors.description_column"
                v-slot="{ id, describedBy, invalid }"
            >
                <select
                    :id="id"
                    name="description_column"
                    v-model="descriptionColumn"
                    class="native-select"
                    required
                    :aria-describedby="describedBy"
                    :aria-invalid="invalid"
                >
                    <option
                        v-for="(label, index) in columnLabels"
                        :key="`description-${index}`"
                        :value="index"
                    >
                        {{ label }}
                    </option>
                </select>
            </FormField>

            <FormField
                :label="`${t.operations.counterparty} (${t.operations.import_optional_column})`"
                :error="errors.counterparty_column"
                v-slot="{ id, describedBy, invalid }"
            >
                <select
                    :id="id"
                    name="counterparty_column"
                    v-model="counterpartyColumn"
                    class="native-select"
                    :aria-describedby="describedBy"
                    :aria-invalid="invalid"
                >
                    <option value="">
                        {{ t.operations.import_no_column }}
                    </option>
                    <option
                        v-for="(label, index) in columnLabels"
                        :key="`counterparty-${index}`"
                        :value="index"
                    >
                        {{ label }}
                    </option>
                </select>
            </FormField>

            <FormField
                :label="t.operations.import_amount_layout"
                group
                :error="errors.amount_layout"
                v-slot="{ labelId, describedBy, invalid }"
            >
                <SegmentedControl
                    v-model="amountLayout"
                    :options="amountLayoutOptions"
                    :ariaLabelledby="labelId"
                    :ariaDescribedby="describedBy"
                    :ariaInvalid="invalid"
                    name="amount_layout"
                    tone="primary"
                    block
                />
            </FormField>

            <FormField
                v-if="amountLayout === 'signed'"
                :label="t.operations.amount"
                :error="errors.amount_column"
                v-slot="{ id, describedBy, invalid }"
            >
                <select
                    :id="id"
                    name="amount_column"
                    v-model="amountColumn"
                    class="native-select"
                    required
                    :aria-describedby="describedBy"
                    :aria-invalid="invalid"
                >
                    <option
                        v-for="(label, index) in columnLabels"
                        :key="`amount-${index}`"
                        :value="index"
                    >
                        {{ label }}
                    </option>
                </select>
            </FormField>

            <template v-else>
                <FormField
                    :label="t.operations.import_inflow_column"
                    :error="errors.inflow_column"
                    v-slot="{ id, describedBy, invalid }"
                >
                    <select
                        :id="id"
                        name="inflow_column"
                        v-model="inflowColumn"
                        class="native-select"
                        required
                        :aria-describedby="describedBy"
                        :aria-invalid="invalid"
                    >
                        <option
                            v-for="(label, index) in columnLabels"
                            :key="`inflow-${index}`"
                            :value="index"
                        >
                            {{ label }}
                        </option>
                    </select>
                </FormField>

                <FormField
                    :label="t.operations.import_outflow_column"
                    :error="errors.outflow_column"
                    v-slot="{ id, describedBy, invalid }"
                >
                    <select
                        :id="id"
                        name="outflow_column"
                        v-model="outflowColumn"
                        class="native-select"
                        required
                        :aria-describedby="describedBy"
                        :aria-invalid="invalid"
                    >
                        <option
                            v-for="(label, index) in columnLabels"
                            :key="`outflow-${index}`"
                            :value="index"
                        >
                            {{ label }}
                        </option>
                    </select>
                </FormField>
            </template>
        </fieldset>
    </div>
</template>
