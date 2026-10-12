<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\TeamRole;
use App\Models\Sponsor;
use App\Models\Team;
use App\Models\User;

class SponsorPolicy
{
    /**
     * Determine whether the user can edit the sponsor's website and confirmed flag.
     */
    public function update(User $user, Sponsor $sponsor): bool
    {
        return $user->canEditSponsors($sponsor->team);
    }

    /**
     * Determine whether the user can set rating grades for the team's sponsors at all.
     */
    public function setRatings(User $user, Team $team): bool
    {
        return $user->teamRole($team)?->isAtLeast(TeamRole::Admin) ?? false;
    }

    /**
     * Determine whether the user can set the sponsor's rating grade.
     * Only a missing or hand-set grade can be set. A grade from the register cannot.
     */
    public function setRating(User $user, Sponsor $sponsor): bool
    {
        return $this->setRatings($user, $sponsor->team)
            && ($sponsor->rating_grade === null || $sponsor->rating_grade_manual);
    }
}
