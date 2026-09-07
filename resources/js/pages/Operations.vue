<script setup lang="ts">
import { Head, setLayoutProps } from '@inertiajs/vue3';
import { Plus, Upload } from '@lucide/vue';
import { ref } from 'vue';
import IncomeFormDialog, {
    type IncomeFormAccount,
    type IncomeFormCategory,
} from '@/components/IncomeFormDialog.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { useTranslations } from '@/composables/useTranslations';
import { formatDate, formatMoney } from '@/lib/format';
import { operations as operationsRoute } from '@/routes';

type OperationRow = {
    id: number;
    operation_date: string;
    description: string;
    type: 'income';
    amount_grosze: number;
    account_name: string;
    category_name: string;
};

const {
    operations: operationRows,
    accounts,
    incomeCategories,
} = defineProps<{
    operations: OperationRow[];
    accounts: IncomeFormAccount[];
    incomeCategories: IncomeFormCategory[];
}>();
const t = useTranslations();
const incomeDialogOpen = ref(false);

setLayoutProps({
    breadcrumbs: [
        {
            title: t.nav.operations,
            href: operationsRoute(),
        },
    ],
});

function operationTypeLabel(type: OperationRow['type']): string {
    return type === 'income' ? t.operations.type_income : type;
}
</script>

<template>
    <Head :title="t.operations.title" />

    <div class="flex flex-1 flex-col gap-4 p-4 md:gap-5">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-lg font-semibold">{{ t.operations.title }}</h1>
            <div class="flex flex-wrap gap-2">
                <Button @click="incomeDialogOpen = true">
                    <Plus class="mr-2 size-4" />
                    {{ t.operations.add_income }}
                </Button>
                <Button disabled :title="t.operations.import_hint">
                    <Upload class="mr-2 size-4" />
                    {{ t.operations.import_csv }}
                </Button>
            </div>
        </div>

        <Card class="hidden md:block">
            <CardHeader>
                <CardTitle class="text-base">
                    {{ t.operations.title }}
                </CardTitle>
                <CardDescription>
                    {{
                        operationRows.length === 0
                            ? t.operations.empty
                            : t.operations.list_description
                    }}
                </CardDescription>
            </CardHeader>
            <CardContent class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b text-left">
                            <th class="pb-2 font-medium">
                                {{ t.operations.date }}
                            </th>
                            <th class="pb-2 font-medium">
                                {{ t.operations.description }}
                            </th>
                            <th class="pb-2 font-medium">
                                {{ t.operations.type }}
                            </th>
                            <th class="pb-2 font-medium">
                                {{ t.operations.account }}
                            </th>
                            <th class="pb-2 text-right font-medium">
                                {{ t.operations.amount }}
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="operationRows.length === 0">
                            <td
                                colspan="5"
                                class="text-muted-foreground py-6 text-center"
                            >
                                {{ t.operations.empty }}
                            </td>
                        </tr>
                        <tr
                            v-for="operation in operationRows"
                            :key="operation.id"
                            class="border-b"
                        >
                            <td class="py-3">
                                {{ formatDate(operation.operation_date) }}
                            </td>
                            <td class="py-3">{{ operation.description }}</td>
                            <td class="py-3">
                                <Badge variant="secondary">
                                    {{ operationTypeLabel(operation.type) }}
                                </Badge>
                            </td>
                            <td class="py-3">{{ operation.account_name }}</td>
                            <td class="py-3 text-right text-success">
                                {{ formatMoney(operation.amount_grosze) }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </CardContent>
        </Card>

        <div class="space-y-3 md:hidden">
            <Card v-if="operationRows.length === 0">
                <CardHeader class="pb-2">
                    <CardDescription>
                        {{ t.operations.empty }}
                    </CardDescription>
                </CardHeader>
            </Card>
            <Card
                v-for="operation in operationRows"
                :key="operation.id"
            >
                <CardHeader class="pb-2">
                    <CardTitle class="text-base">
                        {{ operation.description }}
                    </CardTitle>
                    <CardDescription>
                        {{ formatDate(operation.operation_date) }}
                        · {{ operation.account_name }}
                    </CardDescription>
                </CardHeader>
                <CardContent class="flex items-center justify-between">
                    <Badge variant="secondary">
                        {{ operationTypeLabel(operation.type) }}
                    </Badge>
                    <span class="text-success font-medium">
                        {{ formatMoney(operation.amount_grosze) }}
                    </span>
                </CardContent>
            </Card>
        </div>
    </div>

    <IncomeFormDialog
        v-model:open="incomeDialogOpen"
        :accounts="accounts"
        :income-categories="incomeCategories"
    />
</template>
