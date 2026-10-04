<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SkipReason;
use App\Enums\SponsorStatus;
use App\Enums\TechReason;
use Database\Factories\SponsorFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $team_id
 * @property string $name
 * @property string|null $town
 * @property string|null $county
 * @property string $route
 * @property string $rating
 * @property 'A'|'B'|null $rating_grade
 * @property bool $rating_grade_manual
 * @property string|null $region
 * @property int $priority
 * @property string|null $company_number
 * @property string|null $company_status
 * @property array<int, string>|null $sic_codes
 * @property bool|null $is_tech
 * @property TechReason|null $tech_reason
 * @property string|null $website
 * @property int|null $confidence
 * @property bool $confirmed
 * @property SponsorStatus $status
 * @property SkipReason|null $skip_reason
 * @property int $attempts
 * @property string|null $error
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Team $team
 */
#[Fillable([
    'team_id',
    'name',
    'town',
    'county',
    'route',
    'rating',
    'rating_grade',
    'rating_grade_manual',
    'region',
    'priority',
    'company_number',
    'company_status',
    'sic_codes',
    'is_tech',
    'tech_reason',
    'website',
    'confidence',
    'confirmed',
    'status',
    'skip_reason',
    'attempts',
    'error',
])]
class Sponsor extends Model
{
    /** @use HasFactory<SponsorFactory> */
    use HasFactory;

    /**
     * The lookup gives up on a row after this many attempts.
     */
    public const MAX_ATTEMPTS = 3;

    /**
     * The register route for Scale-up visa sponsors.
     */
    public const SCALE_UP_ROUTE = 'Scale-up';

    /**
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'pending',
        'priority' => 0,
        'attempts' => 0,
        'confirmed' => false,
        'rating_grade_manual' => false,
    ];

    /**
     * Get the team that owns the sponsor.
     *
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Only sponsors that have not been looked up yet.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function pending(Builder $query): void
    {
        $query->where('status', SponsorStatus::Pending);
    }

    /**
     * Only sponsors the lookup pipeline can still work on.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function readyForLookup(Builder $query): void
    {
        $query->whereIn('status', [SponsorStatus::Pending, SponsorStatus::ChDone])
            ->where('attempts', '<', self::MAX_ATTEMPTS);
    }

    /**
     * Only sponsors classified as tech employers.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function tech(Builder $query): void
    {
        $query->where('is_tech', true);
    }

    /**
     * Only sponsors licensed for the Scale-up visa route.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function scaleUp(Builder $query): void
    {
        $query->where('route', 'like', '%'.self::SCALE_UP_ROUTE.'%');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sic_codes' => 'array',
            'is_tech' => 'boolean',
            'rating_grade_manual' => 'boolean',
            'tech_reason' => TechReason::class,
            'confirmed' => 'boolean',
            'status' => SponsorStatus::class,
            'skip_reason' => SkipReason::class,
            'priority' => 'integer',
        ];
    }
}
