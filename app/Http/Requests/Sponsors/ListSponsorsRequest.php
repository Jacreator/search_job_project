<?php

declare(strict_types=1);

namespace App\Http\Requests\Sponsors;

use App\Enums\SponsorStatus;
use App\Services\SponsorFilters;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListSponsorsRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'status' => ['nullable', Rule::enum(SponsorStatus::class)],
            'town' => ['nullable', 'string', 'max:255'],
            'region' => ['nullable', 'string', 'max:255'],
            'min_confidence' => ['nullable', 'integer', 'between:0,100'],
            'tech' => ['nullable', 'boolean'],
            'scale_up' => ['nullable', 'boolean'],
            'rating_missing' => ['nullable', 'boolean'],
            'needs_second_check' => ['nullable', 'boolean'],
            'search' => ['nullable', 'string', 'max:255'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * The validated filters.
     */
    public function filters(): SponsorFilters
    {
        $minConfidence = $this->validated('min_confidence');

        return new SponsorFilters(
            status: SponsorStatus::tryFrom((string) $this->validated('status')),
            town: $this->text('town'),
            region: $this->text('region'),
            minConfidence: $minConfidence === null ? null : (int) $minConfidence,
            tech: $this->boolean('tech'),
            scaleUp: $this->boolean('scale_up'),
            ratingMissing: $this->boolean('rating_missing'),
            needsSecondCheck: $this->boolean('needs_second_check'),
            search: $this->text('search'),
        );
    }

    /**
     * A validated text filter, trimmed, or null when it is empty.
     */
    private function text(string $key): ?string
    {
        $value = trim((string) $this->validated($key));

        return $value === '' ? null : $value;
    }
}
