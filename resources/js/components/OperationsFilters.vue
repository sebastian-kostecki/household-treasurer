<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Search } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTranslations } from '@/composables/useTranslations';
import { operations as operationsRoute } from '@/routes';

export type OperationsListFilters = {
    q: string | null;
    year: number | null;
    category_name: string | null;
    category_type: string | null;
    account_id: number | null;
    term_deposit_id: number | null;
    date_from: string | null;
    date_to: string | null;
};

export type OperationsFilterCategory = {
    name: string;
    type: 'income' | 'expense';
    icon: string;
    color: string;
};

export type OperationsFilterAccount = {
    id: number;
    name: string;
    is_archived: boolean;
};

export type OperationsFilterTermDeposit = {
    id: number;
    name: string;
};

const props = defineProps<{
    filters: OperationsListFilters;
    filtersActive: boolean;
    years: number[];
    categories: OperationsFilterCategory[];
    accounts: OperationsFilterAccount[];
    termDeposits: OperationsFilterTermDeposit[];
}>();

const t = useTranslations();
const search = ref(props.filters.q ?? '');
let searchTimer: ReturnType<typeof setTimeout> | null = null;
let skipSearchWatch = false;

const incomeCategories = computed(() =>
    props.categories.filter((category) => category.type === 'income'),
);
const expenseCategories = computed(() =>
    props.categories.filter((category) => category.type === 'expense'),
);

const categoryValue = computed(() => {
    if (props.filters.category_type === null || props.filters.category_name === null) {
        return '';
    }

    return serializeCategory(props.filters.category_type, props.filters.category_name);
});

const thisMonthRange = computed(() => monthRange(0));
const lastMonthRange = computed(() => monthRange(-1));

const thisMonthActive = computed(
    () =>
        props.filters.date_from === thisMonthRange.value.from &&
        props.filters.date_to === thisMonthRange.value.to,
);
const lastMonthActive = computed(
    () =>
        props.filters.date_from === lastMonthRange.value.from &&
        props.filters.date_to === lastMonthRange.value.to,
);

watch(
    () => props.filters.q,
    (value) => {
        search.value = value ?? '';
    },
);

watch(search, (value) => {
    if (skipSearchWatch) {
        skipSearchWatch = false;

        return;
    }

    if (searchTimer !== null) {
        clearTimeout(searchTimer);
    }

    searchTimer = setTimeout(() => {
        const q = value.trim() === '' ? null : value.trim();

        if (q === props.filters.q) {
            return;
        }

        visit({ ...props.filters, q });
    }, 300);
});

function serializeCategory(type: string, name: string): string {
    return `${type}\u001f${name}`;
}

function parseCategory(value: string): { category_name: string | null; category_type: string | null } {
    if (value === '') {
        return { category_name: null, category_type: null };
    }

    const separator = value.indexOf('\u001f');

    if (separator === -1) {
        return { category_name: null, category_type: null };
    }

    return {
        category_type: value.slice(0, separator),
        category_name: value.slice(separator + 1),
    };
}

function monthRange(offset: number): { from: string; to: string } {
    const now = new Date();
    const from = new Date(now.getFullYear(), now.getMonth() + offset, 1);
    const to = new Date(now.getFullYear(), now.getMonth() + offset + 1, 0);

    return { from: toIsoDate(from), to: toIsoDate(to) };
}

function toIsoDate(date: Date): string {
    const year = String(date.getFullYear());
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');

    return `${year}-${month}-${day}`;
}

function visit(next: OperationsListFilters): void {
    router.get(
        operationsRoute({
            query: {
                q: next.q ?? undefined,
                year: next.year ?? undefined,
                category_name: next.category_name ?? undefined,
                category_type: next.category_type ?? undefined,
                account_id: next.account_id ?? undefined,
                term_deposit_id: next.term_deposit_id ?? undefined,
                date_from: next.date_from ?? undefined,
                date_to: next.date_to ?? undefined,
            },
        }),
        {},
        { preserveState: true, preserveScroll: true },
    );
}

function onYearChange(event: Event): void {
    const value = (event.target as HTMLSelectElement).value;
    visit({
        ...props.filters,
        year: value === '' ? null : Number(value),
        q: search.value.trim() === '' ? null : search.value.trim(),
    });
}

function onCategoryChange(event: Event): void {
    visit({
        ...props.filters,
        ...parseCategory((event.target as HTMLSelectElement).value),
        q: search.value.trim() === '' ? null : search.value.trim(),
    });
}

function onAccountChange(event: Event): void {
    const value = (event.target as HTMLSelectElement).value;
    visit({
        ...props.filters,
        account_id: value === '' ? null : Number(value),
        q: search.value.trim() === '' ? null : search.value.trim(),
    });
}

function onTermDepositChange(event: Event): void {
    const value = (event.target as HTMLSelectElement).value;
    visit({
        ...props.filters,
        term_deposit_id: value === '' ? null : Number(value),
        q: search.value.trim() === '' ? null : search.value.trim(),
    });
}

function onDateFromChange(value: string | number): void {
    visit({
        ...props.filters,
        date_from: String(value) === '' ? null : String(value),
        q: search.value.trim() === '' ? null : search.value.trim(),
    });
}

function onDateToChange(value: string | number): void {
    visit({
        ...props.filters,
        date_to: String(value) === '' ? null : String(value),
        q: search.value.trim() === '' ? null : search.value.trim(),
    });
}

