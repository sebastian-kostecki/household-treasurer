<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import { LayoutGrid } from '@lucide/vue';
import AppSidebar from '@/components/AppSidebar.vue';
import BrandMark from '@/components/BrandMark.vue';
import BudgetYearSwitcher from '@/components/BudgetYearSwitcher.vue';
import { Button } from '@/components/ui/button';
import {
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarProvider,
} from '@/components/ui/sidebar';
import { useTranslations } from '@/composables/useTranslations';
import { notifyBudgetYearSelectionFailed } from '@/lib/budget-year-selection';

type SidebarFrame = {
    key: string;
    label: string;
    defaultOpen: boolean;
    budgetYears: number[];
    budgetYear: number | null;
};

const t = useTranslations();
const appName = usePage().props.name;

const brandMarkPlaces = [
    {
        key: 'sidebar',
        label: 'Sidebar',
        surface: 'bg-sidebar',
        nameTone: 'text-sidebar-strong',
    },
    {
        key: 'welcome',
        label: 'Nagłówek Welcome',
        surface: 'bg-background',
        nameTone: 'text-foreground',
    },
    {
        key: 'login',
        label: 'Tło logowania',
        surface: 'bg-muted',
        nameTone: 'text-foreground',
    },
];

const stateMatrix = [
    {
        state: 'default',
        where: 'Ramki expanded-years i collapsed-years poniżej.',
    },
    {
        state: 'empty',
        where: 'Ramki expanded-empty i collapsed-empty poniżej (brak lat budżetu, nad stanem pustym etykieta „Rok budżetu”).',
    },
    {
        state: 'loading',
        where: 'N/A: powłoka nie ma deferred props. Jedyne oczekiwanie to pending przełącznika roku, pokazane jako disabled.',
    },
    {
        state: 'error',
        where: 'Przycisk „Pokaż błąd wyboru roku” poniżej (toast).',
    },
    {
        state: 'active',
        where: 'Ramka menu-states poniżej: pozycja w spoczynku i aktywna (miętowa także pod kursorem).',
    },
    {
        state: 'hover',
        where: 'Notatka przy ramce menu-states poniżej (najedź kursorem na pozycje).',
    },
    {
        state: 'focus-visible',
        where: 'Sekcja focus-visible poniżej (prawdziwe kontrolki, przejdź Tabem).',
    },
    {
        state: 'disabled',
        where: 'Trzecia pozycja ramki menu-states (aria-disabled i aria-busy, jak przełącznik roku podczas POST).',
    },
];

function showYearSelectionError(): void {
    notifyBudgetYearSelectionFailed(t.shell.select_year_failed);
}

const frames: SidebarFrame[] = [
    {
        key: 'expanded-years',
        label: 'Rozwinięty, z latami',
        defaultOpen: true,
        budgetYears: [2025, 2026],
        budgetYear: 2026,
    },
    {
        key: 'collapsed-years',
        label: 'Zwinięty, z latami',
        defaultOpen: false,
        budgetYears: [2025, 2026],
        budgetYear: 2026,
    },
    {
        key: 'expanded-empty',
        label: 'Rozwinięty, bez lat',
        defaultOpen: true,
        budgetYears: [],
        budgetYear: null,
    },
    {
        key: 'collapsed-empty',
        label: 'Zwinięty, bez lat',
        defaultOpen: false,
        budgetYears: [],
        budgetYear: null,
    },
];
</script>

