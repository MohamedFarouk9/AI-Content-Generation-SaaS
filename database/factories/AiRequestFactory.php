<?php

namespace Database\Factories;

use App\Modules\AI\Models\AiModel;
use App\Modules\AI\Models\AiRequest;
use App\Modules\Identity\Models\User;
use App\Modules\Organization\Models\Organization;
use App\Shared\Enums\AiRequestStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

class AiRequestFactory extends Factory
{
    protected $model = AiRequest::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'organization_id' => Organization::factory(),
            'ai_model_id' => AiModel::factory(),
            'prompt' => fake()->paragraph(),
            'response' => fake()->paragraphs(3, true),
            'status' => AiRequestStatus::COMPLETED->value,
            'prompt_tokens' => fake()->numberBetween(50, 500),
            'completion_tokens' => fake()->numberBetween(100, 2000),
            'total_tokens' => fake()->numberBetween(150, 2500),
            'cost_in_cents' => fake()->numberBetween(1, 50),
        ];
    }

    public function pending(): static
    {
        return $this->state(fn () => [
            'status' => AiRequestStatus::PENDING->value,
            'response' => null,
            'prompt_tokens' => 0,
            'completion_tokens' => 0,
            'total_tokens' => 0,
            'cost_in_cents' => 0,
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn () => [
            'status' => AiRequestStatus::FAILED->value,
            'response' => null,
            'error_message' => 'Provider API returned an error.',
        ]);
    }
}
