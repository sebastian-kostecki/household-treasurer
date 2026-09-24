<?php

namespace App\Http\Controllers;

use App\Actions\ManageOwnTransfer;
use App\Actions\RebuildOperation;
use App\Http\Requests\StoreOwnTransferRequest;
use App\Http\Requests\UpdateOwnTransferRequest;
use App\Models\OwnTransfer;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class OwnTransferController extends Controller
{
    use AuthorizesRequests;

    public function store(StoreOwnTransferRequest $request, ManageOwnTransfer $manageOwnTransfer): RedirectResponse
    {
        $manageOwnTransfer->create($request->user(), $request->payload());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Own transfer recorded.'),
        ]);

        return to_route('operations');
    }

    public function update(
        UpdateOwnTransferRequest $request,
        OwnTransfer $ownTransfer,
        ManageOwnTransfer $manageOwnTransfer,
        RebuildOperation $rebuildOperation,
    ): RedirectResponse {
        if ($request->boolean('convert_to_capital_movement')) {
            $rebuildOperation->ownTransferToCapitalMovement(
                $ownTransfer,
                $request->capitalMovementConversionPayload('transfer_date'),
            );

            Inertia::flash('toast', [
                'type' => 'success',
                'message' => __('Capital movement recorded.'),
            ]);

            return to_route('operations');
        }

        $manageOwnTransfer->update($ownTransfer, $request->ownTransferPayload());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Own transfer updated.'),
        ]);

        return to_route('operations');
    }

    public function destroy(Request $request, OwnTransfer $ownTransfer, ManageOwnTransfer $manageOwnTransfer): RedirectResponse
    {
        $this->authorize('delete', $ownTransfer);

        $manageOwnTransfer->delete($ownTransfer);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Own transfer deleted.'),
        ]);

        return to_route('operations');
    }
}
