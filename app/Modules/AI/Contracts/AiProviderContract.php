<?php

namespace App\Modules\AI\Contracts;

use App\Modules\AI\DTOs\AiResponse;

interface AiProviderContract
{
    /**
     * Generate text based on a prompt.
     *
     * @param string $modelId The specific model string (e.g., 'gpt-4o')
     * @param string $prompt The user's input prompt
     * @param array $options Additional options (temperature, max_tokens, etc.)
     */
    public function generateText(string $modelId, string $prompt, array $options = []): AiResponse;
}
