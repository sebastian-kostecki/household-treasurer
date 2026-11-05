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
const transferReasonId = `${idPrefix}-transfer-reason`;
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
                    class="items-start data-[disabled]:opacity-100"
                    :disabled="!availability.transfer.enabled"
                    :aria-describedby="
                        transferReason === null ? undefined : transferReasonId
                    "
                    @select="emit('add-transfer')"
                >
                    <ArrowLeftRight class="mt-0.5 size-4" aria-hidden="true" />
                    <span class="flex min-w-0 flex-col">
                        <span
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
                </DropdownMenuItem>
                <DropdownMenuItem
                    class="items-start data-[disabled]:opacity-100"
                    :disabled="!availability.capitalMovement.enabled"
                    :aria-describedby="
                        capitalMovementReason === null
                            ? undefined
                            : capitalMovementReasonId
                    "
                    @select="emit('add-capital-movement')"
                >
                    <Landmark class="mt-0.5 size-4" aria-hidden="true" />
                    <span class="flex min-w-0 flex-col">
                        <span
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
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>
    </div>
</template>
