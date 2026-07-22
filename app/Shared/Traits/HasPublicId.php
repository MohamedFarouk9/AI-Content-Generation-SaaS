<?php 

namespace App\Shared\Traits;

use Illuminate\Support\Str;


trait HasPublicId
{
    public static function bootHasPublicId()
    {
       static::creating(function($model) {
         if(empty($model->public_id)) {
            $model->public_id = (string) Str::ulid();
         }
       });
    }

    public function getRouteKeyName(): string 
    {
        // Expose public_id in route model binding instead of internal ID like 1, 2, 3... 
        // Now when you use Route::currentRoute("slug"), it will use the public_id instead of the internal ID
        // Also in route binding {{ $model->public_id }} will work 
        return 'public_id';
    }
}