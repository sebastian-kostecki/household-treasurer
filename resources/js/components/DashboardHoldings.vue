<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ChevronDown } from '@lucide/vue';
import { computed, onMounted, ref, watch } from 'vue';
import AccountIdentityBlock from '@/components/AccountIdentityBlock.vue';
import IndexEmptyState from '@/components/IndexEmptyState.vue';
import ListSurface from '@/components/ListSurface.vue';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { useTranslations } from '@/composables/useTranslations';
import { amountToneClass } from '@/lib/amount-tone';
import { formatDate, formatMoney } from '@/lib/format';
import { index as accountsIndex } from '@/routes/accounts';
import type { AccountSummary, TermDepositSummary } from '@/types/dashboard';

const HOLDINGS_EXPANDED_STORAGE_KEY = 'dashboard-holdings-expanded';

const props = defineProps<{
    accounts: AccountSummary[];
    termDeposits: TermDepositSummary[];
}>();

const t = useTranslations();
const isExpanded = ref(false);

onMounted(() => {
    isExpanded.value =
        window.localStorage.getItem(HOLDINGS_EXPANDED_STORAGE_KEY) === '1';
});

watch(isExpanded, (expanded) => {
    if (window.matchMedia('(max-width: 1023.98px)').matches) {
        window.localStorage.setItem(
            HOLDINGS_EXPANDED_STORAGE_KEY,
            expanded ? '1' : '0',
        );
    }
});

const holdingsCount = computed(
    () => props.accounts.length + props.termDeposits.length,
);

const showEmptyState = computed(
    () => props.accounts.length === 0 && props.termDeposits.length === 0,
);

const holdingsOpenOnMobile = computed(
    () => isExpanded.value || showEmptyState.value,
);

const accountsTotalMinorUnits = computed(() =>
    props.accounts.reduce(
        (sum, account) => sum + account.current_balance_minor_units,
        0,
    ),
);

const termDepositsTotalMinorUnits = computed(() =>
    props.termDeposits.reduce(
        (sum, termDeposit) => sum + termDeposit.current_balance_minor_units,
        0,
    ),
);

function toggleExpanded(): void {
    isExpanded.value = !isExpanded.value;
}
</script>

<template>
    <div class="flex flex-col gap-3">
        <div class="hidden items-center justify-between gap-2 lg:flex">
            <h2 class="text-sm font-semibold">
                {{ t.dashboard.holdings_title }}
            </h2>
            <Button variant="outline" size="sm" as-child>
                <Link :href="accountsIndex()">
                    {{ t.dashboard.open_accounts }}
                </Link>
            </Button>
        </div>
        <Button
            type="button"
            variant="ghost"
            class="h-auto w-full justify-between gap-2 px-0 py-1 text-sm font-semibold hover:bg-transparent lg:hidden"
            :aria-expanded="holdingsOpenOnMobile"
            @click="toggleExpanded"
        >
            <span class="flex items-center gap-2">
                {{ t.dashboard.holdings_title }}
                <span class="text-muted-foreground font-normal">
                    ({{ holdingsCount }})
                </span>
            </span>
            <ChevronDown
                class="size-4 shrink-0 opacity-60 transition-transform"
                :class="holdingsOpenOnMobile ? 'rotate-180' : ''"
            />
        </Button>

        <div
            class="space-y-3"
            :class="holdingsOpenOnMobile ? '' : 'max-lg:hidden'"
        >
            <Card v-if="showEmptyState">
                <IndexEmptyState
                    :title="t.dashboard.no_accounts"
                    :description="t.accounts.empty"
                >
                    <template #actions>
                        <Button as-child>
                            <Link :href="accountsIndex()">
                                {{ t.dashboard.open_accounts }}
                            </Link>
                        </Button>
                    </template>
                </IndexEmptyState>
            </Card>

            <template v-else>
                <ListSurface :title="t.nav.accounts">
                    <p
                        v-if="accounts.length === 0"
                        class="text-muted-foreground text-sm"
                    >
                        {{ t.dashboard.no_accounts }}
                    </p>

                    <template v-else>
                        <div
                            class="table-header flex items-center justify-between gap-3 pb-2"
                        >
                            <span>{{ t.accounts.name }}</span>
                            <span>{{ t.accounts.current_balance }}</span>
                        </div>

                        <div class="divide-border/60 divide-y">
                            <div
                                v-for="account in accounts"
                                :key="account.id"
                                class="dashboard-holdings-row"
                            >
                                <AccountIdentityBlock
                                    class="min-w-0 flex-1"
                                    :name="account.name"
                                    :type="account.type"
                                    :bank="account.bank"
                                    :mark_url="account.mark_url"
                                >
                                    <p class="text-sm font-medium">
                                        {{ account.name }}
                                    </p>
                                </AccountIdentityBlock>
                                <p
                                    class="shrink-0 text-sm font-semibold tabular-nums"
                                    :class="
                                        amountToneClass(
                                            account.current_balance_minor_units,
                                        )
                                    "
                                >
                                    {{
                                        formatMoney(
                                            account.current_balance_minor_units,
                                        )
                                    }}
                                </p>
                            </div>
                        </div>

                        <div
                            class="border-border/60 flex items-center justify-between border-t pt-3 text-sm font-semibold"
                        >
                            <span>{{ t.accounts.liquidity_total }}</span>
                            <span
                                class="tabular-nums"
                                :class="
                                    amountToneClass(accountsTotalMinorUnits)
                                "
                            >
                                {{ formatMoney(accountsTotalMinorUnits) }}
                            </span>
                        </div>
                    </template>
                </ListSurface>

                <ListSurface
                    v-if="termDeposits.length > 0"
                    :title="t.nav.term_deposits"
                >
                    <div
                        class="table-header flex items-center justify-between gap-3 pb-2"
                    >
                        <span>{{ t.term_deposits.name }}</span>
                        <span>{{ t.term_deposits.current_balance }}</span>
                    </div>

                    <div class="divide-border/60 divide-y">
                        <div
                            v-for="termDeposit in termDeposits"
                            :key="termDeposit.id"
                            class="dashboard-holdings-row"
                        >
                            <AccountIdentityBlock
                                class="min-w-0 flex-1"
                                :name="termDeposit.name"
                                type="bank"
                                :bank="termDeposit.bank"
                                :mark_url="termDeposit.mark_url"
                            >
                                <p class="text-sm font-medium">
                                    {{ termDeposit.name }}
                                </p>
                                <p
                                    v-if="termDeposit.end_date"
                                    class="text-muted-foreground text-xs"
                                >
                                    {{ t.term_deposits.end_date }}:
                                    {{ formatDate(termDeposit.end_date) }}
                                </p>
                            </AccountIdentityBlock>
                            <p
                                class="shrink-0 text-sm font-semibold tabular-nums"
                                :class="
                                    amountToneClass(
                                        termDeposit.current_balance_minor_units,
                                    )
                                "
                            >
                                {{
                                    formatMoney(
                                        termDeposit.current_balance_minor_units,
                                    )
                                }}
                            </p>
                        </div>
                    </div>

                    <div
                        class="border-border/60 flex items-center justify-between border-t pt-3 text-sm font-semibold"
                    >
                        <span>{{ t.term_deposits.total }}</span>
                        <span
                            class="tabular-nums"
                            :class="
                                amountToneClass(termDepositsTotalMinorUnits)
                            "
                        >
                            {{ formatMoney(termDepositsTotalMinorUnits) }}
                        </span>
                    </div>
                </ListSurface>
            </template>
        </div>
    </div>
</template>
