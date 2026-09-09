<script setup lang="ts">
import { Form, Head, router, setLayoutProps } from '@inertiajs/vue3';
import { Archive, ArchiveRestore, Pencil, Trash2 } from '@lucide/vue';
import { computed, ref } from 'vue';
import TermDepositController from '@/actions/App/Http/Controllers/TermDepositController';
import TermDepositFormDialog, {
    type TermDepositFormTermDeposit,
} from '@/components/TermDepositFormDialog.vue';
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
import type { AccountBank } from '@/lib/account-identity';
import { formatDate, formatMoney } from '@/lib/format';
import { index as termDepositsRoute } from '@/routes/term-deposits';

type TermDepositRow = TermDepositFormTermDeposit & {
    bank: AccountBank;
    mark_url: string | null;
    current_balance_minor_units: number;
    is_negative: boolean;
    is_archived: boolean;
    can_delete: boolean;
    can_archive: boolean;
    can_restore: boolean;
};

const props = defineProps<{
    termDeposits: TermDepositRow[];
    term_deposits_total_minor_units: number;
    show_archived: boolean;
}>();

const t = useTranslations();

const createDialogOpen = ref(false);
const editDialogOpen = ref(false);
const editingTermDeposit = ref<TermDepositRow | null>(null);

const totalClass = computed(() =>
    props.term_deposits_total_minor_units < 0
        ? 'text-destructive'
        : 'text-success',
);

setLayoutProps({
    breadcrumbs: [
        {
            title: t.nav.term_deposits,
            href: termDepositsRoute(),
        },
    ],
});

function balanceClass(termDeposit: TermDepositRow): string {
    if (termDeposit.is_negative) {
        return 'text-destructive';
    }

    if (termDeposit.current_balance_minor_units > 0) {
        return 'text-success';
    }

    return '';
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
        { preserveState: true, preserveScroll: true },
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

        <Card>
            <CardHeader class="pb-2">
                <CardDescription>
                    {{ t.term_deposits.total }}
                </CardDescription>
                <CardTitle class="text-2xl" :class="totalClass">
                    {{ formatMoney(term_deposits_total_minor_units) }}
                </CardTitle>
            </CardHeader>
        </Card>

        <div class="flex items-center gap-2">
            <Checkbox
                id="show-archived-term-deposits"
                :checked="show_archived"
                @update:checked="toggleShowArchived"
            />
            <Label
                for="show-archived-term-deposits"
                class="text-muted-foreground text-sm"
            >
                {{ t.term_deposits.show_archived }}
            </Label>
        </div>

        <IndexEmptyState
            v-if="termDeposits.length === 0"
            :title="t.term_deposits.empty_title"
            :description="t.term_deposits.empty_description"
        >
            <template #actions>
                <Button @click="createDialogOpen = true">
                    {{ t.term_deposits.add }}
                </Button>
            </template>
        </IndexEmptyState>

        <ListSurface
            v-else
            :title="t.term_deposits.title"
            :description="t.term_deposits.list_description"
        >
            <div
                v-for="termDeposit in termDeposits"
                :key="termDeposit.id"
                class="border-border/60 hover:bg-foreground/[0.03] flex flex-wrap items-center justify-between gap-x-4 gap-y-3 border-b py-3 transition-colors last:border-b-0"
                :class="termDeposit.is_archived ? 'opacity-60' : ''"
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
                    <Dialog v-if="termDeposit.can_delete">
                        <IconAction
                            as-dialog-trigger
                            :icon="Trash2"
                            :label="t.term_deposits.delete"
                            destructive
                        />
                        <DialogContent>
                            <Form
                                v-bind="
                                    TermDepositController.destroy.form(
                                        termDeposit,
                                    )
                                "
                                v-slot="{ processing }"
                            >
                                <DialogHeader>
                                    <DialogTitle>
                                        {{
                                            t.term_deposits.delete_confirm_title
                                        }}
                                    </DialogTitle>
                                    <DialogDescription>
                                        {{
                                            t.term_deposits.delete_confirm_body
                                        }}
                                    </DialogDescription>
                                </DialogHeader>
                                <DialogFooter class="gap-2">
                                    <DialogClose as-child>
                                        <Button
                                            type="button"
                                            variant="secondary"
                                        >
                                            {{ t.term_deposits.cancel }}
                                        </Button>
                                    </DialogClose>
                                    <Button
                                        type="submit"
                                        variant="destructive"
                                        :disabled="processing"
                                    >
                                        {{ t.term_deposits.delete }}
                                    </Button>
                                </DialogFooter>
                            </Form>
                        </DialogContent>
                    </Dialog>
                    <Dialog v-if="termDeposit.can_archive">
                        <IconAction
                            as-dialog-trigger
                            :icon="Archive"
                            :label="t.term_deposits.archive"
                        />
                        <DialogContent>
                            <Form
                                v-bind="
                                    TermDepositController.archive.form(
                                        termDeposit,
                                    )
                                "
                                v-slot="{ processing }"
                            >
                                <DialogHeader>
                                    <DialogTitle>
                                        {{
                                            t.term_deposits
                                                .archive_confirm_title
                                        }}
                                    </DialogTitle>
                                    <DialogDescription>
                                        {{
                                            t.term_deposits.archive_confirm_body
                                        }}
                                    </DialogDescription>
                                </DialogHeader>
                                <DialogFooter class="gap-2">
                                    <DialogClose as-child>
                                        <Button
                                            type="button"
                                            variant="secondary"
                                        >
                                            {{ t.term_deposits.cancel }}
                                        </Button>
                                    </DialogClose>
                                    <Button
                                        type="submit"
                                        :disabled="processing"
                                    >
                                        {{ t.term_deposits.archive }}
                                    </Button>
                                </DialogFooter>
                            </Form>
                        </DialogContent>
                    </Dialog>
                    <Dialog v-if="termDeposit.can_restore">
                        <IconAction
                            as-dialog-trigger
                            :icon="ArchiveRestore"
                            :label="t.term_deposits.restore"
                        />
                        <DialogContent>
                            <Form
                                v-bind="
                                    TermDepositController.restore.form(
                                        termDeposit,
                                    )
                                "
                                v-slot="{ processing }"
                            >
                                <DialogHeader>
                                    <DialogTitle>
                                        {{
                                            t.term_deposits
                                                .restore_confirm_title
                                        }}
                                    </DialogTitle>
                                    <DialogDescription>
                                        {{
                                            t.term_deposits.restore_confirm_body
                                        }}
                                    </DialogDescription>
                                </DialogHeader>
                                <DialogFooter class="gap-2">
                                    <DialogClose as-child>
                                        <Button
                                            type="button"
                                            variant="secondary"
                                        >
                                            {{ t.term_deposits.cancel }}
                                        </Button>
                                    </DialogClose>
                                    <Button
                                        type="submit"
                                        :disabled="processing"
                                    >
                                        {{ t.term_deposits.restore }}
                                    </Button>
                                </DialogFooter>
                            </Form>
                        </DialogContent>
                    </Dialog>
                </div>
            </div>
        </ListSurface>
    </div>

    <TermDepositFormDialog v-model:open="createDialogOpen" mode="create" />

    <TermDepositFormDialog
        v-model:open="editDialogOpen"
        mode="edit"
        :term-deposit="editingTermDeposit"
    />
</template>
