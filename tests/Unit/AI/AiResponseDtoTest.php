<?php

use App\Modules\AI\DTOs\AiResponse;

it('creates an AiResponse DTO with all fields', function () {
    $response = new AiResponse(
        content: 'Hello, world!',
        promptTokens: 10,
        completionTokens: 5,
        totalTokens: 15,
        finishReason: 'stop',
        rawResponse: ['choices' => []]
    );

    expect($response->content)->toBe('Hello, world!');
    expect($response->promptTokens)->toBe(10);
    expect($response->completionTokens)->toBe(5);
    expect($response->totalTokens)->toBe(15);
    expect($response->finishReason)->toBe('stop');
    expect($response->rawResponse)->toBe(['choices' => []]);
});

it('defaults finishReason to stop', function () {
    $response = new AiResponse(
        content: 'test',
        promptTokens: 0,
        completionTokens: 0,
        totalTokens: 0,
    );

    expect($response->finishReason)->toBe('stop');
    expect($response->rawResponse)->toBeNull();
});
