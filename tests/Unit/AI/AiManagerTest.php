<?php

use App\Modules\AI\AiManager;
use App\Modules\AI\Providers\OpenAiDriver;

it('resolves openai driver', function () {
    config(['services.openai.key' => 'test-key']);

    $manager = new AiManager();
    $driver = $manager->driver('openai');

    expect($driver)->toBeInstanceOf(OpenAiDriver::class);
});

it('throws exception for unsupported provider', function () {
    $manager = new AiManager();

    expect(fn () => $manager->driver('unknown'))
        ->toThrow(InvalidArgumentException::class, 'Unsupported AI provider: unknown');
});
