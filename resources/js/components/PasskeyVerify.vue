<script setup lang="ts">
import type { UrlMethodPair } from '@inertiajs/core';
import { router } from '@inertiajs/vue3';
import { usePasskeyVerify } from '@laravel/passkeys/vue';
import { KeyRound } from '@lucide/vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { dashboard } from '@/routes';

type Props = {
    routes?: {
        options: UrlMethodPair;
        submit: UrlMethodPair;
    };
    label: string;
    loadingLabel: string;
    separator: string;
};

const props = defineProps<Props>();

const { verify, isLoading, error, isSupported } = usePasskeyVerify({
    ...(props.routes
        ? {
              routes: {
                  options: props.routes.options.url,
                  submit: props.routes.submit.url,
              },
          }
        : {}),
    onSuccess: (response) => {
        router.visit(response.redirect ?? dashboard().url);
    },
});
</script>

<template>
    <div v-if="isSupported" class="flex flex-col gap-4">
        <div class="grid gap-2">
            <Button
                type="button"
                variant="outline"
                class="auth-submit"
                @click="verify"
                :disabled="isLoading"
            >
                <Spinner v-if="isLoading" />
                <KeyRound v-else class="size-4" />
                {{ isLoading ? props.loadingLabel : props.label }}
            </Button>

            <InputError v-if="error" :message="error" class="text-center" />
        </div>

        <div class="auth-or">{{ props.separator }}</div>
    </div>
</template>
