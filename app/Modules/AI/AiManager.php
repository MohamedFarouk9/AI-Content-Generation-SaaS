<?php

namespace App\Modules\AI;

use App\Modules\AI\Contracts\AiProviderContract;
use App\Modules\AI\Providers\OpenAiDriver;
use InvalidArgumentException;

class AiManager
{
    /**
     * Resolve the appropriate AI driver based on the provider name.
     */
    public function driver(string $provider): AiProviderContract
    {
        return match ($provider) {
            'openai' => new OpenAiDriver(config('services.openai.key')),
            // 'anthropic' => new AnthropicDriver(config('services.anthropic.key')),
            default => throw new InvalidArgumentException("Unsupported AI provider: {$provider}"),
        };
    }
}
