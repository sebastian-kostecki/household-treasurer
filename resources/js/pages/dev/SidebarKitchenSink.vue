<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppSidebar from '@/components/AppSidebar.vue';
import { SidebarProvider } from '@/components/ui/sidebar';

type SidebarFrame = {
    key: string;
    label: string;
    defaultOpen: boolean;
    budgetYears: number[];
    budgetYear: number | null;
};

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
