<script setup lang="ts">
import { Head, router, setLayoutProps } from '@inertiajs/vue3';
import { Archive, ArchiveRestore, Pencil, Trash2 } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import TermDepositController from '@/actions/App/Http/Controllers/TermDepositController';
import AccountIdentityBlock from '@/components/AccountIdentityBlock.vue';
import ConfirmActionDialog from '@/components/ConfirmActionDialog.vue';
import IconAction from '@/components/IconAction.vue';
import IndexEmptyState from '@/components/IndexEmptyState.vue';
import ListSurface from '@/components/ListSurface.vue';
import StatTile from '@/components/StatTile.vue';
import TermDepositFormDialog from '@/components/TermDepositFormDialog.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { useTranslations } from '@/composables/useTranslations';
import { amountToneClass } from '@/lib/amount-tone';
import { formatDate, formatMoney } from '@/lib/format';
import { index as termDepositsRoute } from '@/routes/term-deposits';
import type {
    TermDepositRow,
    TermDepositsPageProps,
} from '@/types/term-deposits';

const props = defineProps<TermDepositsPageProps>();

const t = useTranslations();

const createDialogOpen = ref(false);
const editDialogOpen = ref(false);
const editingTermDeposit = ref<TermDepositRow | null>(null);

const showArchived = ref(props.show_archived);
const isTogglingArchived = ref(false);

watch(
    () => props.show_archived,
    (value) => {
        showArchived.value = value;
    },
);

watch(showArchived, (value) => {
    if (value !== props.show_archived) {
        toggleShowArchived(value);
    }
});

const filterQuery = computed(() =>
    props.show_archived ? { query: { show_archived: 1 } } : undefined,
);

setLayoutProps({
    breadcrumbs: [
        {
            title: t.nav.term_deposits,
            href: termDepositsRoute(),
        },
    ],
});

function archivedAmountClass(amountMinorUnits: number): string | undefined {
    return amountMinorUnits < 0 ? amountToneClass(amountMinorUnits) : undefined;
}

function balanceClass(termDeposit: TermDepositRow): string | undefined {
    return termDeposit.is_archived
        ? archivedAmountClass(termDeposit.current_balance_minor_units)
        : amountToneClass(termDeposit.current_balance_minor_units);
}

function openEditDialog(termDeposit: TermDepositRow): void {
    editingTermDeposit.value = termDeposit;
    editDialogOpen.value = true;
}

function toggleShowArchived(checked: boolean): void {
    router.get(
        termDepositsRoute({
            query: { show_archived: checked ? 1 : undefined },
        }),
        {},
        {
            preserveState: true,
            preserveScroll: true,
            onStart: () => {
                isTogglingArchived.value = true;
            },
            onFinish: () => {
                isTogglingArchived.value = false;
                showArchived.value = props.show_archived;
            },
        },
    );
}
</script>

