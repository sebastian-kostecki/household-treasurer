<script setup lang="ts">
import { Head, router, setLayoutProps } from '@inertiajs/vue3';
import { Archive, ArchiveRestore, Pencil, Trash2 } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import AccountController from '@/actions/App/Http/Controllers/AccountController';
import AccountFormDialog from '@/components/AccountFormDialog.vue';
import AccountMarkCompact from '@/components/AccountMarkCompact.vue';
import ConfirmActionDialog from '@/components/ConfirmActionDialog.vue';
import IconAction from '@/components/IconAction.vue';
import IndexEmptyState from '@/components/IndexEmptyState.vue';
import ListSurface from '@/components/ListSurface.vue';
import StatPanel from '@/components/StatPanel.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { useTranslations } from '@/composables/useTranslations';
import { bankLabel } from '@/lib/account-identity';
import { amountToneClass } from '@/lib/amount-tone';
import { formatMoney } from '@/lib/format';
import { index as accountsRoute } from '@/routes/accounts';
import type { AccountRow, AccountsPageProps } from '@/types/accounts';

const props = defineProps<AccountsPageProps>();

const t = useTranslations();

const createDialogOpen = ref(false);
const editDialogOpen = ref(false);
const editingAccount = ref<AccountRow | null>(null);

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
            title: t.nav.accounts,
            href: accountsRoute(),
        },
    ],
});

function accountTypeLabel(type: AccountRow['type']): string {
    return type === 'bank' ? t.accounts.type_bank : t.accounts.type_cash;
}

function accountCaption(account: AccountRow): string | null {
    if (account.type === 'cash') {
        return t.accounts.cash_caption;
    }

    return account.bank === null ? null : bankLabel(t.accounts, account.bank);
}

function balanceToneClass(account: AccountRow): string {
    if (!account.is_archived) {
        return amountToneClass(account.current_balance_minor_units);
    }

    return account.current_balance_minor_units < 0
        ? amountToneClass(account.current_balance_minor_units)
        : '';
}

const metrics = computed(() => [
    {
        id: 'liquidity',
        label: t.accounts.spendable_liquidity,
        value: formatMoney(props.liquidity_total_minor_units),
        caption: t.accounts.liquidity_caption,
        toneClass: amountToneClass(props.liquidity_total_minor_units),
    },
    {
        id: 'total-funds',
        label: t.accounts.total_funds,
        value: formatMoney(props.total_funds_minor_units),
        caption: t.accounts.total_funds_caption,
        toneClass: amountToneClass(props.total_funds_minor_units),
    },
]);

function openEditDialog(account: AccountRow): void {
    editingAccount.value = account;
    editDialogOpen.value = true;
}

