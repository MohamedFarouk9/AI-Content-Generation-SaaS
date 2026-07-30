<?php

namespace App\Modules\Identity\Models;

use Illuminate\Database\Eloquent\Model;

class OAuthProvider extends Model
{
    //

    protected $fillable = [
        'user_id',
        'provider',
        'provider_id',
    ];

    public function user()  
    {
        return $this->belongsTo(User::class);
    }
}
