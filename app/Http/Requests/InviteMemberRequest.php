<?php

namespace App\Http\Requests;

use App\Models\Role;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InviteMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manageMembers', app(TenantContext::class)->organization());
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return ['email' => ['required', 'email'], 'role' => ['required', Rule::enum(Role::class), Rule::notIn([Role::Owner->value])]];
    }
}
