<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppSidebar from '@/components/AppSidebar.vue';
import { Button } from '@/components/ui/button';
import { SidebarProvider } from '@/components/ui/sidebar';
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

const stateMatrix = [
    {
        state: 'default',
        where: 'Ramki expanded-years i collapsed-years poniżej.',
    },
    {
        state: 'empty',
        where: 'Ramki expanded-empty i collapsed-empty poniżej (brak lat budżetu).',
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
        state: 'hover',
        where: 'Zrzuty z działającej aplikacji (stan zależny od interakcji).',
    },
    {
        state: 'focus-visible',
        where: 'Zrzuty z działającej aplikacji (stan zależny od interakcji).',
    },
    {
        state: 'disabled',
        where: 'Zrzuty z działającej aplikacji przy spowolnionej sieci (przełącznik roku podczas POST).',
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
                budżetu i bez. Elementy zależne od zalogowanego użytkownika
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
                        <th class="column-caption pb-2.5 text-left">Stan</th>
                        <th class="column-caption pb-2.5 text-left">
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
                    class="relative h-160 transform-gpu overflow-hidden border"
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
    </div>
</template>
