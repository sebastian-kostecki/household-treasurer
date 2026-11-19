<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

#[Signature('app:prune-oauth-clients {--days=7 : Delete clients registered more than this many days ago}')]
#[Description('Delete OAuth clients with no live access or refresh token (open registration of the MCP endpoint creates them)')]
class PruneOAuthClientsCommand extends Command
{
    public function handle(): int
    {
        $cutoff = now()->subDays(max(1, (int) $this->option('days')));

        $deleted = DB::transaction(function () use ($cutoff): int {
            $clientIds = DB::table('oauth_clients')
                ->where('created_at', '<', $cutoff)
                ->whereNotExists(fn (Builder $query) => $query->from('oauth_access_tokens')
                    ->whereColumn('oauth_access_tokens.client_id', 'oauth_clients.id')
                    ->where('oauth_access_tokens.revoked', false)
                    ->where('oauth_access_tokens.expires_at', '>', now()))
                ->whereNotExists(fn (Builder $query) => $query->from('oauth_refresh_tokens')
                    ->join('oauth_access_tokens', 'oauth_access_tokens.id', '=', 'oauth_refresh_tokens.access_token_id')
                    ->whereColumn('oauth_access_tokens.client_id', 'oauth_clients.id')
                    ->where('oauth_refresh_tokens.revoked', false)
                    ->where('oauth_refresh_tokens.expires_at', '>', now()))
                ->pluck('id')
                ->all();

            if ($clientIds === []) {
                return 0;
            }

            DB::table('oauth_refresh_tokens')
                ->whereIn('access_token_id', DB::table('oauth_access_tokens')->whereIn('client_id', $clientIds)->select('id'))
                ->delete();
            DB::table('oauth_access_tokens')->whereIn('client_id', $clientIds)->delete();
            DB::table('oauth_auth_codes')->whereIn('client_id', $clientIds)->delete();

            return DB::table('oauth_clients')->whereIn('id', $clientIds)->delete();
        });

        $this->components->info("Deleted {$deleted} unused OAuth client(s).");

        return self::SUCCESS;
    }
}
