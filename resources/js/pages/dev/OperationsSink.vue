<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Plus, Upload } from '@lucide/vue';
import CapitalMovementRow from '@/components/CapitalMovementRow.vue';
import CategoryChip from '@/components/CategoryChip.vue';
import CompactAccountIdentity from '@/components/CompactAccountIdentity.vue';
import FinancialOperationRow from '@/components/FinancialOperationRow.vue';
import IndexEmptyState from '@/components/IndexEmptyState.vue';
import ListSurface from '@/components/ListSurface.vue';
import OperationDescriptionCell from '@/components/OperationDescriptionCell.vue';
import OperationsDateCell from '@/components/OperationsDateCell.vue';
import OperationsPageActions from '@/components/OperationsPageActions.vue';
import OwnTransferRow from '@/components/OwnTransferRow.vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { useTranslations } from '@/composables/useTranslations';
import {
    financialOperationAmountClass,
    formatFinancialOperationAmount,
    formatMoney,
} from '@/lib/format';
import {
    capitalMovementFixtures,
    capitalMovementToDeposit,
    expenseOperation,
    financialOperationFixtures,
    ownTransferFixture,
    ownTransferFixtures,
    paginate,
} from '@/lib/operations-fixtures';

const t = useTranslations();

const actionStates = [
    { label: '2 konta, 1 lokata', accountsCount: 2, termDepositsCount: 1 },
    { label: '1 konto, 1 lokata', accountsCount: 1, termDepositsCount: 1 },
    { label: '2 konta, 0 lokat', accountsCount: 2, termDepositsCount: 0 },
    { label: '0 kont, 0 lokat', accountsCount: 0, termDepositsCount: 0 },
];