function applyMonthPreset(offset: number): void {
    const range = monthRange(offset);
    visit({
        ...props.filters,
        date_from: range.from,
        date_to: range.to,
        q: search.value.trim() === '' ? null : search.value.trim(),
    });
}

function clearFilters(): void {
    if (searchTimer !== null) {
        clearTimeout(searchTimer);
        searchTimer = null;
    }

    skipSearchWatch = true;
    search.value = '';
    visit({
        q: null,
        year: null,
        category_name: null,
        category_type: null,
        account_id: null,
        term_deposit_id: null,
        date_from: null,
        date_to: null,
    });
}

function accountLabel(account: OperationsFilterAccount): string {
    if (!account.is_archived) {
        return account.name;
    }

    return `${account.name} (${t.accounts.archived_badge})`;
}
</script>

<template>
    <div
        class="flex flex-col gap-3 rounded-2xl border border-border/60 bg-card p-4"
    >
        <div class="relative">
            <Search
                class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
            />
            <Input
                v-model="search"
                type="search"
                class="pl-9"
                :placeholder="t.operations.search_placeholder"
                :aria-label="t.operations.search_placeholder"
            />
        </div>

        <div class="flex flex-wrap items-end gap-2">
            <div class="grid min-w-32 gap-1">
                <Label for="operations-filter-year" class="text-muted-foreground text-xs">
                    {{ t.operations.filter_year }}
                </Label>
                <select
                    id="operations-filter-year"
                    class="native-select"
                    :value="filters.year ?? ''"
                    @change="onYearChange"
                >
                    <option value="">
                        {{ t.operations.filter_year_all }}
                    </option>
                    <option v-for="year in years" :key="year" :value="year">
                        {{ year }}
                    </option>
                </select>
            </div>

            <div class="grid min-w-44 gap-1">
                <Label for="operations-filter-category" class="text-muted-foreground text-xs">
                    {{ t.operations.filter_category }}
                </Label>
                <select
                    id="operations-filter-category"
                    class="native-select"
                    :value="categoryValue"
                    @change="onCategoryChange"
                >
                    <option value="">
                        {{ t.operations.filter_category_all }}
                    </option>
                    <optgroup
                        v-if="incomeCategories.length > 0"
                        :label="t.operations.type_income"
                    >
                        <option
                            v-for="category in incomeCategories"
                            :key="serializeCategory(category.type, category.name)"
                            :value="serializeCategory(category.type, category.name)"
                        >
                            {{ category.name }}
                        </option>
                    </optgroup>
                    <optgroup
                        v-if="expenseCategories.length > 0"
                        :label="t.operations.type_expense"
                    >
                        <option
                            v-for="category in expenseCategories"
                            :key="serializeCategory(category.type, category.name)"
                            :value="serializeCategory(category.type, category.name)"
                        >
                            {{ category.name }}
                        </option>
                    </optgroup>
                </select>
            </div>

            <div class="grid min-w-40 gap-1">
                <Label for="operations-filter-account" class="text-muted-foreground text-xs">
                    {{ t.operations.filter_account }}
                </Label>
                <select
                    id="operations-filter-account"
                    class="native-select"
                    :value="filters.account_id ?? ''"
                    @change="onAccountChange"
                >
                    <option value="">
                        {{ t.operations.filter_account_all }}
                    </option>
                    <option
                        v-for="account in accounts"
                        :key="account.id"
                        :value="account.id"
                    >
                        {{ accountLabel(account) }}
                    </option>
                </select>
            </div>

            <div class="grid min-w-40 gap-1">
                <Label for="operations-filter-term-deposit" class="text-muted-foreground text-xs">
                    {{ t.operations.filter_term_deposit }}
                </Label>
                <select
                    id="operations-filter-term-deposit"
                    class="native-select"
                    :value="filters.term_deposit_id ?? ''"
                    @change="onTermDepositChange"
                >
                    <option value="">
                        {{ t.operations.filter_term_deposit_all }}
                    </option>
                    <option
                        v-for="termDeposit in termDeposits"
                        :key="termDeposit.id"
                        :value="termDeposit.id"
                    >
                        {{ termDeposit.name }}
                    </option>
                </select>
            </div>

            <div class="grid min-w-36 gap-1">
                <Label for="operations-filter-date-from" class="text-muted-foreground text-xs">
                    {{ t.operations.filter_date_from }}
                </Label>
                <Input
                    id="operations-filter-date-from"
                    type="date"
                    :model-value="filters.date_from ?? ''"
                    @update:model-value="onDateFromChange"
                />
            </div>

            <div class="grid min-w-36 gap-1">
                <Label for="operations-filter-date-to" class="text-muted-foreground text-xs">
                    {{ t.operations.filter_date_to }}
                </Label>
                <Input
                    id="operations-filter-date-to"
                    type="date"
                    :model-value="filters.date_to ?? ''"
                    @update:model-value="onDateToChange"
                />
            </div>

            <Button
                type="button"
                size="sm"
                :variant="thisMonthActive ? 'secondary' : 'outline'"
                @click="applyMonthPreset(0)"
            >
                {{ t.operations.filter_this_month }}
            </Button>
            <Button
                type="button"
                size="sm"
                :variant="lastMonthActive ? 'secondary' : 'outline'"
                @click="applyMonthPreset(-1)"
            >
                {{ t.operations.filter_last_month }}
            </Button>
            <Button
                v-if="filtersActive || search.trim() !== ''"
                type="button"
                size="sm"
                variant="ghost"
                @click="clearFilters"
            >
                {{ t.operations.filter_clear }}
            </Button>
        </div>
    </div>
</template>
