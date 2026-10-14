<?php

declare(strict_types=1);

namespace App\Http\Controllers\Teams;

use App\Http\Controllers\Controller;
use App\Http\Requests\Teams\UpdateLookupSharingRequest;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class LookupSharingController extends Controller
{
    /**
     * Turn sharing finished lookups with other teams on or off.
     */
    public function update(UpdateLookupSharingRequest $request, Team $team): RedirectResponse
    {
        $share = $request->boolean('share_lookups');

        $team->update(['share_lookups' => $share]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $share ? __('Lookups are now shared with other teams.') : __('Lookups are no longer shared.'),
        ]);

        return to_route('teams.edit', ['team' => $team->slug]);
    }
}
