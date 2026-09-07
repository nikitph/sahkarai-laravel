<?php

namespace App\Http\Requests\Billing;

use App\Enums\Tier;
use App\Models\Permission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PurchaseOrganizationSubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $organization = $this->user()?->currentOrganization;

        return $organization === null
            || $this->user()->hasPermission(Permission::ManageBilling, $organization);
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'organization_name' => [$this->user()->currentOrganization ? 'nullable' : 'required', 'string', 'max:120'],
            'tier' => ['required', Rule::enum(Tier::class), Rule::notIn([Tier::Free->value])],
            'seats' => [
                'required',
                'integer',
                'min:'.config('sahkarai.razorpay.organization_billing.min_seats', 2),
                'max:'.config('sahkarai.razorpay.organization_billing.max_seats', 25),
            ],
        ];
    }
}
