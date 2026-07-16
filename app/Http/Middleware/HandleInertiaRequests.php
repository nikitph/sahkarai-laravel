<?php

namespace App\Http\Middleware;

use App\Models\Permission;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'locale' => app()->getLocale(),
            'auth' => [
                'user' => $request->user(),
            ],
            'product' => fn () => $request->user() ? [
                'tier' => $request->user()->tier,
                'role' => $request->user()->role,
                'locale' => $request->user()->locale,
                'credits' => $request->user()->credits_balance,
                'personalized_chat' => $request->user()->tier->canPersonalizeChat(),
                'unread_notifications' => $request->user()->productNotifications()->whereNull('read_at')->count(),
            ] : null,
            'realtime' => fn () => $request->user() ? config('sahkarai.realtime') : null,
            'organization' => fn () => $this->organizationProps($request),
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }

    /** @return array<string, mixed>|null */
    private function organizationProps(Request $request): ?array
    {
        $user = $request->user();

        if (! $user) {
            return null;
        }

        $current = $user->currentOrganization;

        return [
            'current' => $current,
            'all' => $user->organizations()->get(['organizations.id', 'name', 'slug']),
            'permissions' => $current
                ? collect(Permission::cases())->filter(fn (Permission $permission) => $user->hasPermission($permission, $current))->map->value->values()
                : [],
        ];
    }
}
