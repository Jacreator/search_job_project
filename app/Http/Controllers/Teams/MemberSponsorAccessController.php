<?php

declare(strict_types=1);

namespace App\Http\Controllers\Teams;

use App\Http\Controllers\Controller;
use App\Http\Requests\Teams\UpdateMemberSponsorAccessRequest;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class MemberSponsorAccessController extends Controller
{
    /**
     * Turn sponsor editing on or off for the specified team member.
     *
     * The user parameter binds the {user} route segment to a model. The request reads it to find the membership.
     */
    public function update(UpdateMemberSponsorAccessRequest $request, Team $team, User $user): RedirectResponse
    {
        $canEdit = $request->boolean('can_edit_sponsors');

        $request->membership()->update(['can_edit_sponsors' => $canEdit]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $canEdit ? __('Member can now edit sponsors.') : __('Member can no longer edit sponsors.'),
        ]);

        return to_route('teams.edit', ['team' => $team->slug]);
    }
}
