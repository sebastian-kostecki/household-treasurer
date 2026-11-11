<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { defineAsyncComponent, ref } from 'vue';
import DialogsKitchenSinkController from '@/actions/App/Http/Controllers/Dev/DialogsKitchenSinkController';
import CategoryArchiveSection from '@/components/CategoryArchiveSection.vue';
import CategoryGroupFormDialog from '@/components/CategoryGroupFormDialog.vue';
import CategoryGroupRow from '@/components/CategoryGroupRow.vue';
import CategoryListRow from '@/components/CategoryListRow.vue';
import ConfirmActionDialog from '@/components/ConfirmActionDialog.vue';
import ListSurface from '@/components/ListSurface.vue';
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/composables/useTranslations';
import {
    archivedCategories,
    categoriesScenarios,
    expenseGroups,
    groupedExpenseCategories,
} from '@/lib/categories-fixtures';
import { groupDeleteDescription } from '@/lib/category-list';

const Categories = defineAsyncComponent(() => import('@/pages/Categories.vue'));

const t = useTranslations();

// The group dialogs post to the dialogs sink endpoint, which waits and rejects
// every field, so the saving state and the name error are visible without
// touching the User's data.
const validateForm = DialogsKitchenSinkController.validate.form();
const plainForm = { action: '#', method: 'post' } as const;

const createDialogOpen = ref(false);
const renameDialogOpen = ref(false);
const deleteDialogOpen = ref(false);
const deleteEmptyDialogOpen = ref(false);
const deleteArchivedOnlyDialogOpen = ref(false);

const deleteCopy = {
    group_delete_description: t.categories.group_delete_description,
    group_delete_orphans_zero: t.categories.group_delete_orphans_zero,
    group_delete_orphans_one: t.categories.group_delete_orphans_one,
    group_delete_orphans_few: t.categories.group_delete_orphans_few,
    group_delete_orphans_many: t.categories.group_delete_orphans_many,
    group_delete_archived: t.categories.group_delete_archived,
};

const pickerCategory = groupedExpenseCategories[3];

