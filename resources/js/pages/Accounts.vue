<script setup lang="ts">
import { Form, Head, router, setLayoutProps } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AccountController from '@/actions/App/Http/Controllers/AccountController';
import AccountFormDialog, {
    type AccountFormAccount,
} from '@/components/AccountFormDialog.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
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
    DialogTrigger,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { useTranslations } from '@/composables/useTranslations';
import { formatDate, formatMoney } from '@/lib/format';
import { index as accountsRoute } from '@/routes/accounts';

type AccountRow = AccountFormAccount & {
    current_balance_grosze: number;
    is_negative: boolean;
    is_archived: boolean;
    can_delete: boolean;
    can_archive: boolean;
    can_restore: boolean;
};

const props = defineProps<{
    accounts: AccountRow[];
    liquidity_total_grosze: number;
    show_archived: boolean;
}>();

const t = useTranslations();

const createDialogOpen = ref(false);
const editDialogOpen = ref(false);
const editingAccount = ref<AccountRow | null>(null);

const liquidityClass = computed(() =>
    props.liquidity_total_grosze < 0 ? 'text-destructive' : 'text-success',
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

    if (account.current_balance_grosze > 0) {
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
                    {{ formatMoney(liquidity_total_grosze) }}
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

        <Card v-if="accounts.length === 0">
            <CardHeader>
                <CardTitle class="text-base">{{ t.accounts.title }}</CardTitle>
                <CardDescription>{{ t.accounts.empty }}</CardDescription>
            </CardHeader>
            <CardContent>
                <p class="text-muted-foreground text-sm">
                    {{ t.accounts.empty }}
                </p>
            </CardContent>
        </Card>

        <div v-else class="space-y-3">
            <Card
                v-for="account in accounts"
                :key="account.id"
                :class="account.is_archived ? 'opacity-60' : ''"
            >
                <CardHeader class="gap-3 pb-3 sm:flex-row sm:items-start sm:justify-between">
                    <div class="space-y-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <CardTitle class="text-base">{{ account.name }}</CardTitle>
                            <Badge
                                v-if="account.is_archived"
                                variant="secondary"
                            >
                                {{ t.accounts.archived_badge }}
                            </Badge>
                        </div>
                        <CardDescription>
                            {{ accountTypeLabel(account.type) }}
                            · {{ t.accounts.opening_date }}
                            {{ formatDate(account.opening_date) }}
                        </CardDescription>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <Button
                            v-if="!account.is_archived"
                            variant="outline"
                            size="sm"
                            @click="openEditDialog(account)"
                        >
                            {{ t.accounts.edit }}
                        </Button>
                        <Dialog v-if="account.can_delete">
                            <DialogTrigger as-child>
                                <Button variant="destructive" size="sm">
                                    {{ t.accounts.delete }}
                                </Button>
                            </DialogTrigger>
                            <DialogContent>
                                <Form
                                    v-bind="
                                        AccountController.destroy.form(account)
                                    "
                                    v-slot="{ processing }"
                                >
                                    <DialogHeader>
                                        <DialogTitle>
                                            {{
                                                t.accounts.delete_confirm_title
                                            }}
                                        </DialogTitle>
                                        <DialogDescription>
                                            {{
                                                t.accounts.delete_confirm_body
                                            }}
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
                            <DialogTrigger as-child>
                                <Button variant="outline" size="sm">
                                    {{ t.accounts.archive }}
                                </Button>
                            </DialogTrigger>
                            <DialogContent>
                                <Form
                                    v-bind="
                                        AccountController.archive.form(account)
                                    "
                                    v-slot="{ processing }"
                                >
                                    <DialogHeader>
                                        <DialogTitle>
                                            {{
                                                t.accounts.archive_confirm_title
                                            }}
                                        </DialogTitle>
                                        <DialogDescription>
                                            {{
                                                t.accounts.archive_confirm_body
                                            }}
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
                            <DialogTrigger as-child>
                                <Button variant="outline" size="sm">
                                    {{ t.accounts.restore }}
                                </Button>
                            </DialogTrigger>
                            <DialogContent>
                                <Form
                                    v-bind="
                                        AccountController.restore.form(account)
                                    "
                                    v-slot="{ processing }"
                                >
                                    <DialogHeader>
                                        <DialogTitle>
                                            {{
                                                t.accounts.restore_confirm_title
                                            }}
                                        </DialogTitle>
                                        <DialogDescription>
                                            {{
                                                t.accounts.restore_confirm_body
                                            }}
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
                </CardHeader>
                <CardContent class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <p class="text-muted-foreground text-xs">
                            {{ t.accounts.current_balance }}
                        </p>
                        <p
                            class="text-lg font-semibold"
                            :class="balanceClass(account)"
                        >
                            {{ formatMoney(account.current_balance_grosze) }}
                        </p>
                        <Badge
                            v-if="account.is_negative"
                            variant="destructive"
                            class="mt-2"
                        >
                            {{ t.accounts.negative_balance }}
                        </Badge>
                    </div>
                    <div>
                        <p class="text-muted-foreground text-xs">
                            {{ t.accounts.opening_balance }}
                        </p>
                        <p class="text-sm">
                            {{ formatMoney(account.opening_balance_grosze) }}
                        </p>
                    </div>
                </CardContent>
            </Card>
        </div>
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
