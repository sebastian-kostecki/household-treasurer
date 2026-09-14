<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { MoreHorizontal } from '@lucide/vue';
import { ref, watch } from 'vue';
import CashExpectationController from '@/actions/App/Http/Controllers/CashExpectationController';
import CashExpectationFormDialog, {
    type CashExpectationFormAccount,
    type CashExpectationFormCategory,
} from '@/components/CashExpectationFormDialog.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTranslations } from '@/composables/useTranslations';
import { formatDate, formatMoney, minorUnitsToMoneyInput } from '@/lib/format';

type ExpectationRow = {
    id: number;
    type: 'income' | 'expense';
    expectation_date: string;
    amount_minor_units: number;
    description: string;
    counterparty: string | null;
    is_expense_refund: boolean;
    account_id: number;
    category_id: number;
};

type SettlementCandidate = {
    id: number;
    operation_date: string;
    amount_minor_units: number;
    description: string;
};

const props = defineProps<{
    expectation: ExpectationRow;
    accounts: CashExpectationFormAccount[];
    incomeCategories: CashExpectationFormCategory[];
    expenseCategories: CashExpectationFormCategory[];
}>();

const t = useTranslations();
const spawnDialogOpen = ref(false);
const attachDialogOpen = ref(false);
const editDialogOpen = ref(false);
const attachCandidates = ref<SettlementCandidate[]>([]);
const selectedAttachOperationId = ref<number | ''>('');

const spawnOperationDate = ref('');
const spawnDescription = ref('');
const spawnAmountInput = ref('0,00');
const spawnAccountId = ref<number | ''>('');
const spawnCategoryId = ref<number | ''>('');

function defaultSpawnOperationDate(): string {
    const today = new Date();
    const month = String(today.getMonth() + 1).padStart(2, '0');
    const day = String(today.getDate()).padStart(2, '0');

    return `${today.getFullYear()}-${month}-${day}`;
}

watch(spawnDialogOpen, (open) => {
    if (!open) {
        return;
    }

    spawnOperationDate.value = defaultSpawnOperationDate();
    spawnDescription.value = props.expectation.description;
    spawnAmountInput.value = minorUnitsToMoneyInput(
        Math.abs(props.expectation.amount_minor_units),
    );
    spawnAccountId.value = props.expectation.account_id;
    spawnCategoryId.value = props.expectation.category_id;
});

watch(attachDialogOpen, async (open) => {
    if (!open) {
        attachCandidates.value = [];
        selectedAttachOperationId.value = '';

        return;
    }

    const response = await fetch(
        CashExpectationController.settlementCandidates.url(props.expectation),
        { headers: { Accept: 'application/json' } },
    );
    const payload = (await response.json()) as {
        candidates: SettlementCandidate[];
    };

    attachCandidates.value = payload.candidates;
    selectedAttachOperationId.value = payload.candidates[0]?.id ?? '';
});
</script>

<template>
    <DropdownMenu>
        <DropdownMenuTrigger as-child>
            <Button variant="ghost" size="icon" class="size-8">
                <MoreHorizontal class="size-4" />
                <span class="sr-only">{{ t.budget.more_actions }}</span>
            </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end">
            <DropdownMenuItem @select="editDialogOpen = true">
                {{ t.agenda.edit }}
            </DropdownMenuItem>
            <DropdownMenuItem @select="spawnDialogOpen = true">
                {{ t.agenda.settle_spawn }}
            </DropdownMenuItem>
            <DropdownMenuItem @select="attachDialogOpen = true">
                {{ t.agenda.settle_attach }}
            </DropdownMenuItem>
            <DropdownMenuItem as-child>
                <Form
                    v-bind="
                        CashExpectationController.destroy.form(
                            expectation,
                        )
                    "
                    class="w-full"
                >
                    <button type="submit" class="w-full px-2 py-1.5 text-left">
                        {{ t.agenda.delete }}
                    </button>
                </Form>
            </DropdownMenuItem>
        </DropdownMenuContent>
    </DropdownMenu>

    <CashExpectationFormDialog
        v-model:open="editDialogOpen"
        mode="edit"
        :expectation="expectation"
        :accounts="accounts"
        :income-categories="incomeCategories"
        :expense-categories="expenseCategories"
    />

    <Dialog v-model:open="spawnDialogOpen">
        <DialogContent class="sm:max-w-md">
            <Form
                v-bind="
                    CashExpectationController.spawn.form(expectation)
                "
                class="space-y-4"
                @success="spawnDialogOpen = false"
            >
                <DialogHeader>
                    <DialogTitle>{{ t.agenda.settle_spawn_title }}</DialogTitle>
                    <DialogDescription>
                        {{ t.agenda.settle_spawn_description }}
                    </DialogDescription>
                </DialogHeader>
                <div class="space-y-2">
                    <Label for="spawn-operation-date">{{ t.agenda.date }}</Label>
                    <Input
                        id="spawn-operation-date"
                        v-model="spawnOperationDate"
                        name="operation_date"
                        type="date"
                        required
                    />
                </div>
                <div class="space-y-2">
                    <Label for="spawn-description">
                        {{ t.agenda.description }}
                    </Label>
                    <Input
                        id="spawn-description"
                        v-model="spawnDescription"
                        name="description"
                        required
                    />
                </div>
                <input
                    type="hidden"
                    name="amount_minor_units"
                    :value="Math.abs(expectation.amount_minor_units)"
                />
                <input
                    type="hidden"
                    name="account_id"
                    :value="spawnAccountId"
                />
                <input
                    type="hidden"
                    name="category_id"
                    :value="spawnCategoryId"
                />
                <DialogFooter>
                    <DialogClose as-child>
                        <Button type="button" variant="outline">
                            {{ t.agenda.cancel }}
                        </Button>
                    </DialogClose>
                    <Button type="submit">{{ t.agenda.settle_spawn }}</Button>
                </DialogFooter>
            </Form>
        </DialogContent>
    </Dialog>

    <Dialog v-model:open="attachDialogOpen">
        <DialogContent class="sm:max-w-md">
            <Form
                v-bind="
                    CashExpectationController.attach.form(expectation)
                "
                class="space-y-4"
                @success="attachDialogOpen = false"
            >
                <DialogHeader>
                    <DialogTitle>{{ t.agenda.settle_attach_title }}</DialogTitle>
                    <DialogDescription>
                        {{ t.agenda.settle_attach_description }}
                    </DialogDescription>
                </DialogHeader>
                <div v-if="attachCandidates.length === 0" class="text-sm">
                    {{ t.agenda.settle_attach_empty }}
                </div>
                <div v-else class="space-y-2">
                    <Label for="attach-operation">
                        {{ t.agenda.settle_attach_pick }}
                    </Label>
                    <select
                        id="attach-operation"
                        v-model="selectedAttachOperationId"
                        name="financial_operation_id"
                        class="native-select w-full"
                        required
                    >
                        <option
                            v-for="candidate in attachCandidates"
                            :key="candidate.id"
                            :value="candidate.id"
                        >
                            {{ formatDate(candidate.operation_date) }} —
                            {{ candidate.description }} ({{
                                formatMoney(candidate.amount_minor_units)
                            }})
                        </option>
                    </select>
                </div>
                <DialogFooter>
                    <DialogClose as-child>
                        <Button type="button" variant="outline">
                            {{ t.agenda.cancel }}
                        </Button>
                    </DialogClose>
                    <Button
                        type="submit"
                        :disabled="attachCandidates.length === 0"
                    >
                        {{ t.agenda.settle_attach }}
                    </Button>
                </DialogFooter>
            </Form>
        </DialogContent>
    </Dialog>
</template>
