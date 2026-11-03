<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowLeftRight, Plus, Upload } from '@lucide/vue';
import { computed, useId } from 'vue';
import CsvImportController from '@/actions/App/Http/Controllers/CsvImportController';
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/composables/useTranslations';
import { operationsCreateAvailability } from '@/lib/operations-create-availability';

const { accountsCount, termDepositsCount } = defineProps<{
    accountsCount: number;
    termDepositsCount: number;
}>();

const emit = defineEmits<{
    'add-income': [];
    'add-expense': [];
    'add-transfer': [];
    'add-capital-movement': [];
}>();

const t = useTranslations();
const idPrefix = useId();
const transferReasonId = `${idPrefix}-transfer-reason`;
const capitalMovementReasonId = `${idPrefix}-capital-movement-reason`;

const availability = computed(() =>
    operationsCreateAvailability(accountsCount, termDepositsCount),
);

const transferReason = computed(() => {
    const key = availability.value.transfer.reasonKey;

    return key === null ? null : t.operations[key];
});

const capitalMovementReason = computed(() => {
    const key = availability.value.capitalMovement.reasonKey;

    return key === null ? null : t.operations[key];
});
</script>

<template>
    <div class="flex flex-col gap-2 md:items-end">
        <div class="flex flex-wrap gap-2">
            <Button as-child>
                <Link :href="CsvImportController.create.url()">
                    <Upload class="size-4" />
                    {{ t.operations.import_title }}
                </Link>
            </Button>
            <Button variant="outline" @click="emit('add-income')">
                <Plus class="size-4" />
                {{ t.operations.add_income }}
            </Button>
            <Button variant="outline" @click="emit('add-expense')">
                <Plus class="size-4" />
                {{ t.operations.add_expense }}
            </Button>
            <Button
                variant="outline"
                :disabled="!availability.transfer.enabled"
                :aria-describedby="
                    transferReason === null ? undefined : transferReasonId
                "
                @click="emit('add-transfer')"
            >
                <ArrowLeftRight class="size-4" />
                {{ t.operations.add_transfer }}
            </Button>
            <Button
                variant="outline"
                :disabled="!availability.capitalMovement.enabled"
                :aria-describedby="
                    capitalMovementReason === null
                        ? undefined
                        : capitalMovementReasonId
                "
                @click="emit('add-capital-movement')"
            >
                <ArrowLeftRight class="size-4" />
                {{ t.operations.add_capital_movement }}
            </Button>
        </div>

        <p
            v-if="transferReason !== null"
            :id="transferReasonId"
            class="text-muted-foreground text-xs"
        >
            {{ transferReason }}
        </p>
        <p
            v-if="capitalMovementReason !== null"
            :id="capitalMovementReasonId"
            class="text-muted-foreground text-xs"
        >
            {{ capitalMovementReason }}
        </p>
    </div>
</template>
