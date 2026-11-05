<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';

type FormBinding = InstanceType<typeof Form>['$props'];

defineProps<{
    form: Pick<FormBinding, 'action' | 'method'>;
    title: string;
    description: string;
    confirmLabel: string;
    cancelLabel: string;
    destructive?: boolean;
}>();

// Without v-model:open the dialog keeps its own state and opens from the trigger slot.
const open = defineModel<boolean>('open', { default: false });
const busy = ref(false);

function onOpenChange(next: boolean): void {
    if (!next && busy.value) {
        return;
    }

    open.value = next;
}
</script>

<template>
    <Dialog :open="open" @update:open="onOpenChange">
        <slot name="trigger" />
        <DialogContent :show-close-button="!busy">
            <Form
                v-bind="form"
                class="grid gap-4"
                @start="busy = true"
                @finish="busy = false"
                @success="open = false"
                v-slot="{ processing, errors }"
            >
                <DialogHeader>
                    <DialogTitle>{{ title }}</DialogTitle>
                    <DialogDescription>{{ description }}</DialogDescription>
                </DialogHeader>
                <slot />
                <InputError
                    v-for="(message, key) in errors"
                    :key="key"
                    :message="message"
                />
                <DialogFooter class="gap-2">
                    <DialogClose as-child>
                        <Button
                            type="button"
                            variant="outline"
                            :disabled="processing"
                        >
                            {{ cancelLabel }}
                        </Button>
                    </DialogClose>
                    <Button
                        type="submit"
                        :variant="destructive ? 'destructive' : 'default'"
                        :disabled="processing"
                    >
                        <Spinner v-if="processing" />
                        {{ confirmLabel }}
                    </Button>
                </DialogFooter>
            </Form>
        </DialogContent>
    </Dialog>
</template>
