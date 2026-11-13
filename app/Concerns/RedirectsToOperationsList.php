<?php

namespace App\Concerns;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

trait RedirectsToOperationsList
{
    /**
     * Returns to the Operations list the write was made from: the list state
     * travels under the `list` query key, never as top-level keys (they would
     * merge into the form input).
     */
    protected function redirectToOperationsList(Request $request): RedirectResponse
    {
        $list = $request->query('list');
        $kept = [];

        if (is_array($list)) {
            foreach (['q', 'year', 'category_id', 'account_id', 'term_deposit_id', 'date_from', 'date_to', 'section', 'operations_page', 'own_transfers_page', 'capital_movements_page'] as $key) {
                if (isset($list[$key]) && is_scalar($list[$key])) {
                    $kept[$key] = $list[$key];
                }
            }
        }

        return to_route('operations', $kept);
    }
}
