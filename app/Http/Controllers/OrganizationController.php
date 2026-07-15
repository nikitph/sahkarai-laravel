<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class OrganizationController extends Controller
{
    public function switch(Request $request, Organization $organization): RedirectResponse
    {
        abort_unless($request->user()->organizations()->whereKey($organization->getKey())->exists(), 403);
        $request->user()->forceFill(['current_organization_id' => $organization->getKey()])->save();

        return back()->with('success', 'Workspace changed to '.$organization->name.'.');
    }
}
