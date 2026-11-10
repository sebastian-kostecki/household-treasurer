<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Check } from '@lucide/vue';
import { toast } from 'vue-sonner';
import { assign as assignGroup } from '@/actions/App/Http/Controllers/CategoryGroupController';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useTranslations } from '@/composables/useTranslations';
import type { CategoryGroupItem, CategoryListItem } from '@/types/categories';

const props = defineProps<{
    category: CategoryListItem;
    /** The groups of the Category's type. */
    groups: CategoryGroupItem[];
    /** Only the kitchen sink opens the menu on mount. */
    defaultOpen?: boolean;
}>();

const t = useTranslations();

function assign(groupId: number | null): void {
    if (groupId === props.category.group_id) {
        return;
    }

    router.patch(
        assignGroup.url({ category: props.category.id }),
        { category_group_id: groupId },
        {
            preserveScroll: true,
            onError: (errors) => {
                toast.error(
                    Object.values(errors)[0] ??
                        t.categories.group_assign_failed,
                );
            },
        },
    );
}
</script>

<template>
    <DropdownMenu :default-open="defaultOpen">
        <DropdownMenuTrigger as-child>
            <Button
                type="button"
                variant="ghost"
                size="sm"
                :aria-label="
                    t.categories.group_picker_label.replace(
                        ':name',
                        category.name,
                    )
                "
            >
                {{ t.categories.group_picker }}
            </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end">
            <DropdownMenuItem @select="assign(null)">
                <Check
                    class="size-4"
                    :class="{ invisible: category.group_id !== null }"
                    aria-hidden="true"
                />
                {{ t.categories.group_none }}
            </DropdownMenuItem>
            <DropdownMenuSeparator v-if="groups.length > 0" />
            <DropdownMenuItem
                v-for="group in groups"
                :key="group.id"
                @select="assign(group.id)"
            >
                <Check
                    class="size-4"
                    :class="{ invisible: category.group_id !== group.id }"
                    aria-hidden="true"
                />
                {{ group.name }}
            </DropdownMenuItem>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
