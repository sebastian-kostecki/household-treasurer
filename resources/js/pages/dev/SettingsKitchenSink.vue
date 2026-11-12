<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { defineAsyncComponent, ref } from 'vue';
import DialogsKitchenSinkController from '@/actions/App/Http/Controllers/Dev/DialogsKitchenSinkController';
import DeleteUser from '@/components/DeleteUser.vue';
import FormField from '@/components/FormField.vue';
import SettingsPanel from '@/components/SettingsPanel.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { useTranslations } from '@/composables/useTranslations';
import SettingsLayout from '@/layouts/settings/Layout.vue';

const Profile = defineAsyncComponent(
    () => import('@/pages/settings/Profile.vue'),
);
const Security = defineAsyncComponent(
    () => import('@/pages/settings/Security.vue'),
);
const Appearance = defineAsyncComponent(
    () => import('@/pages/settings/Appearance.vue'),
);

const MonthPlanSettings = defineAsyncComponent(
    () => import('@/pages/settings/MonthPlan.vue'),
);

const t = useTranslations();

const passwordRules = 'minlength: 8;';

const dialogStates = ['delete-account-saving', 'delete-account-error'] as const;
type DialogState = (typeof dialogStates)[number];

const savingForm = DialogsKitchenSinkController.validate.form();
const errorForm = DialogsKitchenSinkController.validate.form();

function initialState(): DialogState | null {
    if (typeof window === 'undefined') {
        return null;
    }

    const requested = new URLSearchParams(window.location.search).get('open');

    return dialogStates.find((state) => state === requested) ?? null;
}

const openState = ref<DialogState | null>(initialState());

function isOpen(state: DialogState): boolean {
    return openState.value === state;
}

function setOpen(state: DialogState, value: boolean): void {
    openState.value = value ? state : null;
}

const stateMatrix = [
    {
        state: 'default',
        where: 'Sekcje poniżej: Profil, Bezpieczeństwo (bez panelu 2FA i kluczy dostępu, potem z nimi), Wygląd i Plan miesiąca (pusty i 10 %), osadzone w układzie ustawień.',
    },
    {
        state: 'hover',
        where: 'Zrzuty z działającej aplikacji (stan zależny od interakcji): linki podnawigacji, SegmentedControl motywu, przyciski stopek i przycisk „Usuń konto”.',
    },
    {
        state: 'focus-visible',
        where: 'Zrzuty po Tab z działającej aplikacji: linki podnawigacji, pola, SegmentedControl, „Zapisz” oraz przycisk „Usuń konto” z konturem outline-destructive.',
    },
    {
        state: 'disabled',
        where: 'Sekcja „Zapis w toku” poniżej oraz /dev/ui/settings?open=delete-account-saving po potwierdzeniu: spinner, ukryty X i zablokowane zamykanie.',
    },
    {
        state: 'error',
        where: 'Sekcja „Błąd pola” poniżej (FormField z błędem) oraz /dev/ui/settings?open=delete-account-error po potwierdzeniu (błąd walidacji w oknie usuwania).',
    },
    {
        state: 'empty',
        where: 'N/A: ustawienia zawsze mają dane zalogowanego użytkownika.',
    },
    {
        state: 'loading',
        where: 'Przycisk „Zapisz” ze spinnerem w sekcji „Zapis w toku” oraz spinner w oknie usuwania konta.',
    },
];
</script>

