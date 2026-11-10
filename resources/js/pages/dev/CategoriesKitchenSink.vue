<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { defineAsyncComponent } from 'vue';
import CategoryArchiveSection from '@/components/CategoryArchiveSection.vue';
import {
    archivedCategories,
    categoriesScenarios,
} from '@/lib/categories-fixtures';

const Categories = defineAsyncComponent(() => import('@/pages/Categories.vue'));

const stateMatrix = [
    {
        state: 'default',
        where: 'Scenariusz default poniżej: Badge „aktywnych” w tytule, sekcje Przychody i Wydatki z nagłówkiem kolumn (od md), lata pozycji w kolejności rosnącej, kreska z tekstem dla czytnika ekranu przy kategorii bez pozycji i link „Operacje →”. Scenariusze with-archive (zwinięte archiwum) i archive-open (rozwinięte, z datą archiwizacji pod nazwą), one-type-empty i only-archived.',
    },
    {
        state: 'hover',
        where: 'Zrzuty z działającej aplikacji (stan zależny od interakcji): tło wiersza `secondary` i podkreślenie linku „Operacje →”.',
    },
    {
        state: 'focus-visible',
        where: 'Zrzuty z działającej aplikacji po Tab: link „Operacje →” i przycisk rozwijający archiwum (pierścień `ring`).',
    },
    {
        state: 'disabled',
        where: 'N/A: widok jest tylko do odczytu, nie ma przycisków ani pól do wyłączenia (edycja w Budżecie, S-19).',
    },
    {
        state: 'error',
        where: 'N/A: widok nie ma formularzy ani żądań zapisu; błąd odczytu pokazuje strona błędu Inertii.',
    },
    {
        state: 'empty',
        where: 'Scenariusz empty poniżej (użytkownik bez kategorii), one-type-empty (brak aktywnych przychodów) i only-archived (tylko archiwum).',
    },
    {
        state: 'loading',
        where: 'N/A: strona nie ma deferred props, więc nie ma szkieletu; wejście w widok to zwykła wizyta Inertii.',
    },
];
</script>

<template>
    <Head title="Categories kitchen sink" />

    <div class="flex flex-1 flex-col gap-8 p-4">
        <header class="space-y-1">
            <h1 class="page-title">Categories kitchen sink</h1>
            <p class="text-muted-foreground text-sm">
                Strona deweloperska (tylko środowisko local). Widok Kategorie w
                każdym scenariuszu danych. Linki „Operacje →” prowadzą do
                prawdziwych /operations z filtrem po id, którego fixture'y nie
                mają w bazie.
            </p>
        </header>

        <section class="space-y-3" data-section="state-matrix">
            <h2
                class="text-muted-foreground border-border-soft border-b pb-1 font-mono text-sm font-semibold"
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
                        class="border-border-soft border-t align-top"
                    >
                        <td class="py-2 pr-4 font-mono">{{ row.state }}</td>
                        <td class="py-2">{{ row.where }}</td>
                    </tr>
                </tbody>
            </table>
        </section>

        <section
            v-for="scenario in categoriesScenarios"
            :key="scenario.key"
            :data-scenario="scenario.key"
            class="space-y-2"
        >
            <h2
                class="text-muted-foreground border-border-soft border-b pb-1 font-mono text-sm font-semibold"
            >
                {{ scenario.title }}
            </h2>
            <Categories v-bind="scenario.props" />
        </section>

        <section class="space-y-2" data-scenario="archive-open">
            <h2
                class="text-muted-foreground border-border-soft border-b pb-1 font-mono text-sm font-semibold"
            >
                archive-open: rozwinięte archiwum z datą archiwizacji i długą
                nazwą
            </h2>
            <CategoryArchiveSection
                :categories="archivedCategories"
                default-open
            />
        </section>
    </div>
</template>
