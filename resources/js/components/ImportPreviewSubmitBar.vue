<script setup lang="ts">
import { Check } from '@lucide/vue';
import { useId } from 'vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { useTranslations } from '@/composables/useTranslations';

const { reason, processing } = defineProps<{
    reason: string | null;
    processing: boolean;
}>();

const t = useTranslations();
const reasonId = `${useId()}-reason`;
</script>

<template>
    <div class="flex flex-col items-end gap-2">
        <Button
            type="submit"
            :disabled="processing || reason !== null"
            :aria-describedby="reason === null ? undefined : reasonId"
        >
            <Spinner v-if="processing" :aria-label="t.common.loading" />
            <Check v-else class="size-4" aria-hidden="true" />
            {{ t.operations.import_confirm }}
        </Button>
        <p
            v-if="reason !== null"
            :id="reasonId"
            class="meta text-muted-foreground"
        >
            {{ reason }}
        </p>
    </div>
</template>
