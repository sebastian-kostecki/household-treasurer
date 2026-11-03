<script setup lang="ts">
import { Head, router, setLayoutProps } from '@inertiajs/vue3';
import { Archive, ArchiveRestore, Pencil, Trash2 } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import AccountController from '@/actions/App/Http/Controllers/AccountController';
import AccountFormDialog from '@/components/AccountFormDialog.vue';
import AccountIdentityBlock from '@/components/AccountIdentityBlock.vue';
import ConfirmActionDialog from '@/components/ConfirmActionDialog.vue';
import IconAction from '@/components/IconAction.vue';
import IndexEmptyState from '@/components/IndexEmptyState.vue';
import ListSurface from '@/components/ListSurface.vue';
import StatTile from '@/components/StatTile.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { useTranslations } from '@/composables/useTranslations';
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
            <h1 class="text-lg font-semibold">{{ t.accounts.title }}</h1>
            <Button @click="createDialogOpen = true">
                {{ t.accounts.add }}
            </Button>
        </div>

        <StatTile
            :label="t.accounts.liquidity_total"
            :amount-minor-units="liquidity_total_minor_units"
        />

        <div
            v-if="archived_accounts_count > 0 || show_archived"
            class="flex items-center gap-2"
        >
            <Checkbox
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
                <Button variant="secondary" @click="createDialogOpen = true">
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

        <ListSurface v-else :description="t.accounts.list_description">
            <div
                v-for="account in accounts"
                :key="account.id"
                class="border-border/60 hover:bg-foreground/[0.03] flex flex-wrap items-center justify-between gap-x-4 gap-y-3 border-b py-3 transition-colors last:border-b-0"
                :class="account.is_archived ? 'opacity-60' : ''"
            >
                <AccountIdentityBlock
                    class="min-w-0 flex-1 basis-40"
                    :name="account.name"
                    :type="account.type"
                    :bank="account.bank"
                    :mark_url="account.mark_url"
                >
                    <div class="flex flex-wrap items-center gap-2">
                        <p class="font-medium">{{ account.name }}</p>
                        <Badge v-if="account.is_archived" variant="secondary">
                            {{ t.accounts.archived_badge }}
                        </Badge>
                    </div>
                    <p class="text-muted-foreground text-sm">
                        {{ accountTypeLabel(account.type) }}
                    </p>
                </AccountIdentityBlock>

                <p
                    class="shrink-0 font-medium tabular-nums"
                    :class="
                        amountToneClass(account.current_balance_minor_units)
                    "
                >
                    {{ formatMoney(account.current_balance_minor_units) }}
                </p>

                <div
                    class="flex w-full flex-wrap gap-1 sm:w-auto sm:justify-end"
                >
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
