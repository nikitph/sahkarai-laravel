<?php

namespace App\Http\Middleware;

use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetCurrentOrganization
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $organization = $user?->currentOrganization;

        if (! $organization || ! $user->organizations()->whereKey($organization->getKey())->exists()) {
            $organization = $user?->organizations()->first();

            if ($organization) {
                $user->forceFill(['current_organization_id' => $organization->getKey()])->save();
            }
        }

        abort_unless($organization !== null, 403, 'Create an organization to continue.');
        app(TenantContext::class)->set($organization);

        try {
            return $next($request);
        } finally {
            app(TenantContext::class)->clear();
        }
    }
}