<template>
    <Head :title="t.term_deposits.title" />

    <div class="flex flex-1 flex-col gap-4 p-4 md:gap-5">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-lg font-semibold">{{ t.term_deposits.title }}</h1>
            <Button @click="createDialogOpen = true">
                {{ t.term_deposits.add }}
            </Button>
        </div>

        <StatTile
            :label="t.term_deposits.total"
            :amount-minor-units="term_deposits_total_minor_units"
        >
            <template
                v-if="archived_term_deposits_total_minor_units !== 0"
                #footer
            >
                <p class="text-muted-foreground text-sm">
                    {{ t.term_deposits.archived_total }}:
                    <span
                        class="tabular-nums"
                        :class="
                            archivedAmountClass(
                                archived_term_deposits_total_minor_units,
                            )
                        "
                    >
                        {{
                            formatMoney(
                                archived_term_deposits_total_minor_units,
                            )
                        }}
                    </span>
                </p>
            </template>
        </StatTile>

        <div
            v-if="archived_term_deposits_count > 0 || show_archived"
            class="flex items-center gap-2"
        >
            <Checkbox
                id="show-archived-term-deposits"
                v-model="showArchived"
                :disabled="isTogglingArchived"
            />
            <Label
                for="show-archived-term-deposits"
                class="text-muted-foreground text-sm"
            >
                {{ t.term_deposits.show_archived }}
            </Label>
        </div>

        <IndexEmptyState
            v-if="termDeposits.length === 0 && archived_term_deposits_count > 0"
            :title="t.term_deposits.all_archived_title"
            :description="t.term_deposits.all_archived_description"
        >
            <template #actions>
                <Button
                    :disabled="isTogglingArchived"
                    @click="showArchived = true"
                >
                    {{ t.term_deposits.show_archived }}
                </Button>
                <Button variant="secondary" @click="createDialogOpen = true">
                    {{ t.term_deposits.add }}
                </Button>
            </template>
        </IndexEmptyState>

        <IndexEmptyState
            v-else-if="termDeposits.length === 0"
            :title="t.term_deposits.empty_title"
            :description="t.term_deposits.empty_description"
        >
            <template #actions>
                <Button @click="createDialogOpen = true">
                    {{ t.term_deposits.add }}
                </Button>
            </template>
        </IndexEmptyState>

        <ListSurface v-else :description="t.term_deposits.list_description">
            <div
                v-for="termDeposit in termDeposits"
                :key="termDeposit.id"
                class="border-border/60 hover:bg-foreground/[0.03] flex flex-wrap items-center justify-between gap-x-4 gap-y-3 border-b py-3 transition-colors last:border-b-0"
                :class="termDeposit.is_archived ? 'text-muted-foreground' : ''"
            >
                <AccountIdentityBlock
                    class="min-w-0 flex-1 basis-40"
                    :name="termDeposit.name"
                    type="bank"
                    :bank="termDeposit.bank"
                    :mark_url="termDeposit.mark_url"
                >
                    <div class="flex flex-wrap items-center gap-2">
                        <p class="font-medium">{{ termDeposit.name }}</p>
                        <Badge
                            v-if="termDeposit.is_archived"
                            variant="secondary"
                        >
                            {{ t.term_deposits.archived_badge }}
                        </Badge>
                    </div>
                    <p
                        v-if="termDeposit.end_date"
                        class="text-muted-foreground text-sm"
                    >
                        {{ t.term_deposits.end_date }}:
                        {{ formatDate(termDeposit.end_date) }}
                    </p>
                </AccountIdentityBlock>

                <p
                    class="shrink-0 font-medium tabular-nums"
                    :class="balanceClass(termDeposit)"
                >
                    {{ formatMoney(termDeposit.current_balance_minor_units) }}
                </p>

                <div
                    class="flex w-full flex-wrap gap-1 sm:w-auto sm:justify-end"
                >
                    <IconAction
                        v-if="!termDeposit.is_archived"
                        :icon="Pencil"
                        :label="t.term_deposits.edit"
                        @click="openEditDialog(termDeposit)"
                    />
                    <ConfirmActionDialog
                        v-if="termDeposit.can_delete"
                        :form="
                            TermDepositController.destroy.form(
                                termDeposit,
                                filterQuery,
                            )
                        "
                        :title="t.term_deposits.delete_confirm_title"
                        :description="t.term_deposits.delete_confirm_body"
                        :confirm-label="t.term_deposits.delete"
                        :cancel-label="t.term_deposits.cancel"
                        destructive
                    >
                        <template #trigger>
                            <IconAction
                                as-dialog-trigger
                                :icon="Trash2"
                                :label="t.term_deposits.delete"
                                destructive
                            />
                        </template>
                    </ConfirmActionDialog>
                    <ConfirmActionDialog
                        v-if="termDeposit.can_archive"
                        :form="
                            TermDepositController.archive.form(
                                termDeposit,
                                filterQuery,
                            )
                        "
                        :title="t.term_deposits.archive_confirm_title"
                        :description="t.term_deposits.archive_confirm_body"
                        :confirm-label="t.term_deposits.archive"
                        :cancel-label="t.term_deposits.cancel"
                    >
                        <template #trigger>
                            <IconAction
                                as-dialog-trigger
                                :icon="Archive"
                                :label="t.term_deposits.archive"
                            />
                        </template>
                    </ConfirmActionDialog>
                    <ConfirmActionDialog
                        v-if="termDeposit.can_restore"
                        :form="
                            TermDepositController.restore.form(
                                termDeposit,
                                filterQuery,
                            )
                        "
                        :title="t.term_deposits.restore_confirm_title"
                        :description="t.term_deposits.restore_confirm_body"
                        :confirm-label="t.term_deposits.restore"
                        :cancel-label="t.term_deposits.cancel"
                    >
                        <template #trigger>
                            <IconAction
                                as-dialog-trigger
                                :icon="ArchiveRestore"
                                :label="t.term_deposits.restore"
                            />
                        </template>
                    </ConfirmActionDialog>
                </div>
            </div>
        </ListSurface>
    </div>

    <TermDepositFormDialog
        v-model:open="createDialogOpen"
        mode="create"
        :show-archived="show_archived"
    />

    <TermDepositFormDialog
        v-model:open="editDialogOpen"
        mode="edit"
        :term-deposit="editingTermDeposit"
        :show-archived="show_archived"
    />
</template>
