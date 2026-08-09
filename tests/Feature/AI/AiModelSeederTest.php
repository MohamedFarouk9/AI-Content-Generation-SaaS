<?php

use App\Modules\AI\Models\AiModel;

it('seeds ai models correctly', function () {
    $this->seed(\Database\Seeders\AiModelSeeder::class);

    expect(AiModel::count())->toBeGreaterThanOrEqual(2);
    expect(AiModel::where('model_id', 'gpt-4o')->exists())->toBeTrue();
    expect(AiModel::where('model_id', 'gpt-4o-mini')->exists())->toBeTrue();
});

it('seeder is idempotent', function () {
    $this->seed(\Database\Seeders\AiModelSeeder::class);
    $this->seed(\Database\Seeders\AiModelSeeder::class);

    // Should not create duplicates thanks to firstOrCreate
    expect(AiModel::where('model_id', 'gpt-4o')->count())->toBe(1);
});
