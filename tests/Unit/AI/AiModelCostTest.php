<?php

use App\Modules\AI\Models\AiModel;

it('calculates cost correctly for gpt-4o pricing', function () {
    $model = new AiModel([
        'input_price_per_1m' => 500,   // $5.00 per 1M tokens
        'output_price_per_1m' => 1500, // $15.00 per 1M tokens
    ]);

    // 1000 input tokens + 500 output tokens
    $cost = $model->calculateCost(1000, 500);

    // Expected: (1000/1000000)*500 + (500/1000000)*1500 = 0.5 + 0.75 = 1.25 cents
    expect($cost)->toBe(1.25);
});

it('calculates zero cost for zero tokens', function () {
    $model = new AiModel([
        'input_price_per_1m' => 500,
        'output_price_per_1m' => 1500,
    ]);

    $cost = $model->calculateCost(0, 0);

    expect($cost)->toBe(0.0);
});

it('handles mini model fractional pricing', function () {
    $model = new AiModel([
        'input_price_per_1m' => 15,  // $0.15 per 1M
        'output_price_per_1m' => 60, // $0.60 per 1M
    ]);

    $cost = $model->calculateCost(10000, 5000);

    // (10000/1000000)*15 + (5000/1000000)*60 = 0.15 + 0.30 = 0.45 cents
    expect($cost)->toBe(0.45);
});
