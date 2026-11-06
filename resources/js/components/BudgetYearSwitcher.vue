<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { CalendarDays, CalendarPlus, ChevronsUpDown } from '@lucide/vue';
import { nextTick, onUnmounted, ref } from 'vue';
import BudgetController from '@/actions/App/Http/Controllers/BudgetController';
import CreateBudgetDialog from '@/components/CreateBudgetDialog.vue';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuRadioGroup,
    DropdownMenuRadioItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    useSidebar,
} from '@/components/ui/sidebar';
import { useTranslations } from '@/composables/useTranslations';
import {
    listenForBudgetYearSelectionFailure,
    notifyBudgetYearSelectionFailed,
} from '@/lib/budget-year-selection';

type Props = {
    budgetYears: number[];
    budgetYear: number | null;
};

const props = defineProps<Props>();

const t = useTranslations();
const { isMobile, state } = useSidebar();
const createDialogOpen = ref(false);
const pending = ref(false);

defineExpose({ pending });

const stopListeningForYearSelectionFailure =
    listenForBudgetYearSelectionFailure(
        () => pending.value,
        t.shell.select_year_failed,
    );

onUnmounted(stopListeningForYearSelectionFailure);

function openCreateDialog(): void {
    void nextTick(() => {
        createDialogOpen.value = true;
    });
}

function selectYear(value: unknown): void {
    const year = Number(value);

    if (Number.isNaN(year) || year === props.budgetYear || pending.value) {
        return;
    }

    router.post(
        BudgetController.selectYear().url,
        { year },
        {
            onStart: () => {
                pending.value = true;
            },
            onError: () => {
                notifyBudgetYearSelectionFailed(t.shell.select_year_failed);
            },
            onFinish: () => {
                pending.value = false;
            },
        },
    );
}
</script>

<template>
    <SidebarMenu>
        <SidebarMenuItem v-if="budgetYears.length === 0">
            <SidebarMenuButton
                size="lg"
                :tooltip="t.shell.add_budget_year"
                @click="createDialogOpen = true"
            >
                <div
                    class="bg-sidebar-accent text-sidebar-accent-foreground flex size-8 shrink-0 items-center justify-center rounded-md"
                >
                    <CalendarPlus class="size-4" />
                </div>
                <div class="grid flex-1 text-left text-sm leading-tight">
                    <span class="meta text-sidebar-foreground truncate">
                        {{ t.shell.no_budget_year }}
                    </span>
                    <span class="text-sidebar-strong truncate font-semibold">
                        {{ t.shell.add_budget_year }}
                    </span>
                </div>
            </SidebarMenuButton>
        </SidebarMenuItem>
        <SidebarMenuItem v-else>
            <DropdownMenu>
                <DropdownMenuTrigger as-child>
                    <SidebarMenuButton
                        size="lg"
                        class="data-[state=open]:bg-sidebar-accent data-[state=open]:text-sidebar-accent-foreground"
                        :tooltip="`${t.shell.budget_year}: ${budgetYear}`"
                        :aria-label="`${t.shell.budget_year}: ${budgetYear}`"
                        :aria-disabled="pending"
                        :aria-busy="pending"
                    >
                        <div
                            class="bg-sidebar-accent text-sidebar-accent-foreground flex size-8 shrink-0 items-center justify-center rounded-md"
                        >
                            <CalendarDays class="size-4" />
                        </div>
                        <div
                            class="grid flex-1 text-left text-sm leading-tight"
                        >
                            <span
                                class="text-sidebar-strong truncate font-semibold tabular-nums"
                            >
                                {{ budgetYear }}
                            </span>
                        </div>
                        <ChevronsUpDown class="ml-auto size-4" />
                    </SidebarMenuButton>
                </DropdownMenuTrigger>
                <DropdownMenuContent
                    class="w-(--reka-dropdown-menu-trigger-width) min-w-56 rounded-lg"
                    :side="
                        !isMobile && state === 'collapsed' ? 'right' : 'bottom'
                    "
                    align="start"
                    :side-offset="4"
                >
                    <DropdownMenuLabel>
                        {{ t.shell.select_year }}
                    </DropdownMenuLabel>
                    <DropdownMenuRadioGroup
                        :model-value="String(budgetYear ?? '')"
                        @update:model-value="selectYear"
                    >
                        <DropdownMenuRadioItem
                            v-for="year in budgetYears"
                            :key="year"
                            :value="String(year)"
                            class="tabular-nums"
                        >
                            {{ year }}
                        </DropdownMenuRadioItem>
                    </DropdownMenuRadioGroup>
                    <DropdownMenuSeparator />
                    <DropdownMenuItem @select="openCreateDialog">
                        <CalendarPlus />
                        {{ t.shell.add_budget_year }}
                    </DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>
        </SidebarMenuItem>
        <CreateBudgetDialog
            v-model:open="createDialogOpen"
            :year-input-id="
                budgetYears.length === 0
                    ? 'shell-create-budget-year-empty'
                    : 'shell-create-budget-year'
            "
        />
    </SidebarMenu>
</template>
