<?php

namespace App\Actions;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ResetUserData
{
    public function handle(User $user): void
    {
        DB::transaction(function () use ($user): void {
            $user->financialOperations()->delete();
            $user->ownTransfers()->delete();
            $user->capitalMovements()->delete();

            if (Schema::hasTable('cash_expectations')) {
                DB::table('cash_expectations')
                    ->where('user_id', $user->id)
                    ->delete();
            }

            $user->budgets()->delete();
            $user->categories()->delete();
            $user->termDeposits()->delete();
            $user->accounts()->delete();

            DB::table('sessions')
                ->where('user_id', $user->id)
                ->delete();

            $user->forceFill([
                'first_run_checklist_dismissed_at' => null,
            ])->save();
        });
    }
}
