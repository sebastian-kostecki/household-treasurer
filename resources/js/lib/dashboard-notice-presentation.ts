import type { DashboardNotice } from '../types/dashboard';
import type { AccountIdentityFields } from './account-identity';
import { monthElapsedPercent } from './month-pace-calculator';

export type NoticeLabelKey =
    | 'negative_balance'
    | 'month_to_spend_negative'
    | 'over_plan'
    | 'over_limit'
    | 'plan_not_approved'
    | 'pace_ahead';

export type NoticeIdentity =
    | { type: 'name'; name: string }
    | {
          type: 'account';
          name: string;
          avatar: Pick<AccountIdentityFields, 'type' | 'bank' | 'mark_url'>;
      }
    | { type: 'month'; month: number };

export type NoticeValue =
    | { type: 'money'; minorUnits: number }
    | { type: 'pace'; percent: number; daysPercent: number }
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
                value: null,
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
    }
}
