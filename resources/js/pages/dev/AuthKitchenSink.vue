<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { auth } from '@/routes/dev/ui';

defineProps<{
    screens: Array<{ key: string; label: string; states: string[] }>;
}>();

const stateMatrix = [
    {
        state: 'default, status, no-email',
        where: 'Linki poniżej: każdy ekran wejścia jako pełna strona, z danymi przykładowymi. Stany error i recovery nie mają linków: Form wypełnia błędy dopiero po odrzuconym wysłaniu, więc zrzuty error powstają z prawdziwego wysłania formularza, a tryb kodu odzyskiwania włącza link w karcie ekranu kodu.',
    },
    {
        state: 'loading',
        where: 'N/A: to spinner na przycisku podczas wysyłki formularza, widoczny na zrzutach z działającej aplikacji.',
    },
    {
        state: 'hover, focus-visible',
        where: 'N/A: stany zależne od interakcji, widoczne na zrzutach z działającej aplikacji.',
    },
];
</script>

<template>
    <Head title="Auth kitchen sink" />

    <div class="mx-auto flex max-w-3xl flex-col gap-8 p-6">
        <header class="space-y-1">
            <h1 class="page-title">Auth kitchen sink</h1>
            <p class="text-muted-foreground text-sm">
                Strona deweloperska (tylko środowisko local). Każdy ekran
                wejścia w każdym stanie otwiera się jako pełna strona w układzie
                logowania.
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
        </section>

        <section
            v-for="screen in screens"
            :key="screen.key"
            class="space-y-3"
            :data-section="screen.key"
        >
            <h2
                class="text-muted-foreground border-b pb-1 font-mono text-sm font-semibold"
            >
                {{ screen.label }} ({{ screen.key }})
            </h2>
            <ul class="flex flex-wrap gap-x-6 gap-y-1 text-sm">
                <li v-for="state in screen.states" :key="state">
                    <Link
                        class="font-mono underline"
                        :href="
                            auth({ query: { screen: screen.key, state } }).url
                        "
                    >
                        {{ state }}
                    </Link>
                </li>
            </ul>
        </section>
    </div>
</template>
