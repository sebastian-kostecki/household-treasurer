<script setup lang="ts">
import { Form, Head, router, setLayoutProps } from '@inertiajs/vue3';
import { Archive, ArchiveRestore, Pencil, Trash2 } from '@lucide/vue';
import { computed, ref } from 'vue';
import AccountController from '@/actions/App/Http/Controllers/AccountController';
import AccountFormDialog, {
    type AccountFormAccount,
} from '@/components/AccountFormDialog.vue';
import AccountIdentityBlock from '@/components/AccountIdentityBlock.vue';
import IconAction from '@/components/IconAction.vue';
import IndexEmptyState from '@/components/IndexEmptyState.vue';
import ListSurface from '@/components/ListSurface.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { useTranslations } from '@/composables/useTranslations';
import { formatMoney } from '@/lib/format';
import { index as accountsRoute } from '@/routes/accounts';

type AccountRow = AccountFormAccount & {
    mark_url: string | null;
    current_balance_minor_units: number;
    is_negative: boolean;
    is_archived: boolean;
    can_delete: boolean;
    can_archive: boolean;
    can_restore: boolean;
};

const props = defineProps<{
    accounts: AccountRow[];
    liquidity_total_minor_units: number;
    show_archived: boolean;
}>();

const t = useTranslations();

const createDialogOpen = ref(false);
const editDialogOpen = ref(false);
const editingAccount = ref<AccountRow | null>(null);

