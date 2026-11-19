import type { DashboardNotice } from '../types/dashboard';
import type { AccountIdentityFields } from './account-identity';
import { monthElapsedPercent } from './month-pace-calculator';

export type NoticeLabelKey =
    | 'negative_balance'
    | 'month_to_spend_negative'
    | 'over_plan'
    | 'over_limit'
    | 'plan_not_approved'
    | 'pace_ahead'
    | 'collected_not_covered'
    | 'budget_draft'
    | 'budget_missing';

export type NoticeIdentity =
    | { type: 'name'; name: string }
    | {
          type: 'account';
          name: string;
          avatar: Pick<AccountIdentityFields, 'type' | 'bank' | 'mark_url'>;
      }
    | { type: 'month'; month: number }
    | { type: 'year'; year: number };

export type NoticeValue =
    | { type: 'money'; minorUnits: number }
    | { type: 'pace'; percent: number; daysPercent: number }
    | { type: 'approve_by'; date: string }
    | { type: 'import_refused'; year: number }
    | {
          type: 'collected_shortfall';
          shortfallMinorUnits: number;
          collectedMinorUnits: number;
      }
    | null;

export type NoticePresentation = {
    labelKey: NoticeLabelKey;
    identity: NoticeIdentity;
    value: NoticeValue;
};

export function noticePresentation(
    notice: DashboardNotice,
): NoticePresentation {
    switch (notice.kind) {
        case 'negative_account':
            return {
                labelKey: 'negative_balance',
                identity: {
                    type: 'account',
                    name: notice.name,
                    avatar: {
                        type: notice.type,
                        bank: notice.bank,
                        mark_url: notice.mark_url,
                    },
                },
                value: { type: 'money', minorUnits: notice.amount_minor_units },
            };
        case 'negative_term_deposit':
            return {
                labelKey: 'negative_balance',
                identity: {
                    type: 'account',
                    name: notice.name,
                    avatar: {
                        type: 'bank',
                        bank: notice.bank,
                        mark_url: notice.mark_url,
                    },
                },
                value: { type: 'money', minorUnits: notice.amount_minor_units },
            };
        case 'month_to_spend_negative':
            return {
                labelKey: 'month_to_spend_negative',
                identity: { type: 'month', month: notice.month },
                value: { type: 'money', minorUnits: notice.amount_minor_units },
            };
        case 'month_item_over_plan':
            return {
                labelKey: 'over_plan',
                identity: { type: 'name', name: notice.name },
                value: { type: 'money', minorUnits: notice.amount_minor_units },
            };
        case 'even_annual_over_limit':
            return {
                labelKey: 'over_limit',
                identity: { type: 'name', name: notice.name },
                value: { type: 'money', minorUnits: notice.amount_minor_units },
            };
        case 'month_plan_not_approved':
            return {
                labelKey: 'plan_not_approved',
                identity: { type: 'month', month: notice.month },
                value: { type: 'approve_by', date: notice.approve_by },
            };
        case 'month_pace_ahead':
            return {
                labelKey: 'pace_ahead',
                identity: { type: 'month', month: notice.month },
                value: {
                    type: 'pace',
                    percent: notice.percent,
                    daysPercent: monthElapsedPercent(
                        notice.elapsed_day,
                        notice.days_in_month,
                    ),
                },
            };
        case 'collected_not_covered':
            return {
                labelKey: 'collected_not_covered',
                identity: { type: 'month', month: notice.month },
                value: {
                    type: 'collected_shortfall',
                    shortfallMinorUnits: notice.shortfall_minor_units,
                    collectedMinorUnits: notice.collected_minor_units,
                },
            };
        case 'budget_not_in_execution':
            return {
                labelKey:
                    notice.state === 'draft'
                        ? 'budget_draft'
                        : 'budget_missing',
                identity: { type: 'year', year: notice.year },
                value: { type: 'import_refused', year: notice.year },
            };
    }
}