function toggleShowArchived(checked: boolean): void {
    router.get(
        accountsRoute({ query: { show_archived: checked ? 1 : undefined } }),
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
    <Head :title="t.accounts.title" />

    <div class="flex flex-1 flex-col gap-4 p-4 md:gap-5">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="space-y-1">
                <h1 class="page-title">{{ t.accounts.title }}</h1>
                <p class="text-muted-foreground text-sm">
                    {{ t.accounts.list_description }}
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-3">
                <div
                    v-if="archived_accounts_count > 0 || show_archived"
                    class="flex items-center gap-2"
                >
                    <Switch
                        id="show-archived-accounts"
                        v-model="showArchived"
                        :disabled="isTogglingArchived"
                    />
                    <Label
                        for="show-archived-accounts"
                        class="text-muted-foreground text-sm"
                    >
                        {{ t.accounts.show_archived }}
                    </Label>
                </div>
                <Button @click="createDialogOpen = true">
                    {{ t.accounts.add }}
                </Button>
            </div>
        </div>

        <StatPanel :metrics="metrics" />

        <IndexEmptyState
            v-if="accounts.length === 0 && archived_accounts_count > 0"
            :title="t.accounts.all_archived_title"
            :description="t.accounts.all_archived_description"
        >
            <template #actions>
                <Button
                    :disabled="isTogglingArchived"
                    @click="showArchived = true"
                >
                    {{ t.accounts.show_archived }}
                </Button>
                <Button variant="outline" @click="createDialogOpen = true">
                    {{ t.accounts.add }}
                </Button>
            </template>
        </IndexEmptyState>

        <IndexEmptyState
            v-else-if="accounts.length === 0"
            :title="t.accounts.empty_title"
            :description="t.accounts.empty_description"
        >
            <template #actions>
                <Button @click="createDialogOpen = true">
                    {{ t.accounts.add }}
                </Button>
            </template>
        </IndexEmptyState>

        <ListSurface v-else flush>
            <div class="accounts-grid accounts-header-row">
                <div class="accounts-cell-name">{{ t.accounts.name }}</div>
                <div class="accounts-cell-type">{{ t.accounts.type }}</div>
                <div class="accounts-cell-balance">
                    {{ t.accounts.current_balance }}
                </div>
                <div class="accounts-cell-actions">
                    {{ t.accounts.actions }}
                </div>
            </div>
            <div
                v-for="account in accounts"
                :key="account.id"
                class="accounts-grid accounts-row"
                :class="{ 'accounts-row-archived': account.is_archived }"
            >
                <div class="accounts-cell-name">
                    <AccountMarkCompact
                        size="md"
                        :type="account.type"
                        :bank="account.bank"
                    />
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <p class="font-medium">{{ account.name }}</p>
                            <Badge v-if="account.is_archived" variant="neutral">
                                {{ t.accounts.archived_badge }}
                            </Badge>
                        </div>
                        <p
                            v-if="accountCaption(account)"
                            class="meta text-muted-foreground"
                        >
                            {{ accountCaption(account) }}
                        </p>
                    </div>
                </div>

                <p class="accounts-cell-type">
                    {{ accountTypeLabel(account.type) }}
                </p>

                <p
                    class="accounts-cell-balance amount"
                    :class="balanceToneClass(account)"
                >
                    {{ formatMoney(account.current_balance_minor_units) }}
                </p>

                <div class="accounts-cell-actions">
                    <IconAction
                        v-if="!account.is_archived"
                        :icon="Pencil"
                        :label="t.accounts.edit"
                        @click="openEditDialog(account)"
                    />
                    <ConfirmActionDialog
                        v-if="account.can_delete"
                        :form="
                            AccountController.destroy.form(account, filterQuery)
                        "
                        :title="t.accounts.delete_confirm_title"
                        :description="t.accounts.delete_confirm_body"
                        :confirm-label="t.accounts.delete"
                        :cancel-label="t.accounts.cancel"
                        destructive
                    >
                        <template #trigger>
                            <IconAction
                                as-dialog-trigger
                                :icon="Trash2"
                                :label="t.accounts.delete"
                                destructive
                            />
                        </template>
                    </ConfirmActionDialog>
                    <ConfirmActionDialog
                        v-if="account.can_archive"
                        :form="
                            AccountController.archive.form(account, filterQuery)
                        "
                        :title="t.accounts.archive_confirm_title"
                        :description="t.accounts.archive_confirm_body"
                        :confirm-label="t.accounts.archive"
                        :cancel-label="t.accounts.cancel"
                    >
                        <template #trigger>
                            <IconAction
                                as-dialog-trigger
                                :icon="Archive"
                                :label="t.accounts.archive"
                            />
                        </template>
                    </ConfirmActionDialog>
                    <ConfirmActionDialog
                        v-if="account.can_restore"
                        :form="
                            AccountController.restore.form(account, filterQuery)
                        "
                        :title="t.accounts.restore_confirm_title"
                        :description="t.accounts.restore_confirm_body"
                        :confirm-label="t.accounts.restore"
                        :cancel-label="t.accounts.cancel"
                    >
                        <template #trigger>
                            <IconAction
                                as-dialog-trigger
                                :icon="ArchiveRestore"
                                :label="t.accounts.restore"
                            />
                        </template>
                    </ConfirmActionDialog>
                </div>
            </div>
        </ListSurface>
    </div>

    <AccountFormDialog
        v-model:open="createDialogOpen"
        mode="create"
        :show-archived="show_archived"
    />

    <AccountFormDialog
        v-model:open="editDialogOpen"
        mode="edit"
        :account="editingAccount"
        :show-archived="show_archived"
    />
</template>