const stateMatrix = [
    {
        state: 'default',
        where: 'Scenariusz default poniżej: Badge „aktywnych” w tytule, sekcje Przychody i Wydatki z przyciskiem „Dodaj kategorię” w tytule i nagłówkiem kolumn (od md), uchwyt przeciągania i akcje „Edytuj”, „Archiwizuj”, „Usuń” w każdym wierszu, lata pozycji w kolejności rosnącej, kreska z tekstem dla czytnika ekranu przy kategorii bez pozycji i link „Operacje →”. Rytm wydatku jako pierwszy człon przygaszonej drugiej linii pod nazwą (stała opłata: Bez pozycji w budżecie, Czynsz; bieżąca: Żywność, Rozrywka; równomierna roczna: Rekolekcje, Woda; nieregularna: Serwis auta, tylko etykieta rytmu, bez kwoty zebranego), przychód bez rytmu; w oknie kategorii pole „Rytm” tylko dla wydatku, z podpowiedzią „Zmiana działa od listopada 2026” (/dev/ui/dialogs: Kategoria listy: nowa, edycja, edycja rytmu, nowe oznaczenie nieregularnej, edycja nieregularnej, zła kwota zebranego startowego, przychód bez pola, błąd pola). Opis jako przygaszona druga linia pod nazwą: krótki (Wynagrodzenie) i długi, ucięty z pełnym tekstem w `title` (Żywność). „Usuń” tylko przy can_delete (Odsetki, Rekolekcje, Bez pozycji), reszta bez; typ zablokowany (can_change_type) w dialogu edycji Wynagrodzenia i Żywności (/dev/ui/dialogs). Scenariusze with-archive (zwinięte archiwum) i archive-open (rozwinięte: data archiwizacji pod nazwą, „Przywróć” i „Usuń” gdy can_delete, bez uchwytu i edycji), one-type-empty i only-archived.',
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
        state: 'groups',
        where: 'Scenariusz with-groups: wiersz grupy (tło `muted`, nazwa, liczba kategorii, menu ⋯), kategorie wcięte pod grupą, grupa rozwinięta z wieloma kategoriami (Opłaty stałe), pusta grupa z podpowiedzią i strefą upuszczenia (Podróże), kategorie bez grupy na poziomie typu. Sekcja „Okna i menu grup” poniżej: menu „Grupa…”, okno „Dodaj grupę”, „Zmień nazwę” z błędem nazwy po zapisie, potwierdzenie usunięcia (z liczbą kategorii, które zostaną bez grupy, także zarchiwizowanych; grupa z samymi zarchiwizowanymi nie jest „pusta”) i wiersz grupy z przyciskami W górę / W dół wyłączonymi na brzegach. Scenariusz only-empty-group: sekcja z samą pustą grupą.',
    },
    {
        state: 'disabled',
        where: 'Typ zablokowany w dialogu edycji (SegmentedControl wyłączony z podpowiedzią, /dev/ui/dialogs: Kategoria listy: typ zablokowany); przeciąganie wyłączone na czas zapisu kolejności (`reorderHttp.processing`); „Usuń” nie pojawia się, gdy nie można usunąć (can_delete wył. w default). Sekcja „Okna i menu grup”: wiersz grupy na pierwszym miejscu ma „W górę” wyłączone, na ostatnim „W dół”. Uchwyt przeciągania jest ukryty poniżej md (na telefonie kategorię przenosi menu „Grupa…”).',
    },
    {
        state: 'error',
        where: 'Dialogi na /dev/ui/dialogs: Kategoria listy: błędy pól i nazwa zajęta przez kategorię z archiwum. Nieudane przeciągnięcie przywraca kolejność i pokazuje toast „Nie udało się zapisać kolejności”. Sekcja „Okna i menu grup”: okno „Zmień nazwę” zapisuje przez endpoint sinka, który odrzuca pole nazwy (komunikat pod polem); niepowodzenie zapisu kolejności to toast z przywróceniem poprzedniego porządku (przeciągnięcie w sinku trafia w prawdziwy endpoint i kończy się błędem).',
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

        <section class="space-y-2" data-scenario="group-states">
            <h2
                class="text-muted-foreground border-border-soft border-b pb-1 font-mono text-sm font-semibold"
            >
                group-states: okna i menu grup (menu „Grupa…” otwarte, wiersz
                grupy na brzegach)
            </h2>
            <ListSurface flush>
                <template #header>
                    <h2 class="section-title">
                        {{ t.categories.expense_title }}
                    </h2>
                </template>
                <CategoryGroupRow
                    :group="expenseGroups[0]"
                    type="expense"
                    :count="2"
                    :can-move-up="false"
                    :can-move-down="true"
                />
                <CategoryListRow
                    nested
                    picker-open
                    :category="pickerCategory"
                    :groups="expenseGroups"
                />
                <CategoryGroupRow
                    :group="expenseGroups[2]"
                    type="expense"
                    :count="0"
                    :can-move-up="true"
                    :can-move-down="false"
                />
            </ListSurface>
            <div class="flex flex-wrap gap-2">
                <Button type="button" @click="createDialogOpen = true">
                    {{ t.categories.add_group }}
                </Button>
                <Button
                    type="button"
                    variant="outline"
                    @click="renameDialogOpen = true"
                >
                    {{ t.categories.group_rename }} (błąd nazwy po zapisie)
                </Button>
                <Button
                    type="button"
                    variant="outline"
                    @click="deleteDialogOpen = true"
                >
                    {{ t.categories.group_delete }} (5 kategorii i 1
                    zarchiwizowana)
                </Button>
                <Button
                    type="button"
                    variant="outline"
                    @click="deleteArchivedOnlyDialogOpen = true"
                >
                    {{ t.categories.group_delete }} (tylko zarchiwizowane)
                </Button>
                <Button
                    type="button"
                    variant="outline"
                    @click="deleteEmptyDialogOpen = true"
                >
                    {{ t.categories.group_delete }} (pusta)
                </Button>
            </div>
            <CategoryGroupFormDialog
                v-model:open="createDialogOpen"
                mode="create"
                type="expense"
                :form="validateForm"
            />
            <CategoryGroupFormDialog
                v-model:open="renameDialogOpen"
                mode="rename"
                type="expense"
                :group="expenseGroups[1]"
                :form="validateForm"
            />
            <ConfirmActionDialog
                v-model:open="deleteDialogOpen"
                :form="plainForm"
                destructive
                :title="t.categories.group_delete_title"
                :description="groupDeleteDescription(5, 1, deleteCopy)"
                :confirm-label="t.categories.group_delete"
                :cancel-label="t.categories.group_cancel"
            />
            <ConfirmActionDialog
                v-model:open="deleteEmptyDialogOpen"
                :form="plainForm"
                destructive
                :title="t.categories.group_delete_title"
                :description="groupDeleteDescription(0, 0, deleteCopy)"
                :confirm-label="t.categories.group_delete"
                :cancel-label="t.categories.group_cancel"
            />
            <ConfirmActionDialog
                v-model:open="deleteArchivedOnlyDialogOpen"
                :form="plainForm"
                destructive
                :title="t.categories.group_delete_title"
                :description="groupDeleteDescription(0, 2, deleteCopy)"
                :confirm-label="t.categories.group_delete"
                :cancel-label="t.categories.group_cancel"
            />
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