const disabledPagination = paginate(financialOperationFixtures.slice(0, 1));
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
                <div class="operations-grid operations-header-row">
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
                    class="operations-grid operations-grid-transfer operations-header-row"
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
                    class="operations-grid operations-grid-transfer operations-header-row"
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

        <section class="flex flex-col gap-3">
            <h2 class="text-base font-semibold">Kontrakt</h2>

            <ListSurface :description="t.operations.list_description">
                <div class="operations-grid operations-header-row">
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

                <div class="operations-grid operations-row">
                    <div class="operations-cell-date">
                        <OperationsDateCell
                            :date="expenseOperation.operation_date"
                        />
                    </div>
                    <div class="operations-cell-description">
                        <OperationDescriptionCell
                            variant="expense"
                            :description="expenseOperation.description"
                        />
                    </div>
                    <div class="operations-cell-category">
                        <CategoryChip
                            :name="expenseOperation.category_name"
                            :icon="expenseOperation.category_icon"
                            :color="expenseOperation.category_color"
                        />
                    </div>
                    <CompactAccountIdentity
                        class="operations-cell-account"
                        mark-size="md"
                        :name="expenseOperation.account_name"
                        :type="expenseOperation.account_type"
                        :bank="expenseOperation.account_bank"
                        :mark_url="expenseOperation.account_mark_url"
                    />
                    <p
                        class="operations-cell-amount"
                        :class="
                            financialOperationAmountClass(
                                expenseOperation.type,
                                expenseOperation.amount_minor_units,
                            )
                        "
                    >
                        {{
                            formatFinancialOperationAmount(
                                expenseOperation.type,
                                expenseOperation.amount_minor_units,
                            )
                        }}
                    </p>
                </div>
            </ListSurface>

            <ListSurface :title="t.operations.own_transfers">
                <div
                    class="operations-grid operations-grid-transfer operations-header-row"
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

                <div
                    class="operations-grid operations-grid-transfer operations-row"
                >
                    <div class="operations-cell-date">
                        <OperationsDateCell
                            :date="ownTransferFixture.transfer_date"
                        />
                    </div>
                    <div class="operations-cell-description">
                        <OperationDescriptionCell
                            variant="transfer"
                            :description="ownTransferFixture.description"
                        />
                    </div>
                    <div
                        class="operations-cell-account flex items-center gap-1"
                    >
                        <CompactAccountIdentity
                            class="min-w-0 flex-1"
                            mark-size="md"
                            :name="ownTransferFixture.source_account_name"
                            :type="ownTransferFixture.source_account_type"
                            :bank="ownTransferFixture.source_account_bank"
                            :mark_url="
                                ownTransferFixture.source_account_mark_url
                            "
                        />
                        <span class="text-muted-foreground shrink-0">→</span>
                        <CompactAccountIdentity
                            class="min-w-0 flex-1"
                            mark-size="md"
                            :name="ownTransferFixture.destination_account_name"
                            :type="ownTransferFixture.destination_account_type"
                            :bank="ownTransferFixture.destination_account_bank"
                            :mark_url="
                                ownTransferFixture.destination_account_mark_url
                            "
                        />
                    </div>
                    <p class="operations-cell-amount">
                        {{ formatMoney(ownTransferFixture.amount_minor_units) }}
                    </p>
                </div>
            </ListSurface>

            <ListSurface :title="t.operations.capital_movements">
                <div
                    class="operations-grid operations-grid-transfer operations-header-row"
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

                <div
                    class="operations-grid operations-grid-transfer operations-row"
                >
                    <div class="operations-cell-date">
                        <OperationsDateCell
                            :date="capitalMovementToDeposit.movement_date"
                        />
                    </div>
                    <div class="operations-cell-description">
                        <OperationDescriptionCell
                            variant="transfer"
                            :description="capitalMovementToDeposit.description"
                        />
                    </div>
                    <div
                        class="operations-cell-account flex items-center gap-1"
                    >
                        <CompactAccountIdentity
                            class="min-w-0 flex-1"
                            mark-size="md"
                            :name="capitalMovementToDeposit.account_name"
                            :type="capitalMovementToDeposit.account_type"
                            :bank="capitalMovementToDeposit.account_bank"
                            :mark_url="
                                capitalMovementToDeposit.account_mark_url
                            "
                        />
                        <span class="text-muted-foreground shrink-0">→</span>
                        <span class="min-w-0 flex-1 truncate font-medium">
                            {{ capitalMovementToDeposit.term_deposit_name }}
                        </span>
                    </div>
                    <p class="operations-cell-amount">
                        {{
                            formatMoney(
                                capitalMovementToDeposit.amount_minor_units,
                            )
                        }}
                    </p>
                </div>
            </ListSurface>
        </section>

        <section class="flex flex-col gap-3">
            <h2 class="text-base font-semibold">Wiersz tytułu</h2>

            <div
                v-for="state in actionStates"
                :key="state.label"
                class="flex flex-col gap-2"
            >
                <p class="text-muted-foreground text-xs">{{ state.label }}</p>
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <h3 class="text-lg font-semibold">
                        {{ t.operations.title }}
                    </h3>
                    <OperationsPageActions
                        :accounts-count="state.accountsCount"
                        :term-deposits-count="state.termDepositsCount"
                    />
                </div>
            </div>
        </section>

        <section class="flex flex-col gap-3">
            <h2 class="text-base font-semibold">Puste stany</h2>

            <ListSurface>
                <IndexEmptyState
                    :title="t.operations.empty_title"
                    :description="t.operations.empty_description"
                >
                    <template #actions>
                        <Button as-child>
                            <Link href="#">
                                <Upload class="size-4" />
                                {{ t.operations.import_title }}
                            </Link>
                        </Button>
                        <Button variant="outline">
                            <Plus class="size-4" />
                            {{ t.operations.add_income }}
                        </Button>
                    </template>
                </IndexEmptyState>
            </ListSurface>

            <ListSurface>
                <IndexEmptyState
                    :title="t.operations.empty_filtered_title"
                    :description="t.operations.empty_filtered_description"
                >
                    <template #actions>
                        <Button variant="outline" as-child>
                            <Link href="#">
                                {{ t.operations.filter_clear }}
                            </Link>
                        </Button>
                    </template>
                </IndexEmptyState>
            </ListSurface>
        </section>
        <section class="flex flex-col gap-3">
            <h2 class="text-base font-semibold">Stany z macierzy</h2>
            <p class="text-muted-foreground text-xs">
                Default (trzy listy z fixture) i empty (oba stany puste) są
                wyżej. Poniżej: hover, focus-visible, disabled, error, loading.
            </p>

            <h3 class="text-sm font-semibold">Hover</h3>
            <p class="text-muted-foreground text-xs">
                Rytm, nie afordancja: tło wiersza zmienia się tylko po to, żeby
                prowadzić oko wzdłuż linii. Wiersz nie jest klikalny.
            </p>
            <ListSurface :description="t.operations.list_description">
                <FinancialOperationRow
                    v-for="operation in financialOperationFixtures.slice(0, 3)"
                    :key="operation.id"
                    :operation="operation"
                />
            </ListSurface>

            <h3 class="text-sm font-semibold">Focus-visible</h3>
            <p class="text-muted-foreground text-xs">
                Przejdź Tabem: linki paginacji, akcje wiersza i wiersz z opisem
                z banku (otwiera dymek) dostają pierścień 3 px.
            </p>
            <ListSurface
                :description="t.operations.list_description"
                :paginator="operations"
            >
                <FinancialOperationRow
                    v-for="operation in financialOperationFixtures.slice(2, 4)"
                    :key="operation.id"
                    :operation="operation"
                />
            </ListSurface>

            <h3 class="text-sm font-semibold">Disabled</h3>
            <p class="text-muted-foreground text-xs">
                Powody blokady akcji tworzenia (1 konto, 0 lokat) oraz wyłączony
                link paginacji na pierwszej stronie (span z aria-disabled, poza
                Tabem).
            </p>
            <div class="flex flex-wrap items-start justify-between gap-3">
                <h4 class="text-lg font-semibold">{{ t.operations.title }}</h4>
                <OperationsPageActions
                    :accounts-count="1"
                    :term-deposits-count="0"
                />
            </div>
            <ListSurface :paginator="disabledPagination">
                <FinancialOperationRow
                    v-for="operation in financialOperationFixtures.slice(0, 1)"
                    :key="operation.id"
                    :operation="operation"
                />
            </ListSurface>

            <h3 class="text-sm font-semibold">Error</h3>
            <p class="text-muted-foreground text-xs">
                N/A: usunięcie nie ma walidacji; błąd serwera pokazuje modal
                Inertia (D11).
            </p>

            <h3 class="text-sm font-semibold">Loading</h3>
            <p class="text-muted-foreground text-xs">
                Lista w czasie wizyty (aria-busy="true", wyciszona) oraz
                przycisk usunięcia ze spinnerem.
            </p>
            <ListSurface
                class="operations-list"
                aria-busy="true"
                :description="t.operations.list_description"
            >
                <FinancialOperationRow
                    v-for="operation in financialOperationFixtures.slice(0, 3)"
                    :key="operation.id"
                    :operation="operation"
                />
            </ListSurface>
            <div>
                <Button variant="destructive" disabled>
                    <Spinner :aria-label="t.common.loading" />
                    {{ t.accounts.delete }}
                </Button>
            </div>
        </section>
    </div>
</template>
