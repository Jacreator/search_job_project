<?php

declare(strict_types=1);

namespace App\Http\Requests\Sponsors;

use App\Models\Sponsor;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SetSponsorRatingRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $sponsor = $this->route('sponsor');

        abort_if(! $sponsor instanceof Sponsor, 404);

        return $this->user()?->can('setRating', $sponsor) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'grade' => ['required', 'string', Rule::in(['A', 'B'])],
        ];
    }

    /**
     * The validated grade.
     *
     * @return 'A'|'B'
     */
    public function grade(): string
    {
        return $this->validated('grade') === 'B' ? 'B' : 'A';
    }
}
