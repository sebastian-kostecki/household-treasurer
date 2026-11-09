<?php

namespace App\Http\Controllers;

use App\Support\CommandPaletteCategories;
use App\Support\SelectedBudgetYear;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CommandPaletteCategoryController extends Controller
{
    /**
     * Categories offered by the command palette for the budget year chosen in
     * the sidebar; read on every open of the palette, not shared on each page.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $year = SelectedBudgetYear::resolve($request);

        return response()->json([
            'budget_year' => $year,
            'categories' => CommandPaletteCategories::forYear($request->user(), $year),
        ]);
    }
}
