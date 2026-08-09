<?php

namespace Database\Seeders;

use App\Modules\AI\Models\AiModel;
use Illuminate\Database\Seeder;

class AiModelSeeder extends Seeder
{
    public function run(): void
    {
        $models = [
            [
                'provider' => 'openai',
                'name' => 'GPT-4o',
                'model_id' => 'gpt-4o',
                'context_window' => 128000,
                'input_price_per_1m' => 500,  // $5.00 per 1M input tokens
                'output_price_per_1m' => 1500, // $15.00 per 1M output tokens
                'is_active' => true,
            ],
            [
                'provider' => 'openai',
                'name' => 'GPT-4o Mini',
                'model_id' => 'gpt-4o-mini',
                'context_window' => 128000,
                'input_price_per_1m' => 15,    // $0.15 per 1M input tokens
                'output_price_per_1m' => 60,   // $0.60 per 1M output tokens
                'is_active' => true,
            ],
            [
                'provider' => 'anthropic',
                'name' => 'Claude 3.5 Sonnet',
                'model_id' => 'claude-3-5-sonnet-20240620',
                'context_window' => 200000,
                'input_price_per_1m' => 300,   // $3.00
                'output_price_per_1m' => 1500, // $15.00
                'is_active' => false, // Disabled until Phase Anthropic is built
            ],
        ];

        foreach ($models as $model) {
            AiModel::firstOrCreate(['model_id' => $model['model_id']], $model);
        }
    }
}
