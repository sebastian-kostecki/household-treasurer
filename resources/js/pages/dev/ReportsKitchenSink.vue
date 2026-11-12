<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { defineAsyncComponent } from 'vue';
import ReportBarChartPlaceholder from '@/components/ReportBarChartPlaceholder.vue';
import ReportTrendChartPlaceholder from '@/components/ReportTrendChartPlaceholder.vue';
import {
    comparedYearReport,
    reportScenarios,
    trendInProgress,
    yearReport,
} from '@/lib/reports-fixtures';

const Reports = defineAsyncComponent(() => import('@/pages/Reports.vue'));

const stateMatrix = [
    {
        state: 'default',
        where: 'Scenariusze year-in-progress, month-closed, archived-in-group, refund-negative-share, non-positive-expenses poniżej.',
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
        where: 'Scenariusze last-12-full (pełne pokrycie), last-12-partial (notka, brak na początku), last-12-partial-end (notka, brak na końcu), last-12-no-coverage (stan pusty), last-12-no-operations i last-12-phone (cztery segmenty w 22rem). Okno bez paska miesięcy, bez porównania i bez Trendu.',
    },
    {
        state: 'empty',
        where: 'Scenariusze no-operations, trend-no-operations, last-12-no-coverage, last-12-no-operations, draft, draft-last-12 i no-budget poniżej.',
    },
    {
        state: 'loading',
        where: 'Sekcja chart-loading: placeholdery wykresów (słupkowy bez i z porównaniem, Trend) o wysokości wykresu, widoczne do doładowania fragmentu.',
    },
    {
        state: 'error',
        where: 'N/A: strona tylko czyta, nie ma formularzy ani żądań w tle; błąd ładowania obsługuje strona błędu Inertia.',
    },
    {
        state: 'disabled',
        where: 'N/A: przełącznik okresu i wiersze grup nie mają stanu wyłączonego.',
    },
    {
        state: 'hover',
        where: 'Nagłówek grupy i przyciski miesięcy w scenariuszach poniżej (najechanie myszą).',
    },
    {
        state: 'focus-visible',
        where: 'Kolejność Tab w scenariuszach: przełącznik okresu, miesiące, przyciski zwijania grup.',
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
                Placeholdery wykresów: stoją od pierwszej klatki i trzymają
                wysokość wykresu, dopóki fragment się nie doładuje.
            </p>
            <ReportBarChartPlaceholder
                type="expense"
                :entries="yearReport.expense"
                :budget-year="2026"
            />
            <ReportBarChartPlaceholder
                type="expense"
                :entries="comparedYearReport.expense"
                :budget-year="2026"
                :comparison-year="comparedYearReport.comparison?.year"
            />
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
