<?php

namespace Database\Factories;

use App\Modules\AI\Models\AiModel;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class AiModelFactory extends Factory
{
    protected $model = AiModel::class;

    public function definition(): array
    {
        return [
            'provider' => 'openai',
            'name' => 'GPT-4o',
            'model_id' => 'gpt-4o',
            'context_window' => 128000,
            'input_price_per_1m' => 500, // $5.00
            'output_price_per_1m' => 1500, // $15.00
            'is_active' => true,
        ];
    }
}
