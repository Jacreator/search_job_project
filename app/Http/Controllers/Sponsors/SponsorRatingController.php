<?php

declare(strict_types=1);

namespace App\Http\Controllers\Sponsors;

use App\Enums\SponsorStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Sponsors\SetSponsorRatingRequest;
use App\Models\Sponsor;
use App\Models\Team;
use App\Services\RatingChange;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class SponsorRatingController extends Controller
{
    /**
     * Set a missing or hand-set rating grade.
     */
    public function update(
        SetSponsorRatingRequest $request,
        Team $currentTeam,
        Sponsor $sponsor,
        RatingChange $ratingChange,
    ): RedirectResponse {
        $grade = $request->grade();
        $previousStatus = $sponsor->status;

        $ratingChange->apply($sponsor, $grade);
        $sponsor->rating_grade_manual = true;
        $sponsor->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => match (true) {
            $sponsor->status === $previousStatus => __('Rating set to :grade.', ['grade' => $grade]),
            $sponsor->status === SponsorStatus::Skipped => __('Rating set to B. The sponsor is now skipped.'),
            default => __('Rating set to A. The sponsor is pending again.'),
        }]);

        return redirect()->back(fallback: route('sponsors.index', ['current_team' => $currentTeam->slug]));
    }
}
