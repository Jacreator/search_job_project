<?php

declare(strict_types=1);

namespace App\Http\Requests\Teams;

use App\Enums\TeamPermission;
use App\Enums\TeamRole;
use App\Models\Membership;
use App\Models\Team;
use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateMemberSponsorAccessRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->hasTeamPermission($this->team(), TeamPermission::ManageSponsorEditors) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'can_edit_sponsors' => ['required', 'boolean'],
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
                if ($this->membership()->role !== TeamRole::Member) {
                    $validator->errors()->add('can_edit_sponsors', __('Owners and admins can always edit sponsors.'));
                }
            },
        ];
    }

    /**
     * Get the team membership of the user being changed.
     */
    public function membership(): Membership
    {
        $user = $this->route('user');

        abort_if(! $user instanceof User, 404);

        return $this->team()->memberships()->where('user_id', $user->id)->firstOrFail();
    }

    /**
     * Get the team associated with the request.
     */
    private function team(): Team
    {
        $team = $this->route('team');

        abort_if(! $team instanceof Team, 404);

        return $team;
    }
}
