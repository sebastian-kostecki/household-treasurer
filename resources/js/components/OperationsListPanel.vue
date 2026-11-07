<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Plus, Upload } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import CsvImportController from '@/actions/App/Http/Controllers/CsvImportController';
import CapitalMovementRow, {
    type CapitalMovementRowData,
} from '@/components/CapitalMovementRow.vue';
import FinancialOperationRow, {
    type FinancialOperationRowData,
} from '@/components/FinancialOperationRow.vue';
import IndexEmptyState from '@/components/IndexEmptyState.vue';
import ListSurface from '@/components/ListSurface.vue';
import type { OperationsListSection } from '@/components/OperationsActiveFilterChips.vue';
import OperationsDayRow from '@/components/OperationsDayRow.vue';
import OperationsFilters, {
    type OperationsFilterAccount,
    type OperationsFilterCategory,
    type OperationsFilterTermDeposit,
    type OperationsListFilters,
} from '@/components/OperationsFilters.vue';
import OwnTransferRow, {
    type OwnTransferRowData,
} from '@/components/OwnTransferRow.vue';
import type { PaginatedCollection } from '@/components/PaginationLinks.vue';
import SegmentedControl from '@/components/SegmentedControl.vue';
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/composables/useTranslations';
import {
    groupOperationsByDay,
    groupRowsByDay,
} from '@/lib/operations-day-groups';
import {
    EMPTY_OPERATIONS_FILTERS,
    operationsListQuery,
} from '@/lib/operations-list-query';
import { operations as operationsRoute } from '@/routes';

const {
    section,
    hasAnyRecords,
    operations,
    ownTransfers,
    capitalMovements,
    operationDaySums,
    sectionTotals,
    filters,
    filtersActive,
    suppressedSections,
    filterYears,
    filterCategories,
    filterAccounts,
    filterTermDeposits,
    busy = false,
} = defineProps<{
    section: OperationsListSection;
    hasAnyRecords: boolean;
    operations: PaginatedCollection<FinancialOperationRowData>;
    ownTransfers: PaginatedCollection<OwnTransferRowData>;
    capitalMovements: PaginatedCollection<CapitalMovementRowData>;
    operationDaySums: Record<string, number>;
    sectionTotals: Record<OperationsListSection, number>;
    filters: OperationsListFilters;
    filtersActive: boolean;
    suppressedSections: OperationsListSection[];
    filterYears: number[];
    filterCategories: OperationsFilterCategory[];
    filterAccounts: OperationsFilterAccount[];
    filterTermDeposits: OperationsFilterTermDeposit[];
    busy?: boolean;
}>();

const emit = defineEmits<{
    'change-section': [section: OperationsListSection];
    'add-income': [];
    'edit-income': [operation: FinancialOperationRowData];
    'edit-expense': [operation: FinancialOperationRowData];
    'edit-transfer': [transfer: OwnTransferRowData];
    'edit-capital-movement': [movement: CapitalMovementRowData];
}>();

const t = useTranslations();

// Lokalny stan, żeby zakładka reagowała od razu; po wizycie źródłem prawdy jest prop.
const activeSection = ref<OperationsListSection>(section);

watch(
    () => section,
    (value) => {
        activeSection.value = value;
    },
);

const sectionLabels: Record<OperationsListSection, string> = {
    financial_operations: t.operations.financial_operations,
    own_transfers: t.operations.own_transfers,
    capital_movements: t.operations.capital_movements,
};

const sectionEmptyTitles: Record<OperationsListSection, string> = {
    financial_operations: t.operations.empty_financial_operations,
    own_transfers: t.operations.empty_own_transfers,
    capital_movements: t.operations.empty_capital_movements,
};

const paginators = computed<
    Record<OperationsListSection, PaginatedCollection<unknown>>
>(() => ({
    financial_operations: operations,
    own_transfers: ownTransfers,
    capital_movements: capitalMovements,
}));

function sectionCount(value: OperationsListSection): number {
    return suppressedSections.includes(value)
        ? 0
        : paginators.value[value].total;
}

const sectionOptions = computed(() =>
    (Object.keys(sectionLabels) as OperationsListSection[]).map((value) => ({
        value,
        label: sectionLabels[value],
        count: sectionCount(value),
    })),
);

const sectionModel = computed({
    get: () => activeSection.value,
    set: (value: OperationsListSection) => {
        activeSection.value = value;
        emit('change-section', value);
    },
});

const state = computed<
    'none' | 'suppressed' | 'empty-section' | 'filtered' | 'list'
>(() => {
    if (!hasAnyRecords) {
        return 'none';
    }

    if (suppressedSections.includes(activeSection.value)) {
        return 'suppressed';
    }

    if (sectionTotals[activeSection.value] === 0) {
        return 'empty-section';
    }

    if (paginators.value[activeSection.value].total === 0) {
        return 'filtered';
    }

    return 'list';
});

