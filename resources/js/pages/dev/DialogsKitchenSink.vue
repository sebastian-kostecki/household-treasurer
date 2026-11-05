<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { ref } from 'vue';
import DialogsKitchenSinkController from '@/actions/App/Http/Controllers/Dev/DialogsKitchenSinkController';
import AccountFormDialog from '@/components/AccountFormDialog.vue';
import CategoryFormDialog from '@/components/CategoryFormDialog.vue';
import type { CategoryFormCategory } from '@/components/CategoryFormDialog.vue';
import ConfirmActionDialog from '@/components/ConfirmActionDialog.vue';
import CreateBudgetDialog from '@/components/CreateBudgetDialog.vue';
import { Alert } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import type { CategoryAppearanceOptions } from '@/lib/category-appearance';

defineProps<{
    categoryAppearanceOptions: CategoryAppearanceOptions;
}>();

const states = [
    'category-new',
    'category-edit',
    'category-locked',
    'category-errors',
    'category-saving',
    'category-bad-amount',
    'category-month-item-kind',
    'budget-create',
    'budget-errors',
    'confirm-plain',
    'confirm-destructive',
    'confirm-saving',
    'confirm-deficit',
    'account',
] as const;

type SinkState = (typeof states)[number];

const labels: Record<SinkState, string> = {
    'category-new': 'Kategoria: nowa',
    'category-edit': 'Kategoria: edycja',
    'category-locked': 'Kategoria: typ zablokowany',
    'category-errors': 'Kategoria: błędy pól',
    'category-saving': 'Kategoria: zapis w toku',
    'category-bad-amount': 'Kategoria: zła kwota',
    'category-month-item-kind': 'Kategoria: rodzaj pozycji (wydatek)',
    'budget-create': 'Utwórz budżet',
    'budget-errors': 'Utwórz budżet: błędy',
    'confirm-plain': 'Potwierdzenie zwykłe',
    'confirm-destructive': 'Potwierdzenie destrukcyjne',
    'confirm-saving': 'Potwierdzenie: zapis w toku',
    'confirm-deficit': 'Potwierdzenie: ostrzeżenie o deficycie',
    account: 'Konto (powłoka)',
};

const category: CategoryFormCategory = {
    id: 1,
    name: 'Jedzenie',
    icon: 'shopping-cart',
    color: 'blue',
    type: 'expense',
    planned_amount_minor_units: 120000,
    is_paced: true,
    month_item_kind: 'running',
};

const savingForm = DialogsKitchenSinkController.validate.form();
const validateForm = DialogsKitchenSinkController.validate.form();
const plainForm = { action: '#', method: 'post' } as const;

function initialState(): SinkState | null {
    if (typeof window === 'undefined') {
        return null;
    }

    const requested = new URLSearchParams(window.location.search).get('open');

    return states.find((state) => state === requested) ?? null;
}

const openState = ref<SinkState | null>(initialState());

function isOpen(state: SinkState): boolean {
    return openState.value === state;
}

function setOpen(state: SinkState, value: boolean): void {
    openState.value = value ? state : null;
}
</script>

