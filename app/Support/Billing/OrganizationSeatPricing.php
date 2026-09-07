<?php

namespace App\Support\Billing;

use App\Enums\Tier;
use InvalidArgumentException;
use RuntimeException;

class OrganizationSeatPricing
{
    /** @return array{seats: int, unit_price: int, subtotal: int, discount_basis_points: int, discount: int, total: int, offer_id: string} */
    public function quote(Tier $tier, int $seats): array
    {
        $minimum = (int) config('sahkarai.razorpay.organization_billing.min_seats', 2);
        $maximum = (int) config('sahkarai.razorpay.organization_billing.max_seats', 25);

        if ($tier === Tier::Free || $seats < $minimum || $seats > $maximum) {
            throw new InvalidArgumentException("Organization subscriptions require {$minimum}–{$maximum} seats on a paid tier.");
        }

        $discounts = config('sahkarai.razorpay.organization_billing.discounts', []);
        $band = null;
        if (is_array($discounts)) {
            foreach ($discounts as $candidate) {
                if (is_array($candidate) && $seats >= (int) $candidate['min'] && $seats <= (int) $candidate['max']) {
                    $band = $candidate;
                    break;
                }
            }
        }

        if (! $band) {
            throw new RuntimeException('No organization discount is configured for this seat quantity.');
        }

        $offerId = (string) ($band['offer_id'] ?? '');
        if ($offerId === '') {
            throw new RuntimeException('The Razorpay offer for this organization discount is not configured.');
        }

        $unitPrice = (int) config("sahkarai.tiers.{$tier->value}.monthly_price");
        $subtotal = $unitPrice * $seats;
        $basisPoints = (int) $band['basis_points'];
        $discount = intdiv($subtotal * $basisPoints, 10_000);

        return [
            'seats' => $seats,
            'unit_price' => $unitPrice,
            'subtotal' => $subtotal,
            'discount_basis_points' => $basisPoints,
            'discount' => $discount,
            'total' => $subtotal - $discount,
            'offer_id' => $offerId,
        ];
    }
}
