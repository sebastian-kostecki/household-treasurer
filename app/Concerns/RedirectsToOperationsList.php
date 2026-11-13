<?php

namespace App\Concerns;

use App\Http\Requests\IndexOperationsRequest;
use App\Support\OperationsListQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;

trait RedirectsToOperationsList
{
    /**
     * Returns to the Operations list the write was made from: the list state
     * travels under the `list` query key, never as top-level keys (they would
     * merge into the form input). A value the list itself would reject (a
     * Category merged away in another tab, a made-up section) is dropped, so
     * the redirect cannot bounce on the list's own validation.
     */
    protected function redirectToOperationsList(Request $request): RedirectResponse
    {
        $list = $request->query('list');
        $kept = [];

        if (is_array($list)) {
            foreach ([...OperationsListQuery::FILTER_KEYS, 'section', ...OperationsListQuery::PAGE_KEYS] as $key) {
                if (isset($list[$key]) && is_scalar($list[$key])) {
                    $kept[$key] = $list[$key];
                }
            }
        }

        $rejected = Validator::make($kept, IndexOperationsRequest::listRules($request->user()?->id))
            ->errors()
            ->keys();

        return to_route('operations', Arr::except($kept, $rejected));
    }
}
