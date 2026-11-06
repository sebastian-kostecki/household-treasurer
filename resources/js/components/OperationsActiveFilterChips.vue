<script setup lang="ts">
import { X } from '@lucide/vue';
import { computed } from 'vue';
import CategoryChip from '@/components/CategoryChip.vue';
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/composables/useTranslations';
import type { AccountPickerOption } from '@/lib/account-identity';
import { formatDate } from '@/lib/format';

export type OperationsFilterChipKey =
    | 'q'
    | 'year'
    | 'category'
    | 'account'
    | 'termDeposit'
    | 'dates';

export type OperationsListSection =
    | 'financial_operations'
    | 'own_transfers'
    | 'capital_movements';

type ChipFilters = {
    q: string | null;
    year: number | null;
    category_name: string | null;
    category_type: string | null;
    account_id: number | null;
    term_deposit_id: number | null;
    date_from: string | null;
    date_to: string | null;
};

type ChipCategory = {
    name: string;
    type: 'income' | 'expense';
    icon: string;
    color: string;
};

type ChipAccount = AccountPickerOption & { is_archived: boolean };

type ChipTermDeposit = {
    id: number;
    name: string;
};

const props = defineProps<{
    filters: ChipFilters;
    categories: ChipCategory[];
    accounts: ChipAccount[];
    termDeposits: ChipTermDeposit[];
    suppressedSections: OperationsListSection[];
}>();

const emit = defineEmits<{
    remove: [key: OperationsFilterChipKey];
    clear: [];
}>();

const t = useTranslations();

type FilterChip = {
    key: OperationsFilterChipKey;
    label: string;
    removeLabel: string;
    category?: ChipCategory;
};

const selectedCategory = computed(() => {
    if (
        props.filters.category_type === null ||
        props.filters.category_name === null
    ) {
        return null;
    }

    return (
        props.categories.find(
            (category) =>
                category.type === props.filters.category_type &&
                category.name === props.filters.category_name,
        ) ?? null
    );
});

const selectedAccount = computed(() => {
    if (props.filters.account_id === null) {
        return null;
    }

    return (
        props.accounts.find(
            (account) => account.id === props.filters.account_id,
        ) ?? null
    );
});

const selectedTermDeposit = computed(() => {
    if (props.filters.term_deposit_id === null) {
        return null;
    }

    return (
        props.termDeposits.find(
            (termDeposit) => termDeposit.id === props.filters.term_deposit_id,
        ) ?? null
    );
});

const dateRangeLabel = computed(() => {
    const from = props.filters.date_from;
    const to = props.filters.date_to;

    if (from !== null && to !== null) {
        return `${formatDate(from)} – ${formatDate(to)}`;
    }

    if (from !== null) {
        return `${t.operations.filter_date_from} ${formatDate(from)}`;
    }

    if (to !== null) {
        return `${t.operations.filter_date_to} ${formatDate(to)}`;
    }

    return null;
});

function accountLabel(account: ChipAccount): string {
    if (!account.is_archived) {
        return account.name;
    }

    return `${account.name} (${t.accounts.archived_badge})`;
}

function removeAriaLabel(label: string): string {
    return t.operations.filter_remove.replace(':label', label);
}

const chips = computed((): FilterChip[] => {
    const items: FilterChip[] = [];

    if (props.filters.q !== null && props.filters.q !== '') {
        const label = `„${props.filters.q}”`;
        items.push({
            key: 'q',
            label,
            removeLabel: removeAriaLabel(label),
        });
    }

    if (props.filters.year !== null) {
        const label = String(props.filters.year);
        items.push({
            key: 'year',
            label,
            removeLabel: removeAriaLabel(label),
        });
    }

    if (selectedCategory.value !== null) {
        items.push({
            key: 'category',
            label: selectedCategory.value.name,
            removeLabel: removeAriaLabel(selectedCategory.value.name),
            category: selectedCategory.value,
        });
    } else if (
        props.filters.category_name !== null &&
        props.filters.category_type !== null
    ) {
        const label = props.filters.category_name;
        items.push({
            key: 'category',
            label,
            removeLabel: removeAriaLabel(label),
        });
    }

    if (selectedAccount.value !== null) {
        const label = accountLabel(selectedAccount.value);
        items.push({
            key: 'account',
            label,
            removeLabel: removeAriaLabel(label),
        });
    } else if (props.filters.account_id !== null) {
        const label = String(props.filters.account_id);
        items.push({
            key: 'account',
            label,
            removeLabel: removeAriaLabel(label),
        });
    }

    if (selectedTermDeposit.value !== null) {
        const label = selectedTermDeposit.value.name;
        items.push({
            key: 'termDeposit',
            label,
            removeLabel: removeAriaLabel(label),
        });
    } else if (props.filters.term_deposit_id !== null) {
        const label = String(props.filters.term_deposit_id);
        items.push({
            key: 'termDeposit',
            label,
            removeLabel: removeAriaLabel(label),
        });
    }

    if (dateRangeLabel.value !== null) {
        items.push({
            key: 'dates',
            label: dateRangeLabel.value,
            removeLabel: removeAriaLabel(dateRangeLabel.value),
        });
    }

    return items;
});

const sectionLabels: Record<OperationsListSection, string> = {
    financial_operations: t.operations.financial_operations,
    own_transfers: t.operations.own_transfers,
    capital_movements: t.operations.capital_movements,
};

const suppressedNote = computed(() => {
    if (props.suppressedSections.length === 0) {
        return null;
    }

    const sections = props.suppressedSections
        .map((section) => sectionLabels[section])
        .join(', ');

    return t.operations.filter_suppressed_sections.replace(
        ':sections',
        sections,
    );
});
</script>

<template>
    <div v-if="chips.length > 0" class="flex flex-wrap items-center gap-2">
        <span
            v-for="chip in chips"
            :key="chip.key"
            class="bg-background h-control-sm inline-flex max-w-full items-center gap-1.5 rounded-full border pr-1 pl-2.5"
        >
            <CategoryChip
                v-if="chip.category"
                :name="chip.category.name"
                :icon="chip.category.icon"
                :color="chip.category.color"
                size="sm"
            />
            <span v-else class="truncate text-xs font-medium">{{
                chip.label
            }}</span>
            <Button
                type="button"
                variant="ghost"
                size="icon-sm"
                class="shrink-0 rounded-full"
                :aria-label="chip.removeLabel"
                @click="emit('remove', chip.key)"
            >
                <X class="size-3.5" aria-hidden="true" />
            </Button>
        </span>

        <Button type="button" size="sm" variant="ghost" @click="emit('clear')">
            {{ t.operations.filter_clear }}
        </Button>

        <p
            v-if="suppressedNote !== null"
            class="meta text-muted-foreground order-last basis-full"
            aria-live="polite"
        >
            {{ suppressedNote }}
        </p>
    </div>
</template>