<template>
    <Head title="Sidebar kitchen sink" />

    <div class="flex flex-1 flex-col gap-8 p-4">
        <header class="space-y-1">
            <h1 class="text-xl font-semibold">Sidebar kitchen sink</h1>
            <p class="text-muted-foreground text-sm">
                Strona deweloperska (tylko środowisko local), tylko desktop.
                Sidebar w czterech wariantach: rozwinięty i zwinięty, z latami
                budżetu i bez. Macierz stanów to standardowe siedem (default,
                empty, loading, error, hover, focus-visible, disabled) plus
                pozycja aktywna, właściwa dla menu. Elementy zależne od
                zalogowanego użytkownika (NavUser, źródła kopiowania w oknie
                dodawania roku) są prawdziwe, nie fixture. Zduplikowane id pól
                roku w CreateBudgetDialog są tu dopuszczalne.
            </p>
        </header>

        <section class="space-y-3" data-section="state-matrix">
            <h2
                class="text-muted-foreground border-b pb-1 font-mono text-sm font-semibold"
            >
                Macierz stanów
            </h2>
            <table class="w-full text-left text-sm">
                <thead>
                    <tr>
                        <th class="table-header pb-2.5 text-left">Stan</th>
                        <th class="table-header pb-2.5 text-left">
                            Gdzie widoczny
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="row in stateMatrix"
                        :key="row.state"
                        class="border-t align-top"
                    >
                        <td class="py-2 pr-4 font-mono">{{ row.state }}</td>
                        <td class="py-2">{{ row.where }}</td>
                    </tr>
                </tbody>
            </table>
            <Button
                type="button"
                variant="outline"
                size="sm"
                @click="showYearSelectionError"
            >
                Pokaż błąd wyboru roku
            </Button>
        </section>

        <div class="grid grid-cols-2 gap-6">
            <section
                v-for="frame in frames"
                :key="frame.key"
                class="space-y-2"
                :data-frame="frame.key"
            >
                <h2
                    class="text-muted-foreground border-b pb-1 font-mono text-sm font-semibold"
                >
                    {{ frame.key }}: {{ frame.label }}
                </h2>
                <div
                    class="relative h-svh transform-gpu overflow-hidden border"
                >
                    <SidebarProvider
                        :default-open="frame.defaultOpen"
                        class="h-full min-h-0"
                    >
                        <AppSidebar
                            :budget-years="frame.budgetYears"
                            :budget-year="frame.budgetYear"
                        />
                    </SidebarProvider>
                </div>
            </section>
        </div>

        <section class="space-y-2" data-frame="menu-states">
            <h2
                class="text-muted-foreground border-b pb-1 font-mono text-sm font-semibold"
            >
                menu-states: Pozycja w spoczynku i aktywna
            </h2>
            <SidebarProvider
                class="bg-sidebar text-sidebar-foreground min-h-0 w-(--sidebar-panel-width) p-2"
            >
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton>
                            <LayoutGrid />
                            <span>W spoczynku</span>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                    <SidebarMenuItem>
                        <SidebarMenuButton :is-active="true">
                            <LayoutGrid />
                            <span>Aktywna</span>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                    <SidebarMenuItem>
                        <SidebarMenuButton
                            aria-disabled="true"
                            aria-busy="true"
                        >
                            <LayoutGrid />
                            <span>Wyłączona</span>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarProvider>
            <p class="text-muted-foreground text-sm">
                Hover: najedź kursorem na pozycję w spoczynku, tło przechodzi na
                akcent sidebara. Aktywna pozycja zostaje miętowa także pod
                kursorem.
            </p>
        </section>

        <section class="space-y-2" data-frame="focus-visible">
            <h2
                class="text-muted-foreground border-b pb-1 font-mono text-sm font-semibold"
            >
                focus-visible: Pierścień na kontrolkach sidebara
            </h2>
            <p class="text-muted-foreground text-sm">
                Przejdź klawiszem Tab przez pozycje menu i przycisk roku;
                pierścień ma być widoczny na tle sidebara.
            </p>
            <SidebarProvider
                class="bg-sidebar text-sidebar-foreground min-h-0 w-(--sidebar-panel-width) p-2"
            >
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton>
                            <LayoutGrid />
                            <span>Pierwsza pozycja</span>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                    <SidebarMenuItem>
                        <SidebarMenuButton :is-active="true">
                            <LayoutGrid />
                            <span>Aktywna pozycja</span>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
                <BudgetYearSwitcher
                    :budget-years="[2025, 2026]"
                    :budget-year="2026"
                />
            </SidebarProvider>
        </section>

        <section class="space-y-2" data-frame="brand-mark">
            <h2
                class="text-muted-foreground border-b pb-1 font-mono text-sm font-semibold"
            >
                brand-mark: Znak w miejscach użycia
            </h2>
            <div class="grid grid-cols-3 gap-6">
                <div
                    v-for="place in brandMarkPlaces"
                    :key="place.key"
                    class="space-y-1"
                >
                    <p class="text-muted-foreground text-sm">
                        {{ place.label }}
                    </p>
                    <div
                        class="flex items-center gap-2 border p-4"
                        :class="place.surface"
                    >
                        <BrandMark />
                        <span class="font-semibold" :class="place.nameTone">{{
                            appName
                        }}</span>
                    </div>
                </div>
            </div>
        </section>
    </div>
</template>
