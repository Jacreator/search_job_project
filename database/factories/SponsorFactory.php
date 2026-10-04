<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\SponsorStatus;
use App\Models\Sponsor;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Sponsor>
 */
class SponsorFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'name' => fake()->unique()->company(),
            'town' => fake()->city(),
            'county' => null,
            'route' => 'Skilled Worker',
            'rating' => 'Worker (A rating)',
            'rating_grade' => 'A',
            'status' => SponsorStatus::Pending,
        ];
    }
}
