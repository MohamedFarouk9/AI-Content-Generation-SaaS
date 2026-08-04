<?php

namespace App\Shared\Traits;

use App\Modules\Organization\Models\Organization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Apply this trait to any model that is scoped to an organization.
 *
 * It provides:
 * - A `organization()` BelongsTo relationship.
 * - A global scope that automatically filters queries by the
 *   authenticated user's current organization.
 */
trait BelongsToOrganization
{
    public static function bootBelongsToOrganization(): void
    {
        /* A Global Scope modifies EVERY query executed on this model. */
        static::addGlobalScope('organization', function (Builder $builder) {
            $user = auth()->user();

            if ($user && $user->current_organization_id) {
                $builder->where(
                    $builder->getModel()->getTable() . '.organization_id',
                    $user->current_organization_id
                );
            }
        });

        static::creating(function ($model) {
            if (empty($model->organization_id)) {
                $user = auth()->user();
                if ($user && $user->current_organization_id) {
                    $model->organization_id = $user->current_organization_id;
                }
            }
        });
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
