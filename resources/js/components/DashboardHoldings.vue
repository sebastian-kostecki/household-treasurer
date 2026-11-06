<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowRight } from '@lucide/vue';
import { computed } from 'vue';
import AccountIdentityBlock from '@/components/AccountIdentityBlock.vue';
import IndexEmptyState from '@/components/IndexEmptyState.vue';
import ListSurface from '@/components/ListSurface.vue';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { useTranslations } from '@/composables/useTranslations';
import type { AccountBank } from '@/lib/account-identity';
import { amountToneClass } from '@/lib/amount-tone';
import { formatDate, formatMoneyAmount } from '@/lib/format';
import { index as accountsIndex } from '@/routes/accounts';
import { index as termDepositsIndex } from '@/routes/term-deposits';
import type { AccountSummary, TermDepositSummary } from '@/types/dashboard';

const props = defineProps<{
    accounts: AccountSummary[];
    termDeposits: TermDepositSummary[];
}>();

const t = useTranslations();

function accountCaption(account: AccountSummary): string {
    if (account.type === 'cash') {
        return t.dashboard.holdings_cash_caption;
    }

    return account.bank === null
        ? t.accounts.type_bank
        : bankLabel(account.bank);
}

function termDepositCaption(termDeposit: TermDepositSummary): string {
    const bank = bankLabel(termDeposit.bank);

    return termDeposit.end_date === null
        ? bank
        : t.dashboard.holdings_deposit_caption
              .replace(':bank', bank)
              .replace(':date', formatDate(termDeposit.end_date));
}

function bankLabel(bank: AccountBank): string {
    return t.accounts[`bank_${bank}` as keyof typeof t.accounts] ?? bank;
}

const showEmptyState = computed(
    () => props.accounts.length === 0 && props.termDeposits.length === 0,
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
</script>

<template>
    <div class="flex flex-col gap-3">
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
            <ListSurface flush>
                <template #header>
                    <div class="flex items-center justify-between gap-2">
                        <h2 class="section-title">{{ t.nav.accounts }}</h2>
                        <Link
                            :href="accountsIndex()"
                            class="dashboard-holdings-link"
                        >
                            {{ t.dashboard.holdings_all }}
                            <ArrowRight class="size-3" aria-hidden="true" />
                        </Link>
                    </div>
                </template>

                <p
                    v-if="accounts.length === 0"
                    class="meta text-muted-foreground px-4 py-3"
                >
                    {{ t.dashboard.no_accounts }}
                </p>

                <template v-else>
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
                            size="sm"
                        >
                            <p class="text-sm font-medium">
                                {{ account.name }}
                            </p>
                            <p class="meta text-muted-foreground">
                                {{ accountCaption(account) }}
                            </p>
                        </AccountIdentityBlock>
                        <p
                            class="amount shrink-0"
                            :class="
                                amountToneClass(
                                    account.current_balance_minor_units,
                                )
                            "
                        >
                            {{
                                formatMoneyAmount(
                                    account.current_balance_minor_units,
                                )
                            }}
                        </p>
                    </div>
                </template>

                <template v-if="accounts.length > 0" #footer>
                    <div class="dashboard-holdings-footer">
                        <div class="dashboard-holdings-footer-total">
                            <span class="dashboard-holdings-footer-label">
                                {{ t.accounts.liquidity_total }}
                            </span>
                            <span
                                class="amount-sum"
                                :class="
                                    amountToneClass(accountsTotalMinorUnits)
                                "
                            >
                                {{ formatMoneyAmount(accountsTotalMinorUnits) }}
                            </span>
                        </div>
                    </div>
                </template>
            </ListSurface>

            <ListSurface v-if="termDeposits.length > 0" flush>
                <template #header>
                    <div class="flex items-center justify-between gap-2">
                        <h2 class="section-title">
                            {{ t.nav.term_deposits }}
                        </h2>
                        <Link
                            :href="termDepositsIndex()"
                            class="dashboard-holdings-link"
                        >
                            {{ t.dashboard.holdings_all }}
                            <ArrowRight class="size-3" aria-hidden="true" />
                        </Link>
                    </div>
                </template>

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
                        size="sm"
                    >
                        <p class="text-sm font-medium">
                            {{ termDeposit.name }}
                        </p>
                        <p class="meta text-muted-foreground">
                            {{ termDepositCaption(termDeposit) }}
                        </p>
                    </AccountIdentityBlock>
                    <p
                        class="amount shrink-0"
                        :class="
                            amountToneClass(
                                termDeposit.current_balance_minor_units,
                            )
                        "
                    >
                        {{
                            formatMoneyAmount(
                                termDeposit.current_balance_minor_units,
                            )
                        }}
                    </p>
                </div>

                <template #footer>
                    <div class="dashboard-holdings-footer">
                        <div class="dashboard-holdings-footer-total">
                            <span class="dashboard-holdings-footer-label">
                                {{ t.term_deposits.total }}
                            </span>
                            <span
                                class="amount-sum"
                                :class="
                                    amountToneClass(termDepositsTotalMinorUnits)
                                "
                            >
                                {{
                                    formatMoneyAmount(
                                        termDepositsTotalMinorUnits,
                                    )
                                }}
                            </span>
                        </div>
                    </div>
                </template>
            </ListSurface>
        </template>
    </div>
</template>
