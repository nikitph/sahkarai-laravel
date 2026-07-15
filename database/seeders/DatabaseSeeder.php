<?php

namespace Database\Seeders;

use App\Actions\Organizations\CreateOrganization;
use App\Models\Project;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $user = User::query()->firstOrCreate(['email' => 'demo@example.com'], [
            'name' => 'Demo Founder',
            'password' => 'password',
            'email_verified_at' => now(),
        ]);

        $organization = $user->organizations()->first()
            ?? app(CreateOrganization::class)->handle($user, 'Acme Studio');
        app(TenantContext::class)->set($organization);

        foreach (['Launch workspace', 'Customer interviews', 'Product roadmap'] as $name) {
            Project::query()->firstOrCreate(['name' => $name], [
                'description' => 'A seeded reference project. Replace it with your first domain capability.',
                'status' => 'active',
            ]);
        }
    }
}
