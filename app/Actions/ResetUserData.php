<?php

namespace App\Actions;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class ResetUserData
{
    public function handle(User $user): void
    {
        DB::transaction(function () use ($user): void {
            $user->financialOperations()->delete();
            $user->ownTransfers()->delete();
            $user->budgets()->delete();
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
