<script setup lang="ts">
import { ChevronDown } from '@lucide/vue';
import { computed, onMounted, ref, watch } from 'vue';
import AccountIdentityBlock from '@/components/AccountIdentityBlock.vue';
import ListSurface from '@/components/ListSurface.vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
} from '@/components/ui/card';
import { useTranslations } from '@/composables/useTranslations';
import type { AccountBank } from '@/lib/account-identity';
import { formatDate, formatMoney } from '@/lib/format';

const HOLDINGS_EXPANDED_STORAGE_KEY = 'dashboard-holdings-expanded';

type AccountSummary = {
    id: number;
    name: string;
    type: 'bank' | 'cash';
    bank: AccountBank | null;
    mark_url: string | null;
    current_balance_minor_units: number;
    is_negative: boolean;
};

type TermDepositSummary = {
    id: number;
    name: string;
    bank: AccountBank;
    mark_url: string | null;
    end_date: string | null;
    current_balance_minor_units: number;
    is_negative: boolean;
};

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

function amountClass(
    holding: AccountSummary | TermDepositSummary,
): string {
    if (holding.is_negative) {
        return 'text-destructive';
    }

    if (holding.current_balance_minor_units > 0) {
        return 'text-success';
    }

    return '';
}

function groupTotalClass(totalMinorUnits: number): string {
    if (totalMinorUnits < 0) {
        return 'text-destructive';
    }

    if (totalMinorUnits > 0) {
        return 'text-success';
    }

    return '';
}

function toggleExpanded(): void {
    isExpanded.value = !isExpanded.value;
}
</script>

<template>
    <div class="flex flex-col gap-3">
        <h2 class="hidden text-sm font-semibold lg:block">
            {{ t.dashboard.holdings_title }}
        </h2>
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

        <div :class="holdingsOpenOnMobile ? '' : 'max-lg:hidden'">
            <Card v-if="showEmptyState">
                <CardHeader class="pb-2">
                    <CardDescription>
                        {{ t.dashboard.no_accounts }}
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <p class="text-muted-foreground text-sm">
                        {{ t.accounts.empty }}
                    </p>
                </CardContent>
            </Card>

            <ListSurface v-else>
                <div class="border-border/60 border-b last:border-b-0">
                    <div
                        class="text-muted-foreground flex items-center justify-between gap-3 py-2 text-xs font-medium"
                    >
                        <span>{{ t.nav.accounts }}</span>
                        <span
                            v-if="accounts.length > 0"
                            class="tabular-nums"
                            :class="
                                groupTotalClass(accountsTotalMinorUnits)
                            "
                        >
                            {{ formatMoney(accountsTotalMinorUnits) }}
                        </span>
                    </div>
                    <p
                        v-if="accounts.length === 0"
                        class="text-muted-foreground border-border/60 border-t py-3 text-sm"
                    >
                        {{ t.dashboard.no_accounts }}
                    </p>
                    <div
                        v-for="account in accounts"
                        :key="account.id"
                        class="border-border/60 hover:bg-foreground/[0.03] flex items-center justify-between gap-x-4 gap-y-2 border-t py-3 transition-colors"
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
                            :class="amountClass(account)"
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
                    v-if="termDeposits.length > 0"
                    class="border-border/60 border-b last:border-b-0"
                >
                    <div
                        class="text-muted-foreground border-border/60 flex items-center justify-between gap-3 border-t py-2 text-xs font-medium"
                    >
                        <span>{{ t.nav.term_deposits }}</span>
                        <span
                            class="tabular-nums"
                            :class="
                                groupTotalClass(termDepositsTotalMinorUnits)
                            "
                        >
                            {{ formatMoney(termDepositsTotalMinorUnits) }}
                        </span>
                    </div>
                    <div
                        v-for="termDeposit in termDeposits"
                        :key="termDeposit.id"
                        class="border-border/60 hover:bg-foreground/[0.03] flex items-center justify-between gap-x-4 gap-y-2 border-t py-3 transition-colors"
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
                            :class="amountClass(termDeposit)"
                        >
                            {{
                                formatMoney(
                                    termDeposit.current_balance_minor_units,
                                )
                            }}
                        </p>
                    </div>
                </div>
            </ListSurface>
        </div>
    </div>
</template>
