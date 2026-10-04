<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Container\Attributes\Config;

final class PriorityLocations
{
    /**
     * @var array<string, PriorityLocation>
     */
    private array $byTown = [];

    /**
     * @param  array<string, array{region: string, priority: int}>  $locations
     */
    public function __construct(
        #[Config('sponsor-finder.priority_locations')] array $locations,
    ) {
        foreach ($locations as $town => $location) {
            $this->byTown[mb_strtolower(trim($town))] = new PriorityLocation($location['region'], $location['priority']);
        }
    }

    /**
     * Get the region and priority for a town. Unlisted and empty towns get no region and priority 0.
     */
    public function for(?string $town): PriorityLocation
    {
        return $this->byTown[mb_strtolower(trim((string) $town))] ?? new PriorityLocation(null, 0);
    }
}
