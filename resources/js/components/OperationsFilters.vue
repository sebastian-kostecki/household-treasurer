<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { ChevronDown, Search, SlidersHorizontal } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import OperationsActiveFilterChips, {
    type OperationsFilterChipKey,
    type OperationsListSection,
} from '@/components/OperationsActiveFilterChips.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useTranslations } from '@/composables/useTranslations';
import { operationsListQuery } from '@/lib/operations-list-query';
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
    suppressedSections: OperationsListSection[];
    section: OperationsListSection;
}>();

const SEARCH_DEBOUNCE_MS = 500;

const t = useTranslations();
const search = ref(props.filters.q ?? '');
const visiting = ref(false);
let searchTimer: ReturnType<typeof setTimeout> | null = null;
let skipSearchWatch = false;

const hasPanelFilters = computed(
    () =>
        props.filters.year !== null ||
        props.filters.category_name !== null ||
        props.filters.account_id !== null ||
        props.filters.term_deposit_id !== null ||
        props.filters.date_from !== null ||
        props.filters.date_to !== null,
);

const panelOpen = ref(hasPanelFilters.value);

const incomeCategories = computed(() =>
    props.categories.filter((category) => category.type === 'income'),
);
const expenseCategories = computed(() =>
    props.categories.filter((category) => category.type === 'expense'),
);

const categoryValue = computed(() => {
    if (
        props.filters.category_type === null ||
        props.filters.category_name === null
    ) {
        return '';
    }

    return serializeCategory(
        props.filters.category_type,
        props.filters.category_name,
    );
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

const panelFilterCount = computed(() => {
    let count = 0;

    if (props.filters.year !== null) {
        count += 1;
    }

    if (props.filters.category_name !== null) {
        count += 1;
    }

    if (props.filters.account_id !== null) {
        count += 1;
    }

    if (props.filters.term_deposit_id !== null) {
        count += 1;
    }

    if (props.filters.date_from !== null || props.filters.date_to !== null) {
        count += 1;
    }

    return count;
});

const filtersToggleAria = computed(() => {
    if (panelFilterCount.value === 0) {
        return t.operations.filters_toggle;
    }

    return t.operations.filters_active_aria.replace(
        ':count',
        String(panelFilterCount.value),
    );
});

const showChips = computed(() => props.filtersActive);

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

        applyFilters({ q });
    }, SEARCH_DEBOUNCE_MS);
});

function serializeCategory(type: string, name: string): string {
    return `${type}\u001f${name}`;
}

