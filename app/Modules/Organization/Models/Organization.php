<?php

namespace App\Modules\Organization\Models;

use App\Modules\Identity\Models\User;
use App\Shared\Enums\OrganizationRole;
use App\Shared\Traits\HasPublicId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Organization extends Model
{
    // this table about tenant information for each tenant (customer)
    // also called (workspace, group, team)
    use HasFactory, HasPublicId, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
    ];

    protected $hidden = [
        'id',
    ];

    /**
     * All members of this organization (including the owner).
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'organization_members')
            ->withPivot('role')
            ->withTimestamps();
    }

    /**
     * The organization membership records.
     */
    public function organizationMembers(): HasMany
    {
        return $this->hasMany(OrganizationMember::class);
    }

    /**
     * The owner of this organization.
     */
    public function owner(): BelongsToMany
    {
        return $this->members()->wherePivot('role', OrganizationRole::Owner->value);
    }
}