<template>
    <Head title="Settings kitchen sink" />

    <SettingsLayout>
        <div class="flex flex-col gap-8">
            <header class="space-y-1">
                <h2 class="page-title">Settings kitchen sink</h2>
                <p class="text-muted-foreground text-sm">
                    Strona deweloperska (tylko środowisko local). Ustawienia w
                    macierzy stanów. Żaden link podnawigacji nie jest aktywny,
                    bo adres to /dev/ui/settings; aktywną pozycję pokazują
                    zrzuty prawdziwych tras. Zapisy formularzy trafiają na
                    zwykłe trasy ustawień, a okna usuwania na endpoint walidacji
                    sinka. Duplikaty id pól są tu dopuszczalne.
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

            <section class="space-y-3" data-section="profile">
                <h2
                    class="text-muted-foreground border-b pb-1 font-mono text-sm font-semibold"
                >
                    default: Profil
                </h2>
                <Profile />
            </section>

            <section class="space-y-3" data-section="security-plain">
                <h2
                    class="text-muted-foreground border-b pb-1 font-mono text-sm font-semibold"
                >
                    default: Bezpieczeństwo bez 2FA i kluczy dostępu
                </h2>
                <Security
                    :password-rules="passwordRules"
                    :can-manage-two-factor="false"
                    :can-manage-passkeys="false"
                />
            </section>

            <section class="space-y-3" data-section="security-full">
                <h2
                    class="text-muted-foreground border-b pb-1 font-mono text-sm font-semibold"
                >
                    default: Bezpieczeństwo z 2FA i kluczami dostępu
                </h2>
                <Security
                    :password-rules="passwordRules"
                    :can-manage-two-factor="true"
                    :requires-confirmation="true"
                    :two-factor-enabled="false"
                    :can-manage-passkeys="true"
                    :passkeys="[]"
                />
            </section>

            <section class="space-y-3" data-section="appearance">
                <h2
                    class="text-muted-foreground border-b pb-1 font-mono text-sm font-semibold"
                >
                    default: Wygląd
                </h2>
                <Appearance />
            </section>

            <section class="space-y-3" data-section="month-plan">
                <h2
                    class="text-muted-foreground border-b pb-1 font-mono text-sm font-semibold"
                >
                    default, empty: Plan miesiąca bez procentu
                </h2>
                <MonthPlanSettings :default-set-aside-percent="null" />
            </section>

            <section class="space-y-3" data-section="month-plan-set">
                <h2
                    class="text-muted-foreground border-b pb-1 font-mono text-sm font-semibold"
                >
                    default: Plan miesiąca z procentem 10
                </h2>
                <MonthPlanSettings :default-set-aside-percent="10" />
            </section>

            <section class="space-y-3" data-section="month-plan-error">
                <h2
                    class="text-muted-foreground border-b pb-1 font-mono text-sm font-semibold"
                >
                    error: Plan miesiąca, błąd pola
                </h2>
                <SettingsPanel
                    :title="t.settings.month_plan"
                    :description="t.settings.month_plan_description"
                >
                    <FormField
                        id="sink-error-percent"
                        :label="t.settings.default_set_aside_percent"
                        :hint="t.settings.default_set_aside_percent_help"
                        error="Wartość musi być między 0 a 100."
                        unit="%"
                        v-slot="{ id, describedBy, invalid, controlClass }"
                    >
                        <Input
                            :id="id"
                            :class="controlClass"
                            :aria-describedby="describedBy"
                            :aria-invalid="invalid"
                            type="number"
                            name="default_set_aside_percent"
                            default-value="101"
                        />
                    </FormField>
                    <template #footer>
                        <Button type="button">{{ t.settings.save }}</Button>
                    </template>
                </SettingsPanel>
            </section>

            <section class="space-y-3" data-section="month-plan-saving">
                <h2
                    class="text-muted-foreground border-b pb-1 font-mono text-sm font-semibold"
                >
                    disabled, loading: Plan miesiąca, zapis w toku
                </h2>
                <SettingsPanel
                    :title="t.settings.month_plan"
                    :description="t.settings.month_plan_description"
                >
                    <FormField
                        id="sink-saving-percent"
                        :label="t.settings.default_set_aside_percent"
                        :hint="t.settings.default_set_aside_percent_help"
                        unit="%"
                        v-slot="{ id, describedBy, invalid, controlClass }"
                    >
                        <Input
                            :id="id"
                            :class="controlClass"
                            :aria-describedby="describedBy"
                            :aria-invalid="invalid"
                            type="number"
                            name="default_set_aside_percent"
                            default-value="10"
                            disabled
                        />
                    </FormField>
                    <template #footer>
                        <Button type="button" disabled>
                            <Spinner />
                            {{ t.settings.save }}
                        </Button>
                    </template>
                </SettingsPanel>
            </section>

            <section class="space-y-3" data-section="field-error">
                <h2
                    class="text-muted-foreground border-b pb-1 font-mono text-sm font-semibold"
                >
                    error: Błąd pola
                </h2>
                <SettingsPanel
                    :title="t.settings.profile_heading"
                    :description="t.settings.profile_description"
                >
                    <FormField
                        id="sink-error-email"
                        :label="t.settings.email"
                        error="Podany adres e-mail jest już zajęty."
                        v-slot="{ id, describedBy, invalid, controlClass }"
                    >
                        <Input
                            :id="id"
                            :class="controlClass"
                            :aria-describedby="describedBy"
                            :aria-invalid="invalid"
                            name="email"
                            default-value="test@example.com"
                        />
                    </FormField>
                    <template #footer>
                        <Button type="button">{{ t.settings.save }}</Button>
                    </template>
                </SettingsPanel>
            </section>

            <section class="space-y-3" data-section="saving">
                <h2
                    class="text-muted-foreground border-b pb-1 font-mono text-sm font-semibold"
                >
                    disabled, loading: Zapis w toku
                </h2>
                <SettingsPanel
                    :title="t.settings.profile_heading"
                    :description="t.settings.profile_description"
                >
                    <FormField
                        id="sink-saving-name"
                        :label="t.settings.name"
                        v-slot="{ id, describedBy, invalid, controlClass }"
                    >
                        <Input
                            :id="id"
                            :class="controlClass"
                            :aria-describedby="describedBy"
                            :aria-invalid="invalid"
                            name="name"
                            default-value="Test User"
                            disabled
                        />
                    </FormField>
                    <template #footer>
                        <Button type="button" disabled>
                            <Spinner />
                            {{ t.settings.save }}
                        </Button>
                    </template>
                </SettingsPanel>
            </section>

            <section class="space-y-3" data-section="delete-saving">
                <h2
                    class="text-muted-foreground border-b pb-1 font-mono text-sm font-semibold"
                >
                    disabled: Usuwanie konta, zapis w toku
                </h2>
                <DeleteUser
                    :open="isOpen('delete-account-saving')"
                    :form="savingForm"
                    @update:open="setOpen('delete-account-saving', $event)"
                />
            </section>

            <section class="space-y-3" data-section="delete-error">
                <h2
                    class="text-muted-foreground border-b pb-1 font-mono text-sm font-semibold"
                >
                    error: Usuwanie konta po walidacji
                </h2>
                <DeleteUser
                    :open="isOpen('delete-account-error')"
                    :form="errorForm"
                    @update:open="setOpen('delete-account-error', $event)"
                />
            </section>
        </div>
    </SettingsLayout>
</template>
