<?php

declare(strict_types=1);

namespace App\Http\Requests\Sponsors;

use App\Enums\SponsorStatus;
use App\Models\Sponsor;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateSponsorRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->sponsor()) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'website' => ['required_without:confirmed', 'string', 'max:255', 'url:http,https'],
            'confirmed' => ['required_without:website', 'boolean'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'website.required_without' => __('Enter a website address.'),
            'website.url' => __('The website must be a full http or https address.'),
        ];
    }

    /**
     * Configure the validator instance.
     *
     * @return array<int, Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $sponsor = $this->sponsor();
                $field = $this->has('website') ? 'website' : 'confirmed';

                // The lookup would overwrite a website saved on a row it has not finished.
                if ($sponsor->status !== SponsorStatus::Done) {
                    $validator->errors()->add($field, __('Only sponsors with a finished lookup can be edited.'));

                    return;
                }

                if ($field === 'confirmed' && $this->boolean('confirmed') && blank($sponsor->website)) {
                    $validator->errors()->add('confirmed', __('Add a website before confirming it.'));
                }
            },
        ];
    }

    /**
     * The column changes to save. A website saved by hand is trusted: confidence 100 and confirmed.
     *
     * @return array{website: string, confidence: int, confirmed: bool}|array{confirmed: bool}
     */
    public function changes(): array
    {
        if ($this->has('website')) {
            return [
                'website' => (string) $this->validated('website'),
                'confidence' => 100,
                'confirmed' => true,
            ];
        }

        return ['confirmed' => $this->boolean('confirmed')];
    }

    /**
     * Get the sponsor associated with the request.
     */
    private function sponsor(): Sponsor
    {
        $sponsor = $this->route('sponsor');

        abort_if(! $sponsor instanceof Sponsor, 404);

        return $sponsor;
    }
}
