<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { defineAsyncComponent } from 'vue';
import ReportTrendChartPlaceholder from '@/components/ReportTrendChartPlaceholder.vue';
import { reportScenarios, trendInProgress } from '@/lib/reports-fixtures';

// Lazy on purpose: a static import would fold Reports.vue into this chunk and
// drop it from the Vite manifest, so Inertia could no longer render /reports
// ("Unable to locate file in Vite manifest").
const Reports = defineAsyncComponent(() => import('@/pages/Reports.vue'));

const stateMatrix = [
    {
        state: 'default',
        where: 'Ranking (kategorie od największych, numer, pasek udziału w chart-1, przychody w success, pusty track dla kwoty niedodatniej) w scenariuszach year-in-progress, month-closed, archived-in-group, refund-negative-share, non-positive-expenses poniżej.',
    },
    {
        state: 'ranking',
        where: 'Scenariusze ranking-groups (tryb Grupy: grupy zwinięte, po rozwinięciu kategorie po kwocie) i ranking-tie (remis Czynsz 1 800 i Prąd 1 800: wyżej wcześniejszy na liście).',
    },
    {
        state: 'panel',
        where: 'Scenariusze panel-year-in-progress (kreskowany miesiąc w toku, „sty–paź”, przyszły miesiąc pusty), panel-year-closed („sty–gru”, bez wiersza „w toku”), panel-january („Średnio” „—”), panel-comparison-only (Kino 0,00 / 600,00 / −600,00, bez przycisku Operacje), panel-income (success, bez Udziału i rytmu), panel-negative-month (słupek zerowy, kwota w sr-only), panel-no-previous (bez N−1 i Różnicy), panel-groups (grupa wybranej kategorii rozwinięta) i panel-phone (arkusz zamknięty, Ranking bez podświetlenia; tapnięcie nazwy otwiera arkusz). Scenariusze są statyczne: klik nazwy albo „Grupy” robi prawdziwą wizytę klienta na /reports (adres zmienia się na /reports?…, odświeżenie opuszcza sink), a scenariusz z fixtures nie przerysowuje wyboru ani trybu, więc każdy stan ma własny scenariusz. Wszystkie instancje dzielą id report-category-panel.',
    },
    {
        state: 'comparison',
        where: 'Scenariusze compared-year-in-progress (z notką), compared-month-closed, compared-one-side-only, compared-empty-current i compared-phone (dwie linie wiersza); scenariusze bez przedrostka compared- to raport bez porównania.',
    },
    {
        state: 'trend',
        where: 'Scenariusze trend-in-progress (miesiąc „w toku”, miesiące przyszłe z „—”), trend-closed, trend-irregular-and-zero (kategoria nieregularna, netto 0, ujemna komórka), trend-negative-result, trend-archived-in-group, trend-no-operations i trend-phone (przewijanie z przypiętą nazwą). Grupy startują zwinięte.',
    },
    {
        state: 'last-12',
        where: 'Scenariusze last-12-full (pełne pokrycie), last-12-partial (notka, brak na początku), last-12-partial-end (notka, brak na końcu), last-12-no-coverage (stan pusty), last-12-no-operations i last-12-phone (pięć segmentów w 22rem). Okno bez paska miesięcy, bez porównania i bez Trendu.',
    },
    {
        state: 'years',
        where: 'Scenariusze years-same-day (domyślny: „Do 14 listopada”, Wydatki; tryb „Pełne lata” i zakładkę Przychody ogląda się kliknięciem, bo stan jest lokalny), years-no-current-year (brak przełącznika zakresu), years-only-current (zmiana i statystyki „—”), years-leap („Do 29 lutego”), years-no-coverage (stan pusty), years-no-operations, years-draft-selected (szkic w sidebarze, widok zostaje) i years-phone (przewijanie z przypiętą nazwą, pięć segmentów).',
    },
    {
        state: 'empty',
        where: 'Scenariusze no-operations, trend-no-operations, last-12-no-coverage, last-12-no-operations, years-no-coverage, years-no-operations, draft, draft-last-12 i no-budget poniżej.',
    },
    {
        state: 'loading',
        where: 'Sekcja chart-loading: placeholder wykresu Trendu o wysokości wykresu, widoczny do doładowania fragmentu (Ranking nie ma wykresu do doładowania).',
    },
    {
        state: 'error',
        where: 'N/A: strona tylko czyta, nie ma formularzy ani żądań w tle; błąd ładowania obsługuje strona błędu Inertia.',
    },
    {
        state: 'disabled',
        where: 'Przełącznik zakresu „Do D | Pełne lata” jest ukryty, gdy nie ma roku bieżącego (years-no-current-year); poza tym N/A: przełącznik okresu i wiersze grup nie mają stanu wyłączonego.',
    },
    {
        state: 'hover',
        where: 'Nagłówek grupy, przyciski miesięcy, segmenty przełącznika okresu, zakładki Wydatki | Przychody i przełącznik zakresu „Lat” w scenariuszach poniżej (najechanie myszą).',
    },
    {
        state: 'focus-visible',
        where: 'Kolejność Tab w scenariuszach: przełącznik okresu, miesiące, zakładki i przełącznik zakresu „Lat”, region przewijany tabeli, przyciski zwijania grup.',
    },
];
</script>

<template>
    <Head title="Reports kitchen sink" />

    <div class="flex flex-1 flex-col gap-8 p-4">
        <header class="space-y-1">
            <h1 class="page-title">Reports kitchen sink</h1>
            <p class="text-muted-foreground text-sm">
                Strona deweloperska (tylko środowisko local). Prawdziwa strona
                Raporty (Reports.vue) w każdym scenariuszu danych z fixture.
            </p>
        </header>

        <section class="space-y-3" data-section="state-matrix">
            <h2 class="section-title border-b pb-1">Macierz stanów</h2>
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
        </section>

        <section class="space-y-3" data-section="chart-loading">
            <h2 class="section-title border-b pb-1">chart-loading</h2>
            <p class="text-muted-foreground text-sm">
                Placeholder wykresu Trendu: stoi od pierwszej klatki i trzyma
                wysokość wykresu, dopóki fragment się nie doładuje.
            </p>
            <ReportTrendChartPlaceholder
                :months="trendInProgress.months"
                :in-progress-month="trendInProgress.in_progress_month"
            />
        </section>

        <section
            v-for="scenario in reportScenarios"
            :key="scenario.key"
            :data-scenario="scenario.key"
            class="space-y-2"
        >
            <h2 class="section-title border-b pb-1">
                {{ scenario.key }}
            </h2>
            <p class="text-muted-foreground text-sm">{{ scenario.caption }}</p>

            <div :class="{ 'report-phone-frame': scenario.phone }">
                <Reports v-bind="scenario.props" />
            </div>
        </section>
    </div>
</template>
