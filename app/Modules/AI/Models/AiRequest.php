<?php

namespace App\Modules\AI\Models;

use App\Modules\Identity\Models\User;
use App\Modules\Organization\Models\Organization;
use App\Shared\Enums\AiRequestStatus;
use App\Shared\Traits\BelongsToOrganization;
use App\Shared\Traits\HasPublicId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiRequest extends Model
{
    use HasFactory, HasPublicId, BelongsToOrganization;

    protected $fillable = [
        'user_id', // the user who made the request
        'organization_id', // the organization that owns the request
        'ai_model_id', // the model provider id
        'prompt', // the prompt sent to the model
        'response', // the response from the model
        'status', // the status of the request
        'prompt_tokens', // number of tokens in the prompt
        'completion_tokens', // number of tokens in the response
        'total_tokens', // total tokens used
        'cost_in_cents', // cost of the request in cents
        'error_message', // error message if any
        'metadata', // additional metadata
    ];


    protected function casts(): array
    {
        return [
            'status' => AiRequestStatus::class,
            'metadata' => 'array',
            'prompt_tokens' => 'integer',
            'completion_tokens' => 'integer',
            'total_tokens' => 'integer',
            'cost_in_cents' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function aiModel(): BelongsTo
    {
        return $this->belongsTo(AiModel::class);
    }
}