const liquidityClass = computed(() =>
    props.liquidity_total_minor_units < 0 ? 'text-destructive' : 'text-success',
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

function balanceClass(account: AccountRow): string {
    if (account.is_negative) {
        return 'text-destructive';
    }

    if (account.current_balance_minor_units > 0) {
        return 'text-success';
    }

    return '';
}

function openEditDialog(account: AccountRow): void {
    editingAccount.value = account;
    editDialogOpen.value = true;
}

function toggleShowArchived(checked: boolean): void {
    router.get(
        accountsRoute({ query: { show_archived: checked ? 1 : undefined } }),
        {},
        { preserveState: true, preserveScroll: true },
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

        <Card>
            <CardHeader class="pb-2">
                <CardDescription>
                    {{ t.accounts.liquidity_total }}
                </CardDescription>
                <CardTitle class="text-2xl" :class="liquidityClass">
                    {{ formatMoney(liquidity_total_minor_units) }}
                </CardTitle>
            </CardHeader>
        </Card>

        <div class="flex items-center gap-2">
            <Checkbox
                id="show-archived-accounts"
                :checked="show_archived"
                @update:checked="toggleShowArchived"
            />
            <Label
                for="show-archived-accounts"
                class="text-muted-foreground text-sm"
            >
                {{ t.accounts.show_archived }}
            </Label>
        </div>

        <IndexEmptyState
            v-if="accounts.length === 0"
            :title="t.accounts.empty_title"
            :description="t.accounts.empty_description"
        >
            <template #actions>
                <Button @click="createDialogOpen = true">
                    {{ t.accounts.add }}
                </Button>
            </template>
        </IndexEmptyState>

        <ListSurface
            v-else
            :title="t.accounts.title"
            :description="t.accounts.list_description"
        >
            <div
                v-for="account in accounts"
                :key="account.id"
                class="flex flex-wrap items-center justify-between gap-x-4 gap-y-3 border-b border-border/60 py-3 transition-colors last:border-b-0 hover:bg-foreground/[0.03]"
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
                        <Badge
                            v-if="account.is_archived"
                            variant="secondary"
                        >
                            {{ t.accounts.archived_badge }}
                        </Badge>
                    </div>
                    <p class="text-muted-foreground text-sm">
                        {{ accountTypeLabel(account.type) }}
                    </p>
                </AccountIdentityBlock>

                <p
                    class="shrink-0 font-medium tabular-nums"
                    :class="balanceClass(account)"
                >
                    {{ formatMoney(account.current_balance_minor_units) }}
                </p>

                <div class="flex w-full flex-wrap gap-1 sm:w-auto sm:justify-end">
                    <IconAction
                        v-if="!account.is_archived"
                        :icon="Pencil"
                        :label="t.accounts.edit"
                        @click="openEditDialog(account)"
                    />
                    <Dialog v-if="account.can_delete">
                        <IconAction
                            as-dialog-trigger
                            :icon="Trash2"
                            :label="t.accounts.delete"
                            destructive
                        />
                        <DialogContent>
                            <Form
                                v-bind="AccountController.destroy.form(account)"
                                v-slot="{ processing }"
                            >
                                <DialogHeader>
                                    <DialogTitle>
                                        {{ t.accounts.delete_confirm_title }}
                                    </DialogTitle>
                                    <DialogDescription>
                                        {{ t.accounts.delete_confirm_body }}
                                    </DialogDescription>
                                </DialogHeader>
                                <DialogFooter class="gap-2">
                                    <DialogClose as-child>
                                        <Button
                                            type="button"
                                            variant="secondary"
                                        >
                                            {{ t.accounts.cancel }}
                                        </Button>
                                    </DialogClose>
                                    <Button
                                        type="submit"
                                        variant="destructive"
                                        :disabled="processing"
                                    >
                                        {{ t.accounts.delete }}
                                    </Button>
                                </DialogFooter>
                            </Form>
                        </DialogContent>
                    </Dialog>
                    <Dialog v-if="account.can_archive">
                        <IconAction
                            as-dialog-trigger
                            :icon="Archive"
                            :label="t.accounts.archive"
                        />
                        <DialogContent>
                            <Form
                                v-bind="AccountController.archive.form(account)"
                                v-slot="{ processing }"
                            >
                                <DialogHeader>
                                    <DialogTitle>
                                        {{ t.accounts.archive_confirm_title }}
                                    </DialogTitle>
                                    <DialogDescription>
                                        {{ t.accounts.archive_confirm_body }}
                                    </DialogDescription>
                                </DialogHeader>
                                <DialogFooter class="gap-2">
                                    <DialogClose as-child>
                                        <Button
                                            type="button"
                                            variant="secondary"
                                        >
                                            {{ t.accounts.cancel }}
                                        </Button>
                                    </DialogClose>
                                    <Button
                                        type="submit"
                                        :disabled="processing"
                                    >
                                        {{ t.accounts.archive }}
                                    </Button>
                                </DialogFooter>
                            </Form>
                        </DialogContent>
                    </Dialog>
                    <Dialog v-if="account.can_restore">
                        <IconAction
                            as-dialog-trigger
                            :icon="ArchiveRestore"
                            :label="t.accounts.restore"
                        />
                        <DialogContent>
                            <Form
                                v-bind="AccountController.restore.form(account)"
                                v-slot="{ processing }"
                            >
                                <DialogHeader>
                                    <DialogTitle>
                                        {{ t.accounts.restore_confirm_title }}
                                    </DialogTitle>
                                    <DialogDescription>
                                        {{ t.accounts.restore_confirm_body }}
                                    </DialogDescription>
                                </DialogHeader>
                                <DialogFooter class="gap-2">
                                    <DialogClose as-child>
                                        <Button
                                            type="button"
                                            variant="secondary"
                                        >
                                            {{ t.accounts.cancel }}
                                        </Button>
                                    </DialogClose>
                                    <Button
                                        type="submit"
                                        :disabled="processing"
                                    >
                                        {{ t.accounts.restore }}
                                    </Button>
                                </DialogFooter>
                            </Form>
                        </DialogContent>
                    </Dialog>
                </div>
            </div>
        </ListSurface>
    </div>

    <AccountFormDialog
        v-model:open="createDialogOpen"
        mode="create"
    />

    <AccountFormDialog
        v-model:open="editDialogOpen"
        mode="edit"
        :account="editingAccount"
    />
</template>
