<?php

namespace App\Models;

use App\Models\Scopes\BelongsToOrganization;
use App\Support\Tenancy\TenantContext;
use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $organization_id
 * @property int|null $created_by
 * @property string $name
 * @property string|null $description
 * @property string $status
 */
#[Fillable(['organization_id', 'name', 'description', 'status', 'created_by'])]
#[ScopedBy([BelongsToOrganization::class])]
class Project extends Model
{
    /** @use HasFactory<ProjectFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (Project $project): void {
            if (! $project->organization_id) {
                $project->organization()->associate(app(TenantContext::class)->organization());
            }

            if (! $project->created_by && auth()->user()) {
                $project->creator()->associate(auth()->user());
            }
        });
    }

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
