<?php

namespace App\Http\Controllers;

use App\Actions\ManageCapitalMovement;
use App\Http\Requests\StoreCapitalMovementRequest;
use App\Http\Requests\UpdateCapitalMovementRequest;
use App\Models\CapitalMovement;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CapitalMovementController extends Controller
{
    use AuthorizesRequests;

    public function store(StoreCapitalMovementRequest $request, ManageCapitalMovement $manageCapitalMovement): RedirectResponse
    {
        $manageCapitalMovement->create($request->user(), $request->payload());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Capital movement recorded.'),
        ]);

        return to_route('operations');
    }

    public function update(
        UpdateCapitalMovementRequest $request,
        CapitalMovement $capitalMovement,
        ManageCapitalMovement $manageCapitalMovement,
    ): RedirectResponse {
        $manageCapitalMovement->update($capitalMovement, $request->payload());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Capital movement updated.'),
        ]);

        return to_route('operations');
    }

    public function destroy(Request $request, CapitalMovement $capitalMovement, ManageCapitalMovement $manageCapitalMovement): RedirectResponse
    {
        $this->authorize('delete', $capitalMovement);

        $manageCapitalMovement->delete($capitalMovement);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Capital movement deleted.'),
        ]);

        return to_route('operations');
    }
}
