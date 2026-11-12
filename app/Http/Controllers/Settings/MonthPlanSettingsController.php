<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\MonthPlanSettingsUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MonthPlanSettingsController extends Controller
{
    /**
     * Show the Month Plan settings page.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('settings/MonthPlan', [
            'defaultSetAsidePercent' => $request->user()->default_set_aside_percent,
        ]);
    }

    /**
     * Store the Default Set-aside Percent (empty means none).
     */
    public function update(MonthPlanSettingsUpdateRequest $request): RedirectResponse
    {
        $percent = $request->validated('default_set_aside_percent');

        $request->user()->forceFill([
            'default_set_aside_percent' => $percent === null ? null : (int) $percent,
        ])->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Month plan settings updated.')]);

        return to_route('month-plan-settings.edit');
    }
}
