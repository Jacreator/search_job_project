<?php

declare(strict_types=1);

namespace App\Http\Controllers\Sponsors;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sponsors\ListSponsorsRequest;
use App\Http\Requests\Sponsors\UpdateSponsorRequest;
use App\Models\Sponsor;
use App\Models\Team;
use App\Services\SponsorDashboard;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class SponsorController extends Controller
{
    /**
     * Show the team's sponsors with filters and summary counts.
     */
    public function index(ListSponsorsRequest $request, Team $currentTeam, SponsorDashboard $dashboard): Response
    {
        $user = $request->user();
        $filters = $request->filters();

        return Inertia::render('sponsors/index', [
            'team' => ['name' => $currentTeam->name, 'slug' => $currentTeam->slug],
            'sponsors' => $dashboard->sponsors($currentTeam, $filters),
            'filters' => [
                'status' => $filters->status?->value,
                'town' => $filters->town,
                'region' => $filters->region,
                'min_confidence' => $filters->minConfidence,
                'tech' => $filters->tech,
                'scale_up' => $filters->scaleUp,
                'rating_missing' => $filters->ratingMissing,
                'needs_second_check' => $filters->needsSecondCheck,
                'ai' => $filters->ai,
                'search' => $filters->search,
            ],
            'statusCounts' => $dashboard->statusCounts($currentTeam),
            'skipReasonCounts' => $dashboard->skipReasonCounts($currentTeam),
            'regions' => $dashboard->regions($currentTeam),
            'permissions' => [
                'canEdit' => $user?->canEditSponsors($currentTeam) ?? false,
                'canSetRatings' => $user?->can('setRatings', [Sponsor::class, $currentTeam]) ?? false,
            ],
        ]);
    }

    /**
     * Update the sponsor's website or confirmed flag.
     */
    public function update(UpdateSponsorRequest $request, Team $currentTeam, Sponsor $sponsor): RedirectResponse
    {
        $sponsor->update($request->changes());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Sponsor updated.')]);

        return redirect()->back(fallback: route('sponsors.index', ['current_team' => $currentTeam->slug]));
    }
}