function parseCategory(value: string): {
    category_name: string | null;
    category_type: string | null;
} {
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

function currentSearch(): string | null {
    const trimmed = search.value.trim();

    return trimmed === '' ? null : trimmed;
}

function visit(next: OperationsListFilters): void {
    router.get(
        operationsRoute({ query: operationsListQuery(next, props.section) }),
        {},
        {
            preserveState: true,
            preserveScroll: true,
            onStart: () => {
                visiting.value = true;
            },
            onFinish: () => {
                visiting.value = false;
            },
        },
    );
}

function applyFilters(patch: Partial<OperationsListFilters>): void {
    visit({
        ...props.filters,
        q: currentSearch(),
        ...patch,
    });
}

function onYearChange(event: Event): void {
    const value = (event.target as HTMLSelectElement).value;
    applyFilters({ year: value === '' ? null : Number(value) });
}

function onCategoryChange(event: Event): void {
    applyFilters(parseCategory((event.target as HTMLSelectElement).value));
}

function onAccountChange(event: Event): void {
    const value = (event.target as HTMLSelectElement).value;
    applyFilters({ account_id: value === '' ? null : Number(value) });
}

function onTermDepositChange(event: Event): void {
    const value = (event.target as HTMLSelectElement).value;
    applyFilters({ term_deposit_id: value === '' ? null : Number(value) });
}

function onDateFromChange(value: string | number): void {
    applyFilters({
        date_from: String(value) === '' ? null : String(value),
    });
}

function onDateToChange(value: string | number): void {
    applyFilters({
        date_to: String(value) === '' ? null : String(value),
    });
}

function applyMonthPreset(offset: number): void {
    const range = monthRange(offset);
    const isActive =
        offset === 0 ? thisMonthActive.value : lastMonthActive.value;

    if (isActive) {
        applyFilters({ date_from: null, date_to: null });

        return;
    }

    applyFilters({
        date_from: range.from,
        date_to: range.to,
    });
}

function submitSearch(): void {
    if (searchTimer !== null) {
        clearTimeout(searchTimer);
        searchTimer = null;
    }

    const q = currentSearch();

    if (q === props.filters.q) {
        return;
    }

    applyFilters({ q });
}

function clearSearchWatchAndTimer(): void {
    if (searchTimer !== null) {
        clearTimeout(searchTimer);
        searchTimer = null;
    }

    skipSearchWatch = true;
    search.value = '';
}

function clearFilters(): void {
    clearSearchWatchAndTimer();
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

function removeFilter(key: OperationsFilterChipKey): void {
    switch (key) {
        case 'q':
            clearSearchWatchAndTimer();
            applyFilters({ q: null });
            break;
        case 'year':
            applyFilters({ year: null });
            break;
        case 'category':
            applyFilters({ category_name: null, category_type: null });
            break;
        case 'account':
            applyFilters({ account_id: null });
            break;
        case 'termDeposit':
            applyFilters({ term_deposit_id: null });
            break;
        case 'dates':
            applyFilters({ date_from: null, date_to: null });
            break;
    }
}

function accountLabel(account: OperationsFilterAccount): string {
    if (!account.is_archived) {
        return account.name;
    }

    return `${account.name} (${t.accounts.archived_badge})`;
}
</script>

<template>
    <Collapsible
        v-model:open="panelOpen"
        class="flex flex-col gap-3"
        :aria-busy="visiting"
    >
        <div
            class="flex flex-wrap items-center gap-2"
            role="search"
            :aria-label="t.operations.filter_search"
        >
            <div class="relative min-w-56 flex-1">
                <Search
                    class="text-muted-foreground pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2"
                    aria-hidden="true"
                />
                <Input
                    id="operations-search"
                    v-model="search"
                    type="search"
                    class="pr-9 pl-9"
                    :placeholder="t.operations.search_placeholder"
                    :aria-label="t.operations.search_placeholder"
                    @keydown.enter.prevent="submitSearch"
                />
                <Spinner
                    v-if="visiting"
                    class="text-muted-foreground absolute top-1/2 right-3 size-4 -translate-y-1/2"
                />
            </div>

            <div class="ml-auto flex flex-wrap items-center justify-end gap-2">
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

                <CollapsibleTrigger as-child>
                    <Button
                        type="button"
                        size="sm"
                        variant="outline"
                        :aria-label="filtersToggleAria"
                    >
                        <SlidersHorizontal class="size-4" aria-hidden="true" />
                        {{ t.operations.filters_toggle }}
                        <Badge v-if="panelFilterCount > 0" variant="secondary">
                            {{ panelFilterCount }}
                        </Badge>
                        <ChevronDown
                            class="size-4 transition-transform"
                            :class="panelOpen ? 'rotate-180' : ''"
                            aria-hidden="true"
                        />
                    </Button>
                </CollapsibleTrigger>
            </div>
        </div>

        <OperationsActiveFilterChips
            v-if="showChips"
            :filters="filters"
            :categories="categories"
            :accounts="accounts"
            :term-deposits="termDeposits"
            :suppressed-sections="suppressedSections"
            @remove="removeFilter"
            @clear="clearFilters"
        />

        <CollapsibleContent
            class="data-[state=open]:border-t data-[state=open]:pt-3"
        >
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <div v-if="years.length > 0" class="grid gap-1">
                    <Label
                        for="operations-filter-year"
                        class="text-muted-foreground text-xs"
                    >
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

                <div v-if="categories.length > 0" class="grid gap-1">
                    <Label
                        for="operations-filter-category"
                        class="text-muted-foreground text-xs"
                    >
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
                                :key="
                                    serializeCategory(
                                        category.type,
                                        category.name,
                                    )
                                "
                                :value="
                                    serializeCategory(
                                        category.type,
                                        category.name,
                                    )
                                "
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
                                :key="
                                    serializeCategory(
                                        category.type,
                                        category.name,
                                    )
                                "
                                :value="
                                    serializeCategory(
                                        category.type,
                                        category.name,
                                    )
                                "
                            >
                                {{ category.name }}
                            </option>
                        </optgroup>
                    </select>
                </div>

                <div v-if="accounts.length > 0" class="grid gap-1">
                    <Label
                        for="operations-filter-account"
                        class="text-muted-foreground text-xs"
                    >
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

                <div v-if="termDeposits.length > 0" class="grid gap-1">
                    <Label
                        for="operations-filter-term-deposit"
                        class="text-muted-foreground text-xs"
                    >
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

                <div class="grid gap-1 sm:col-span-2 lg:col-span-1">
                    <Label class="text-muted-foreground text-xs">
                        {{ t.operations.filter_date_range }}
                    </Label>
                    <div class="flex flex-wrap items-center gap-2">
                        <Input
                            id="operations-filter-date-from"
                            type="date"
                            class="min-w-36 flex-1"
                            :model-value="filters.date_from ?? ''"
                            :aria-label="t.operations.filter_date_from"
                            @update:model-value="onDateFromChange"
                        />
                        <Input
                            id="operations-filter-date-to"
                            type="date"
                            class="min-w-36 flex-1"
                            :model-value="filters.date_to ?? ''"
                            :aria-label="t.operations.filter_date_to"
                            @update:model-value="onDateToChange"
                        />
                    </div>
                </div>
            </div>
        </CollapsibleContent>
    </Collapsible>
</template>
