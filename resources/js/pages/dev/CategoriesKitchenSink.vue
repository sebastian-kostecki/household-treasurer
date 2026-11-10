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
        where: 'Scenariusz default poniżej: Badge „aktywnych” w tytule, sekcje Przychody i Wydatki z przyciskiem „Dodaj kategorię” w tytule i nagłówkiem kolumn (od md), uchwyt przeciągania i akcje „Edytuj”, „Archiwizuj”, „Usuń” w każdym wierszu, lata pozycji w kolejności rosnącej, kreska z tekstem dla czytnika ekranu przy kategorii bez pozycji i link „Operacje →”. Opis jako przygaszona druga linia pod nazwą: krótki (Wynagrodzenie) i długi, ucięty z pełnym tekstem w `title` (Żywność). „Usuń” tylko przy can_delete (Odsetki, Rekolekcje, Bez pozycji), reszta bez; typ zablokowany (can_change_type) w dialogu edycji Wynagrodzenia i Żywności (/dev/ui/dialogs). Scenariusze with-archive (zwinięte archiwum) i archive-open (rozwinięte: data archiwizacji pod nazwą, „Przywróć” i „Usuń” gdy can_delete, bez uchwytu i edycji), one-type-empty i only-archived.',
    },
    {
        state: 'hover',
        where: 'Zrzuty z działającej aplikacji (stan zależny od interakcji): tło wiersza `secondary`, kolor uchwytu `foreground`, tło ikon akcji i podkreślenie linku „Operacje →”.',
    },
    {
        state: 'focus-visible',
        where: 'Zrzuty z działającej aplikacji po Tab: przyciski „Dodaj kategorię”, ikony akcji (obrys `ring`), link „Operacje →” i przycisk rozwijający archiwum; uchwyt przeciągania ma `tabindex=-1` (kolejność zmienia się myszą lub dotykiem).',
    },
    {
        state: 'disabled',
        where: 'Typ zablokowany w dialogu edycji (SegmentedControl wyłączony z podpowiedzią, /dev/ui/dialogs: Kategoria listy: typ zablokowany); przeciąganie wyłączone na czas zapisu kolejności (`reorderHttp.processing`); „Usuń” nie pojawia się, gdy nie można usunąć (can_delete wył. w default).',
    },
    {
        state: 'error',
        where: 'Dialogi na /dev/ui/dialogs: Kategoria listy: błędy pól i nazwa zajęta przez kategorię z archiwum. Nieudane przeciągnięcie przywraca kolejność i pokazuje toast „Nie udało się zapisać kolejności”.',
    },
    {
        state: 'empty',
        where: 'Scenariusz empty poniżej (użytkownik bez kategorii, z przyciskiem „Dodaj kategorię”), one-type-empty (brak aktywnych przychodów) i only-archived (tylko archiwum).',
    },
    {
        state: 'loading',
        where: 'Zapis w dialogach i potwierdzeniach (spinner na przycisku, blokada zamknięcia): /dev/ui/dialogs, stany „Kategoria listy: zapis w toku”. Sama strona nie ma deferred props, więc nie ma szkieletu.',
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
