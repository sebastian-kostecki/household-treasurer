<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import { LayoutGrid } from '@lucide/vue';
import { nextTick, onMounted, ref } from 'vue';
import AppMobileBar from '@/components/AppMobileBar.vue';
import AppSidebar from '@/components/AppSidebar.vue';
import CommandPalette, {
    type PaletteCategories,
} from '@/components/CommandPalette.vue';
import BrandMark from '@/components/BrandMark.vue';
import BudgetYearSwitcher from '@/components/BudgetYearSwitcher.vue';
import { Button } from '@/components/ui/button';
import {
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarProvider,
} from '@/components/ui/sidebar';
import { useAppViews } from '@/composables/useAppViews';
import { useTranslations } from '@/composables/useTranslations';
import { paletteFixtureCategories } from '@/lib/command-palette-fixtures';
import { notifyBudgetYearSelectionFailed } from '@/lib/budget-year-selection';

type SidebarFrame = {
    key: string;
    label: string;
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

const views = useAppViews();

type PaletteState =
    | 'default'
    | 'filtered'
    | 'no-results'
    | 'loading'
    | 'error'
    | 'no-budget-year';

const paletteStates: { state: PaletteState; search: string }[] = [
    { state: 'default', search: '' },
    { state: 'filtered', search: 'zyw' },
    { state: 'no-results', search: 'zzzz' },
    { state: 'loading', search: '' },
    { state: 'error', search: '' },
    { state: 'no-budget-year', search: '' },
];

const requestedPalette = new URLSearchParams(
    usePage().url.split('?')[1] ?? '',
).get('palette');
const activePalette =
    paletteStates.find((entry) => entry.state === requestedPalette) ?? null;
const paletteOpen = ref(activePalette !== null);

function fixturePaletteCategories(): PaletteCategories {
    switch (activePalette?.state) {
        case 'loading':
            return { status: 'loading' };
        case 'error':
            return { status: 'error' };
        case 'no-budget-year':
            return { status: 'loaded', budgetYear: null, items: [] };
        default:
            return paletteFixtureCategories;
    }
}

// The query lives inside the registry `Command`, so a filtered state types it
// into the real input and lets the component filter.
onMounted(() => {
    if (activePalette === null || activePalette.search === '') {
        return;
    }

    setTimeout(() => {
        void nextTick(() => {
            const input = document.querySelector<HTMLInputElement>(
                '[data-slot="command-input"]',
            );

            if (input !== null) {
                input.value = activePalette.search;
                input.dispatchEvent(new Event('input', { bubbles: true }));
            }
        });
    }, 50);
});

const stateMatrix = [
    {
        state: 'default',
        where: 'Ramka expanded-years poniżej oraz mobile-bar (pasek z ☰ widoczny tylko na telefonie).',
    },
    {
        state: 'empty',
        where: 'Ramka expanded-empty poniżej (brak lat budżetu, nad stanem pustym etykieta „Rok budżetu”).',
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
        state: 'palette',
        where: 'Sekcja palette-* poniżej: ?palette=default, filtered, no-results, loading, error i no-budget-year otwierają paletę z danymi stałymi.',
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

type YearFrame = SidebarFrame & { pending: boolean };

const yearFrames: YearFrame[] = [
    {
        key: 'years-one',
        label: 'Jeden rok',
        budgetYears: [2026],
        budgetYear: 2026,
        pending: false,
    },
    {
        key: 'years-many',
        label: 'Wiele lat',
        budgetYears: [2019, 2020, 2021, 2022, 2023, 2024, 2025, 2026],
        budgetYear: 2026,
        pending: false,
    },
    {
        key: 'years-empty',
        label: 'Bez budżetu',
        budgetYears: [],
        budgetYear: null,
        pending: false,
    },
    {
        key: 'years-pending',
        label: 'Oczekiwanie',
        budgetYears: [2025, 2026],
        budgetYear: 2026,
        pending: true,
    },
];

function markPending(instance: unknown): void {
    const exposed = instance as { pending?: boolean } | null;

    if (exposed !== null && exposed !== undefined) {
        exposed.pending = true;
    }
}

const frames: SidebarFrame[] = [
    {
        key: 'expanded-years',
        label: 'Z latami',
        budgetYears: [2025, 2026],
        budgetYear: 2026,
    },
    {
        key: 'expanded-empty',
        label: 'Bez lat',
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
                Sidebar jest zawsze rozwinięty (bez trybu ikon); dwa warianty: z
                latami budżetu i bez, oraz pasek mobilny z przyciskiem menu.
                Macierz stanów to standardowe siedem (default, empty, loading,
                error, hover, focus-visible, disabled) plus pozycja aktywna,
                właściwa dla menu. Elementy zależne od zalogowanego użytkownika
                (NavUser, źródła kopiowania w oknie dodawania roku) są
                prawdziwe, nie fixture. Zduplikowane id pól roku w
                CreateBudgetDialog są tu dopuszczalne.
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
                    <SidebarProvider :open="true" class="h-full min-h-0">
                        <AppSidebar
                            :budget-years="frame.budgetYears"
                            :budget-year="frame.budgetYear"
                        />
                    </SidebarProvider>
                </div>
            </section>
        </div>

        <section class="space-y-2" data-frame="years">
            <h2
                class="text-muted-foreground border-b pb-1 font-mono text-sm font-semibold"
            >
                years-*: Rok budżetu w segmentach
            </h2>
            <p class="text-muted-foreground text-sm">
                Przełącznik w panelu 224 px: jeden rok z „+”, wiele lat (trzy
                wiersze), brak budżetu i oczekiwanie (segmenty wyłączone). „+”
                otwiera okno nowego budżetu tylko w ramkach z pełnym sidebarem
                (expanded-*).
            </p>
            <div class="grid grid-cols-4 gap-6">
                <div
                    v-for="frame in yearFrames"
                    :key="frame.key"
                    class="space-y-1"
                    :data-frame="frame.key"
                >
                    <p class="text-muted-foreground font-mono text-sm">
                        {{ frame.key }}
                    </p>
                    <SidebarProvider
                        class="bg-sidebar text-sidebar-foreground min-h-0 w-(--sidebar-panel-width) p-2"
                    >
                        <BudgetYearSwitcher
                            :ref="frame.pending ? markPending : undefined"
                            :budget-years="frame.budgetYears"
                            :budget-year="frame.budgetYear"
                        />
                    </SidebarProvider>
                </div>
            </div>
        </section>

        <section class="space-y-2" data-frame="palette">
            <h2
                class="text-muted-foreground border-b pb-1 font-mono text-sm font-semibold"
            >
                palette-*: Szukaj i przejdź (⌘K)
            </h2>
            <p class="text-muted-foreground text-sm">
                Każdy stan otwiera paletę z danymi stałymi po przeładowaniu
                strony (parametr <code>?palette=</code>). Kategorie ładują się z
                endpointu tylko w aplikacji, tu są fiksurami.
            </p>
            <ul class="flex flex-wrap gap-4 font-mono text-sm">
                <li v-for="entry in paletteStates" :key="entry.state">
                    <a
                        class="underline"
                        :href="`?palette=${entry.state}`"
                        :data-palette-state="entry.state"
                        >{{ entry.state }}</a
                    >
                </li>
            </ul>
            <CommandPalette
                v-model:open="paletteOpen"
                :views="views"
                :categories="fixturePaletteCategories()"
            />
        </section>

        <section class="space-y-2" data-frame="mobile-bar">
            <h2
                class="text-muted-foreground border-b pb-1 font-mono text-sm font-semibold"
            >
                mobile-bar: Pasek z przyciskiem menu (telefon)
            </h2>
            <p class="text-muted-foreground text-sm">
                Na telefonie (≤ 768 px) pasek stoi nad treścią strony i otwiera
                arkusz z sidebarem; tu rysowany bez względu na szerokość.
            </p>
            <SidebarProvider class="min-h-0 w-full max-w-sm border">
                <AppMobileBar />
            </SidebarProvider>
        </section>

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
                            <kbd class="kbd" aria-hidden="true">1</kbd>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                    <SidebarMenuItem>
                        <SidebarMenuButton :is-active="true">
                            <LayoutGrid />
                            <span>Aktywna</span>
                            <kbd class="kbd" aria-hidden="true">2</kbd>
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
