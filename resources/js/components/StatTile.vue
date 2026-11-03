<script setup lang="ts">
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { useId } from 'vue';
import { amountToneClass } from '@/lib/amount-tone';
import { formatMoney } from '@/lib/format';

defineProps<{
    label: string;
    amountMinorUnits: number;
}>();

const labelId = useId();
</script>

<template>
    <Card role="group" :aria-labelledby="labelId">
        <CardHeader class="pb-2">
            <CardDescription :id="labelId" class="flex items-center gap-2">
                {{ label }}
                <slot name="label-extra" />
            </CardDescription>
            <CardTitle
                class="text-3xl tabular-nums"
                :class="amountToneClass(amountMinorUnits)"
            >
                {{ formatMoney(amountMinorUnits) }}
            </CardTitle>
        </CardHeader>
        <CardContent v-if="$slots.footer" class="pt-0">
            <slot name="footer" />
        </CardContent>
    </Card>
</template>
