<script setup lang="ts">
import { Form, Head, setLayoutProps } from '@inertiajs/vue3';
import { ref } from 'vue';
import OwnTransferController from '@/actions/App/Http/Controllers/OwnTransferController';
import OwnTransferFormDialog, {
    type OwnTransferFormAccount,
    type OwnTransferFormTransfer,
} from '@/components/OwnTransferFormDialog.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
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
import { useTranslations } from '@/composables/useTranslations';
import { formatDate, formatMoney } from '@/lib/format';
import { operations } from '@/routes';

type OwnTransferRow = OwnTransferFormTransfer & {
    source_account_name: string;
    destination_account_name: string;
};

const props = defineProps<{
    ownTransfers: OwnTransferRow[];
    accounts: OwnTransferFormAccount[];
}>();

const t = useTranslations();

const createDialogOpen = ref(false);
const editDialogOpen = ref(false);
const editingTransfer = ref<OwnTransferRow | null>(null);

setLayoutProps({
    breadcrumbs: [
        {
            title: t.nav.operations,
            href: operations(),
        },
    ],
});

function openEditDialog(transfer: OwnTransferRow): void {
    editingTransfer.value = transfer;
    editDialogOpen.value = true;
}
</script>

<template>
    <Head :title="t.operations.title" />

    <div class="flex flex-1 flex-col gap-4 p-4 md:gap-5">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-lg font-semibold">{{ t.operations.title }}</h1>
            <div class="flex flex-wrap gap-2">
                <Button
                    :disabled="accounts.length < 2"
                    :title="
                        accounts.length < 2
                            ? t.operations.transfer_requires_two_accounts
                            : undefined
                    "
                    @click="createDialogOpen = true"
                >
                    {{ t.operations.add_transfer }}
                </Button>
                <Button disabled :title="t.operations.import_hint">
                    {{ t.operations.import_csv }}
                </Button>
            </div>
        </div>

        <Card v-if="ownTransfers.length === 0">
            <CardHeader>
                <CardTitle class="text-base">
                    {{ t.operations.title }}
                </CardTitle>
                <CardDescription>{{ t.operations.empty }}</CardDescription>
            </CardHeader>
        </Card>

        <Card v-else class="hidden md:block">
            <CardHeader>
                <CardTitle class="text-base">
                    {{ t.operations.own_transfers }}
                </CardTitle>
            </CardHeader>
            <CardContent class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b text-left">
                            <th class="pb-2 font-medium">
                                {{ t.operations.date }}
                            </th>
                            <th class="pb-2 font-medium">
                                {{ t.operations.description }}
                            </th>
                            <th class="pb-2 font-medium">
                                {{ t.operations.accounts }}
                            </th>
                            <th class="pb-2 text-right font-medium">
                                {{ t.operations.amount }}
                            </th>
                            <th class="pb-2 text-right font-medium">
                                {{ t.operations.actions }}
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="transfer in ownTransfers"
                            :key="transfer.id"
                            class="border-b last:border-b-0"
                        >
                            <td class="py-3">
                                {{ formatDate(transfer.transfer_date) }}
                            </td>
                            <td class="py-3">{{ transfer.description }}</td>
                            <td class="py-3">
                                <div class="flex flex-wrap gap-1">
                                    <Badge variant="secondary">
                                        {{ transfer.source_account_name }}
                                    </Badge>
                                    <span class="text-muted-foreground">→</span>
                                    <Badge variant="secondary">
                                        {{ transfer.destination_account_name }}
                                    </Badge>
                                </div>
                            </td>
                            <td class="py-3 text-right font-medium">
                                {{ formatMoney(transfer.amount_grosze) }}
                            </td>
                            <td class="py-3">
                                <div class="flex justify-end gap-2">
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        @click="openEditDialog(transfer)"
                                    >
                                        {{ t.accounts.edit }}
                                    </Button>
                                    <Dialog>
                                        <DialogTrigger as-child>
                                            <Button
                                                variant="destructive"
                                                size="sm"
                                            >
                                                {{ t.accounts.delete }}
                                            </Button>
                                        </DialogTrigger>
                                        <DialogContent>
                                            <Form
                                                v-bind="
                                                    OwnTransferController.destroy.form(
                                                        transfer,
                                                    )
                                                "
                                                v-slot="{ processing }"
                                            >
                                                <DialogHeader>
                                                    <DialogTitle>
                                                        {{
                                                            t.operations.delete_transfer_confirm_title
                                                        }}
                                                    </DialogTitle>
                                                    <DialogDescription>
                                                        {{
                                                            t.operations.delete_transfer_confirm_body
                                                        }}
                                                    </DialogDescription>
                                                </DialogHeader>
                                                <DialogFooter class="gap-2">
                                                    <DialogClose as-child>
                                                        <Button
                                                            type="button"
                                                            variant="secondary"
                                                        >
                                                            {{
                                                                t.accounts.cancel
                                                            }}
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
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </CardContent>
        </Card>

        <div v-if="ownTransfers.length > 0" class="space-y-3 md:hidden">
            <Card v-for="transfer in ownTransfers" :key="transfer.id">
                <CardHeader class="gap-3 pb-3">
                    <div class="flex items-start justify-between gap-3">
                        <div class="space-y-1">
                            <CardTitle class="text-base">
                                {{ transfer.description }}
                            </CardTitle>
                            <CardDescription>
                                {{ formatDate(transfer.transfer_date) }}
                            </CardDescription>
                        </div>
                        <p class="font-semibold">
                            {{ formatMoney(transfer.amount_grosze) }}
                        </p>
                    </div>
                    <div class="flex flex-wrap gap-1">
                        <Badge variant="secondary">
                            {{ transfer.source_account_name }}
                        </Badge>
                        <span class="text-muted-foreground">→</span>
                        <Badge variant="secondary">
                            {{ transfer.destination_account_name }}
                        </Badge>
                    </div>
                </CardHeader>
                <CardContent class="flex gap-2">
                    <Button
                        variant="outline"
                        size="sm"
                        @click="openEditDialog(transfer)"
                    >
                        {{ t.accounts.edit }}
                    </Button>
                    <Dialog>
                        <DialogTrigger as-child>
                            <Button variant="destructive" size="sm">
                                {{ t.accounts.delete }}
                            </Button>
                        </DialogTrigger>
                        <DialogContent>
                            <Form
                                v-bind="
                                    OwnTransferController.destroy.form(transfer)
                                "
                                v-slot="{ processing }"
                            >
                                <DialogHeader>
                                    <DialogTitle>
                                        {{
                                            t.operations.delete_transfer_confirm_title
                                        }}
                                    </DialogTitle>
                                    <DialogDescription>
                                        {{
                                            t.operations.delete_transfer_confirm_body
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
                </CardContent>
            </Card>
        </div>
    </div>

    <OwnTransferFormDialog
        v-model:open="createDialogOpen"
        mode="create"
        :accounts="accounts"
    />

    <OwnTransferFormDialog
        v-model:open="editDialogOpen"
        mode="edit"
        :transfer="editingTransfer"
        :accounts="accounts"
    />
</template>