const suppressedNote = computed(() =>
    t.operations.filter_suppressed_sections.replace(
        ':sections',
        suppressedSections.map((value) => sectionLabels[value]).join(', '),
    ),
);

const clearFiltersHref = computed(() =>
    operationsRoute({
        query: operationsListQuery(
            EMPTY_OPERATIONS_FILTERS,
            activeSection.value,
        ),
    }),
);

const operationGroups = computed(() =>
    groupOperationsByDay(operations.data, operationDaySums),
);

const transferGroups = computed(() =>
    groupRowsByDay(ownTransfers.data, (transfer) => transfer.transfer_date),
);

const capitalMovementGroups = computed(() =>
    groupRowsByDay(capitalMovements.data, (movement) => movement.movement_date),
);
</script>

<template>
    <div class="flex flex-col gap-4">
        <div v-if="hasAnyRecords" class="max-w-full self-start overflow-x-auto">
            <SegmentedControl
                v-model="sectionModel"
                tone="neutral"
                class="segmented-control-tabs"
                :ariaLabel="t.operations.sections_aria"
                :options="sectionOptions"
            />
        </div>

        <ListSurface
            class="operations-list"
            flush
            :aria-busy="busy"
            :paginator="
                state === 'list' ? paginators[activeSection] : undefined
            "
        >
            <template v-if="hasAnyRecords" #header>
                <OperationsFilters
                    :filters="filters"
                    :filters-active="filtersActive"
                    :years="filterYears"
                    :categories="filterCategories"
                    :accounts="filterAccounts"
                    :term-deposits="filterTermDeposits"
                    :suppressed-sections="suppressedSections"
                    :section="activeSection"
                />
            </template>

            <IndexEmptyState
                v-if="state === 'none'"
                :title="t.operations.empty_title"
                :description="t.operations.empty_description"
            >
                <template #actions>
                    <Button variant="outline" as-child>
                        <Link :href="CsvImportController.create.url()">
                            <Upload class="size-4" aria-hidden="true" />
                            {{ t.operations.import_csv }}
                        </Link>
                    </Button>
                    <Button variant="outline" @click="emit('add-income')">
                        <Plus class="size-4" aria-hidden="true" />
                        {{ t.operations.add_income }}
                    </Button>
                </template>
            </IndexEmptyState>

            <IndexEmptyState
                v-else-if="state === 'suppressed'"
                :title="t.operations.section_suppressed_title"
                :description="suppressedNote"
            >
                <template #actions>
                    <Button variant="outline" as-child>
                        <Link :href="clearFiltersHref" preserve-scroll>
                            {{ t.operations.filter_clear }}
                        </Link>
                    </Button>
                </template>
            </IndexEmptyState>

            <IndexEmptyState
                v-else-if="state === 'empty-section'"
                :title="sectionEmptyTitles[activeSection]"
            />

            <IndexEmptyState
                v-else-if="state === 'filtered'"
                :title="t.operations.empty_filtered_title"
                :description="t.operations.empty_filtered_description"
            >
                <template #actions>
                    <Button variant="outline" as-child>
                        <Link :href="clearFiltersHref" preserve-scroll>
                            {{ t.operations.filter_clear }}
                        </Link>
                    </Button>
                </template>
            </IndexEmptyState>

            <template v-else-if="activeSection === 'financial_operations'">
                <template v-for="group in operationGroups" :key="group.date">
                    <OperationsDayRow
                        :date="group.date"
                        :sum-minor-units="group.sumMinorUnits"
                    />
                    <FinancialOperationRow
                        v-for="operation in group.rows"
                        :key="operation.id"
                        :operation="operation"
                        @edit="
                            operation.type === 'income'
                                ? emit('edit-income', operation)
                                : emit('edit-expense', operation)
                        "
                    />
                </template>
            </template>

            <template v-else-if="activeSection === 'own_transfers'">
                <template v-for="group in transferGroups" :key="group.date">
                    <OperationsDayRow :date="group.date" />
                    <OwnTransferRow
                        v-for="transfer in group.rows"
                        :key="transfer.id"
                        :transfer="transfer"
                        @edit="emit('edit-transfer', transfer)"
                    />
                </template>
            </template>

            <template v-else>
                <template
                    v-for="group in capitalMovementGroups"
                    :key="group.date"
                >
                    <OperationsDayRow :date="group.date" />
                    <CapitalMovementRow
                        v-for="movement in group.rows"
                        :key="movement.id"
                        :movement="movement"
                        @edit="emit('edit-capital-movement', movement)"
                    />
                </template>
            </template>

            <template
                v-if="
                    state === 'list' && activeSection !== 'financial_operations'
                "
                #footer
            >
                <p class="meta text-muted-foreground">
                    {{
                        activeSection === 'own_transfers'
                            ? t.operations.own_transfers_footer
                            : t.operations.capital_movements_footer
                    }}
                </p>
            </template>
        </ListSurface>
    </div>
</template>
