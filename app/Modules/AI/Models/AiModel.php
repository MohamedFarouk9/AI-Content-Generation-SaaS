<?php

namespace App\Modules\AI\Models;

use App\Shared\Traits\HasPublicId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AiModel extends Model
{
    use HasFactory, HasPublicId;

    protected $table = 'ai_models'; // this table for ai models providers (openai, anthropic, etc) and their pricing

    protected $fillable = [
        'provider',
        'name',
        'model_id',
        'context_window',
        'input_price_per_1m',
        'output_price_per_1m',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'context_window' => 'integer',
        'input_price_per_1m' => 'integer',
        'output_price_per_1m' => 'integer',
    ];

    /**
     * Helper to calculate cost in cents for a given token usage.
     */
    public function calculateCost(int $inputTokens, int $outputTokens): float
    {
        $inputCost = ($inputTokens / 1000000) * $this->input_price_per_1m;
        $outputCost = ($outputTokens / 1000000) * $this->output_price_per_1m;
        
        return $inputCost + $outputCost;
    }
}