<template>
    <Head title="Dialogs kitchen sink" />

    <div class="flex flex-1 flex-col gap-6 p-4">
        <header class="space-y-1">
            <h1 class="text-xl font-semibold">Dialogs kitchen sink</h1>
            <p class="text-muted-foreground text-sm">
                Strona deweloperska (tylko środowisko local). Każdy przycisk
                otwiera dialog; <code>?open=&lt;stan&gt;</code> otwiera stan
                przy wejściu. Zapisy formularzy kategorii i budżetu trafiają na
                ich zwykłe trasy.
            </p>
        </header>

        <div class="flex flex-wrap gap-2">
            <Button
                v-for="state in states"
                :key="state"
                variant="outline"
                :data-state-button="state"
                @click="setOpen(state, true)"
            >
                {{ labels[state] }}
            </Button>
        </div>

        <CategoryFormDialog
            :open="isOpen('category-new')"
            mode="create"
            :budget-id="1"
            default-type="expense"
            :category-appearance-options="categoryAppearanceOptions"
            @update:open="setOpen('category-new', $event)"
        />
        <CategoryFormDialog
            :open="isOpen('category-edit')"
            mode="edit"
            :budget-id="1"
            default-type="expense"
            :category="category"
            :category-appearance-options="categoryAppearanceOptions"
            @update:open="setOpen('category-edit', $event)"
        />
        <CategoryFormDialog
            :open="isOpen('category-locked')"
            mode="edit"
            :budget-id="1"
            default-type="expense"
            :category="category"
            lock-type
            :category-appearance-options="categoryAppearanceOptions"
            @update:open="setOpen('category-locked', $event)"
        />
        <CategoryFormDialog
            :open="isOpen('category-errors')"
            mode="edit"
            :budget-id="1"
            default-type="expense"
            :category="category"
            :form="validateForm"
            :category-appearance-options="categoryAppearanceOptions"
            @update:open="setOpen('category-errors', $event)"
        />
        <CategoryFormDialog
            :open="isOpen('category-saving')"
            mode="edit"
            :budget-id="1"
            default-type="expense"
            :category="category"
            :form="validateForm"
            :category-appearance-options="categoryAppearanceOptions"
            @update:open="setOpen('category-saving', $event)"
        />
        <CategoryFormDialog
            :open="isOpen('category-bad-amount')"
            mode="edit"
            :budget-id="1"
            default-type="expense"
            :category="category"
            initial-planned-amount-input="12,345"
            :category-appearance-options="categoryAppearanceOptions"
            @update:open="setOpen('category-bad-amount', $event)"
        />
        <CategoryFormDialog
            :open="isOpen('category-month-item-kind')"
            mode="create"
            :budget-id="1"
            default-type="expense"
            :category-appearance-options="categoryAppearanceOptions"
            @update:open="setOpen('category-month-item-kind', $event)"
        />
        <CreateBudgetDialog
            :open="isOpen('budget-create')"
            @update:open="setOpen('budget-create', $event)"
        />
        <CreateBudgetDialog
            :open="isOpen('budget-errors')"
            :form="validateForm"
            @update:open="setOpen('budget-errors', $event)"
        />
        <ConfirmActionDialog
            :open="isOpen('confirm-plain')"
            :form="plainForm"
            title="Rozpocząć realizację budżetu?"
            description="Plan zostanie zamrożony, a operacje będą liczone względem planu."
            confirm-label="Rozpocznij"
            cancel-label="Anuluj"
            @update:open="setOpen('confirm-plain', $event)"
        />
        <ConfirmActionDialog
            :open="isOpen('confirm-destructive')"
            :form="plainForm"
            destructive
            title="Usunąć kategorię?"
            description="Tej operacji nie można cofnąć."
            confirm-label="Usuń"
            cancel-label="Anuluj"
            @update:open="setOpen('confirm-destructive', $event)"
        />
        <ConfirmActionDialog
            :open="isOpen('confirm-saving')"
            :form="savingForm"
            title="Zapis w toku"
            description="Potwierdź, żeby zobaczyć spinner, ukryty X i zablokowane zamykanie (odpowiedź po około 1,5 s kończy się błędem)."
            confirm-label="Zapisz"
            cancel-label="Anuluj"
            @update:open="setOpen('confirm-saving', $event)"
        />
        <ConfirmActionDialog
            :open="isOpen('confirm-deficit')"
            :form="plainForm"
            title="Rozpocząć realizację budżetu?"
            description="Plan zostanie zamrożony, a operacje będą liczone względem planu."
            confirm-label="Rozpocznij"
            cancel-label="Anuluj"
            @update:open="setOpen('confirm-deficit', $event)"
        >
            <Alert variant="warning">
                Planowany deficyt wynosi 1 200,00 zł, a płynność na początek
                roku 400,00 zł. Niepokryty deficyt: 800,00 zł.
            </Alert>
        </ConfirmActionDialog>
        <AccountFormDialog
            :open="isOpen('account')"
            mode="create"
            @update:open="setOpen('account', $event)"
        />
    </div>
</template>
