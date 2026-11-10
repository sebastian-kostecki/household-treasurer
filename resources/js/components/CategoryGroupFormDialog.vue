<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import CategoryGroupController from '@/actions/App/Http/Controllers/CategoryGroupController';
import FormField from '@/components/FormField.vue';
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
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { useTranslations } from '@/composables/useTranslations';

type FormBinding = InstanceType<typeof Form>['$props'];

const props = defineProps<{
    open: boolean;
    mode: 'create' | 'rename';
    /** The type of a new group; a group keeps its type for good (ADR-0066). */
    type: 'income' | 'expense';
    group?: { id: number; name: string } | null;
    /** Overrides the Wayfinder binding; used only by the kitchen sink. */
    form?: Pick<FormBinding, 'action' | 'method'>;
}>();

const emit = defineEmits<{
    'update:open': [value: boolean];
}>();

const t = useTranslations();

const name = ref('');
const busy = ref(false);

const formBinding = computed(() => {
    if (props.form) {
        return props.form;
    }

    return props.mode === 'create'
        ? CategoryGroupController.store.form()
        : CategoryGroupController.update.form({
              categoryGroup: props.group?.id ?? 0,
          });
});

watch(
    () => [props.open, props.mode, props.group] as const,
    ([open]) => {
        if (open) {
            name.value =
                props.mode === 'rename' && props.group ? props.group.name : '';
        }
    },
    { immediate: true },
);

function onOpenChange(next: boolean): void {
    if (!next && busy.value) {
        return;
    }

    emit('update:open', next);
}

function closeDialog(): void {
    emit('update:open', false);
}
</script>

<template>
    <Dialog :open="open" @update:open="onOpenChange">
        <DialogContent :show-close-button="!busy">
            <Form
                v-bind="formBinding"
                class="grid gap-4"
                @start="busy = true"
                @finish="busy = false"
                @success="closeDialog"
                v-slot="{ errors, processing, clearErrors }"
            >
                <DialogHeader>
                    <DialogTitle>
                        {{
                            mode === 'create'
                                ? t.categories.group_create_title
                                : t.categories.group_rename_title
                        }}
                    </DialogTitle>
                    <DialogDescription>
                        {{
                            mode === 'create'
                                ? t.categories.group_create_description
                                : t.categories.group_rename_description
                        }}
                    </DialogDescription>
                </DialogHeader>

                <input
                    v-if="mode === 'create'"
                    type="hidden"
                    name="type"
                    :value="type"
                />

                <FormField
                    id="category-group-name"
                    :label="t.categories.group_name"
                    :error="errors.name"
                    v-slot="{ id, describedBy, invalid }"
                >
                    <Input
                        :id="id"
                        v-model="name"
                        name="name"
                        required
                        autocomplete="off"
                        :aria-describedby="describedBy"
                        :aria-invalid="invalid"
                    />
                </FormField>

                <DialogFooter class="gap-2">
                    <DialogClose as-child>
                        <Button
                            type="button"
                            variant="ghost"
                            :disabled="processing"
                            @click="
                                () => {
                                    clearErrors();
                                    closeDialog();
                                }
                            "
                        >
                            {{ t.categories.group_cancel }}
                        </Button>
                    </DialogClose>
                    <Button type="submit" :disabled="processing">
                        <Spinner v-if="processing" />
                        {{ t.categories.group_save }}
                    </Button>
                </DialogFooter>
            </Form>
        </DialogContent>
    </Dialog>
</template>
