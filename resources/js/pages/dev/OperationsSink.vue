<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import CapitalMovementRow from '@/components/CapitalMovementRow.vue';
import FinancialOperationRow from '@/components/FinancialOperationRow.vue';
import ListSurface from '@/components/ListSurface.vue';
import OwnTransferRow from '@/components/OwnTransferRow.vue';
import { useTranslations } from '@/composables/useTranslations';
import {
    capitalMovementFixtures,
    financialOperationFixtures,
    ownTransferFixtures,
    paginate,
} from '@/lib/operations-fixtures';

const t = useTranslations();

const operations = paginate(financialOperationFixtures);
const ownTransfers = paginate(ownTransferFixtures);
const capitalMovements = paginate(capitalMovementFixtures);
</script>

<template>
    <Head title="Kitchen sink: Operacje" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:gap-8">
        <h1 class="text-xl font-semibold">Kitchen sink: Operacje</h1>

        <section class="flex flex-col gap-3">
            <h2 class="text-base font-semibold">Default</h2>

            <ListSurface
                :description="t.operations.list_description"
                :paginator="operations"
            >
                <div
                    class="operations-grid border-border/60 text-muted-foreground hidden border-b pb-2.5 text-[11px] font-medium tracking-wider uppercase md:grid"
                >
                    <span class="operations-cell-date">
                        {{ t.operations.date }}
                    </span>
                    <span class="operations-cell-description">
                        {{ t.operations.description }}
                    </span>
                    <span class="operations-cell-category">
                        {{ t.operations.category }}
                    </span>
                    <span class="operations-cell-account">
                        {{ t.operations.account }}
                    </span>
                    <span class="operations-cell-amount">
                        {{ t.operations.amount }}
                    </span>
                    <span class="operations-cell-actions text-right">
                        {{ t.operations.actions }}
                    </span>
                </div>

                <FinancialOperationRow
                    v-for="operation in operations.data"
                    :key="operation.id"
                    :operation="operation"
                />
            </ListSurface>

            <ListSurface
                :title="t.operations.own_transfers"
                :paginator="ownTransfers"
            >
                <div
                    class="operations-grid operations-grid-transfer border-border/60 text-muted-foreground hidden border-b pb-2.5 text-[11px] font-medium tracking-wider uppercase md:grid"
                >
                    <span class="operations-cell-date">
                        {{ t.operations.date }}
                    </span>
                    <span class="operations-cell-description">
                        {{ t.operations.description }}
                    </span>
                    <span class="operations-cell-account">
                        {{ t.operations.accounts }}
                    </span>
                    <span class="operations-cell-amount">
                        {{ t.operations.amount }}
                    </span>
                    <span class="operations-cell-actions text-right">
                        {{ t.operations.actions }}
                    </span>
                </div>

                <OwnTransferRow
                    v-for="transfer in ownTransfers.data"
                    :key="transfer.id"
                    :transfer="transfer"
                />
            </ListSurface>

            <ListSurface
                :title="t.operations.capital_movements"
                :paginator="capitalMovements"
            >
                <div
                    class="operations-grid operations-grid-transfer border-border/60 text-muted-foreground hidden border-b pb-2.5 text-[11px] font-medium tracking-wider uppercase md:grid"
                >
                    <span class="operations-cell-date">
                        {{ t.operations.date }}
                    </span>
                    <span class="operations-cell-description">
                        {{ t.operations.description }}
                    </span>
                    <span class="operations-cell-account">
                        {{ t.operations.accounts }}
                    </span>
                    <span class="operations-cell-amount">
                        {{ t.operations.amount }}
                    </span>
                    <span class="operations-cell-actions text-right">
                        {{ t.operations.actions }}
                    </span>
                </div>

                <CapitalMovementRow
                    v-for="movement in capitalMovements.data"
                    :key="movement.id"
                    :movement="movement"
                />
            </ListSurface>
        </section>
    </div>
</template>
