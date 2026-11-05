<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    ArrowDownLeft,
    ArrowLeftRight,
    ArrowUpRight,
    ChevronDown,
    Landmark,
    Plus,
    Upload,
} from '@lucide/vue';
import { computed, useId } from 'vue';
import CsvImportController from '@/actions/App/Http/Controllers/CsvImportController';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useTranslations } from '@/composables/useTranslations';
import { operationsCreateAvailability } from '@/lib/operations-create-availability';

const { accountsCount, termDepositsCount } = defineProps<{
    accountsCount: number;
    termDepositsCount: number;
}>();

const emit = defineEmits<{
    'add-income': [];
    'add-expense': [];
    'add-transfer': [];
    'add-capital-movement': [];
}>();

const t = useTranslations();
const idPrefix = useId();
const transferLabelId = `${idPrefix}-transfer-label`;
const transferReasonId = `${idPrefix}-transfer-reason`;
const capitalMovementLabelId = `${idPrefix}-capital-movement-label`;
const capitalMovementReasonId = `${idPrefix}-capital-movement-reason`;

const availability = computed(() =>
    operationsCreateAvailability(accountsCount, termDepositsCount),
);

const transferReason = computed(() => {
    const key = availability.value.transfer.reasonKey;

    return key === null ? null : t.operations[key];
});

const capitalMovementReason = computed(() => {
    const key = availability.value.capitalMovement.reasonKey;

    return key === null ? null : t.operations[key];
});

/**
 * Unavailable items stay focusable (`aria-disabled`, not `disabled`), because
 * reka skips disabled items during keyboard navigation and the reason would
 * never be announced. Selecting one keeps the menu open and opens no dialog.
 */
function selectTransfer(event: Event): void {
    if (!availability.value.transfer.enabled) {
        event.preventDefault();

        return;
    }

    emit('add-transfer');
}

function selectCapitalMovement(event: Event): void {
    if (!availability.value.capitalMovement.enabled) {
        event.preventDefault();

        return;
    }

    emit('add-capital-movement');
}
</script>

<template>
    <div class="flex flex-wrap items-center gap-2">
        <Button variant="outline" as-child>
            <Link :href="CsvImportController.create.url()">
                <Upload class="size-4" aria-hidden="true" />
                {{ t.operations.import_csv }}
            </Link>
        </Button>

        <DropdownMenu>
            <DropdownMenuTrigger as-child>
                <Button type="button">
                    <Plus class="size-4" aria-hidden="true" />
                    {{ t.operations.add_operation }}
                    <ChevronDown class="size-4" aria-hidden="true" />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" class="w-72">
                <DropdownMenuItem @select="emit('add-income')">
                    <ArrowDownLeft class="size-4" aria-hidden="true" />
                    {{ t.operations.menu_income }}
                </DropdownMenuItem>
                <DropdownMenuItem @select="emit('add-expense')">
                    <ArrowUpRight class="size-4" aria-hidden="true" />
                    {{ t.operations.menu_expense }}
                </DropdownMenuItem>
                <DropdownMenuItem
                    as-child
                    class="items-start"
                    @select="selectTransfer"
                >
                    <div
                        :aria-disabled="
                            availability.transfer.enabled ? undefined : 'true'
                        "
                        :aria-labelledby="transferLabelId"
                        :aria-describedby="
                            transferReason === null
                                ? undefined
                                : transferReasonId
                        "
                    >
                        <ArrowLeftRight
                            class="mt-0.5 size-4"
                            aria-hidden="true"
                        />
                        <span class="flex min-w-0 flex-col">
                            <span
                                :id="transferLabelId"
                                :class="
                                    availability.transfer.enabled
                                        ? ''
                                        : 'text-muted-foreground'
                                "
                            >
                                {{ t.operations.menu_own_transfer }}
                            </span>
                            <span
                                v-if="transferReason !== null"
                                :id="transferReasonId"
                                class="meta text-muted-foreground"
                            >
                                {{ transferReason }}
                            </span>
                        </span>
                    </div>
                </DropdownMenuItem>
                <DropdownMenuItem
                    as-child
                    class="items-start"
                    @select="selectCapitalMovement"
                >
                    <div
                        :aria-disabled="
                            availability.capitalMovement.enabled
                                ? undefined
                                : 'true'
                        "
                        :aria-labelledby="capitalMovementLabelId"
                        :aria-describedby="
                            capitalMovementReason === null
                                ? undefined
                                : capitalMovementReasonId
                        "
                    >
                        <Landmark class="mt-0.5 size-4" aria-hidden="true" />
                        <span class="flex min-w-0 flex-col">
                            <span
                                :id="capitalMovementLabelId"
                                :class="
                                    availability.capitalMovement.enabled
                                        ? ''
                                        : 'text-muted-foreground'
                                "
                            >
                                {{ t.operations.menu_capital_movement }}
                            </span>
                            <span
                                v-if="capitalMovementReason !== null"
                                :id="capitalMovementReasonId"
                                class="meta text-muted-foreground"
                            >
                                {{ capitalMovementReason }}
                            </span>
                        </span>
                    </div>
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>
    </div>
</template>
