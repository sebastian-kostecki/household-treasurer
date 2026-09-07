<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\OwnTransfer;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OperationsController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        $ownTransfers = $user->ownTransfers()
            ->with(['sourceAccount:id,name', 'destinationAccount:id,name'])
            ->orderByDesc('transfer_date')
            ->orderByDesc('id')
            ->get()
            ->map(fn (OwnTransfer $transfer): array => [
                'id' => $transfer->id,
                'transfer_date' => $transfer->transfer_date->toDateString(),
                'amount_grosze' => $transfer->amount_grosze,
                'description' => $transfer->description,
                'source_account_id' => $transfer->source_account_id,
                'destination_account_id' => $transfer->destination_account_id,
                'source_account_name' => $transfer->sourceAccount->name,
                'destination_account_name' => $transfer->destinationAccount->name,
            ]);

        $accounts = $user->accounts()
            ->orderBy('name')
            ->get()
            ->map(fn (Account $account): array => [
                'id' => $account->id,
                'name' => $account->name,
                'type' => $account->type->value,
            ]);

        return Inertia::render('Operations', [
            'ownTransfers' => $ownTransfers,
            'accounts' => $accounts,
        ]);
    }
}
