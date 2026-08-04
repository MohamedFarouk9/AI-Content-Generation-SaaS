<?php

namespace App\Modules\Organization\Models;

use App\Modules\Identity\Models\User;
use App\Shared\Enums\OrganizationRole;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrganizationMember extends Model
{
    // this table about user role in the organization
    // it like pivot table between user and organization 
    protected $fillable = [
        'organization_id', 
        'user_id',
        'role',
    ];

    protected function casts(): array
    {
        return [
            'role' => OrganizationRole::class, // converts to enum in code and string in db 
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
