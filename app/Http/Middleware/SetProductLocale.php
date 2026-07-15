<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetProductLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        app()->setLocale($user ? $user->locale->value : $request->session()->get('locale', 'en'));

        return $next($request);
    }
}
