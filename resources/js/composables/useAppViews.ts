import {
    ArrowLeftRight,
    CalendarDays,
    Landmark,
    LayoutGrid,
    PiggyBank,
    Tags,
    Wallet,
} from '@lucide/vue';
import { useTranslations } from '@/composables/useTranslations';
import { budget, dashboard, monthPlan, operations } from '@/routes';
import { index as accounts } from '@/routes/accounts';
import { index as categories } from '@/routes/categories';
import { index as termDeposits } from '@/routes/term-deposits';
import type { NavItem } from '@/types';

/**
 * The one list of views, in menu order. The sidebar, the global shortcuts
 * and the command palette read it; the shortcut number is the position.
 */
export function useAppViews(): NavItem[] {
    const t = useTranslations();

    const views: Omit<NavItem, 'shortcut'>[] = [
        { title: t.nav.dashboard, href: dashboard(), icon: LayoutGrid },
        { title: t.nav.budget, href: budget(), icon: PiggyBank },
        { title: t.nav.categories, href: categories(), icon: Tags },
        { title: t.nav.month_plan, href: monthPlan(), icon: CalendarDays },
        { title: t.nav.accounts, href: accounts(), icon: Wallet },
        { title: t.nav.term_deposits, href: termDeposits(), icon: Landmark },
        { title: t.nav.operations, href: operations(), icon: ArrowLeftRight },
    ];

    return views.map((view, index) => ({
        ...view,
        shortcut: String(index + 1),
    }));
}
