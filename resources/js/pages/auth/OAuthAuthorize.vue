<script setup lang="ts">
import { Head, setLayoutProps } from '@inertiajs/vue3';
import { ShieldCheck } from '@lucide/vue';
import { approve } from '@/routes/passport/authorizations';
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/composables/useTranslations';

const props = defineProps<{
    client_name: string;
    scopes: string[];
    auth_token: string;
    client_id: string;
    state: string | null;
    csrf_token: string;
}>();

const t = useTranslations();
const action = approve().url;

setLayoutProps({
    title: t.auth.oauth_authorize.title,
    description: t.auth.oauth_authorize.description.replace(
        ':client',
        props.client_name,
    ),
    icon: ShieldCheck,
});
</script>

<template>
    <Head :title="t.auth.oauth_authorize.title" />

    <div class="flex flex-col gap-4">
        <div class="flex flex-col gap-2">
            <p class="text-muted-foreground text-sm">
                {{ t.auth.oauth_authorize.intro }}
            </p>
            <ul class="flex list-disc flex-col gap-1 pl-5 text-sm">
                <li v-for="scope in scopes" :key="scope">{{ scope }}</li>
            </ul>
        </div>

        <p class="text-muted-foreground text-sm">
            {{ t.auth.oauth_authorize.outro }}
        </p>

        <form method="post" :action="action" class="flex flex-col gap-2">
            <input type="hidden" name="_token" :value="csrf_token" />
            <input type="hidden" name="state" :value="state ?? ''" />
            <input type="hidden" name="client_id" :value="client_id" />
            <input type="hidden" name="auth_token" :value="auth_token" />
            <Button
                type="submit"
                class="auth-submit"
                data-test="oauth-approve-button"
            >
                {{ t.auth.oauth_authorize.allow }}
            </Button>
        </form>

        <form method="post" :action="action">
            <input type="hidden" name="_method" value="DELETE" />
            <input type="hidden" name="_token" :value="csrf_token" />
            <input type="hidden" name="state" :value="state ?? ''" />
            <input type="hidden" name="client_id" :value="client_id" />
            <input type="hidden" name="auth_token" :value="auth_token" />
            <Button
                type="submit"
                variant="outline"
                class="auth-submit"
                data-test="oauth-deny-button"
            >
                {{ t.auth.oauth_authorize.deny }}
            </Button>
        </form>
    </div>
</template>
