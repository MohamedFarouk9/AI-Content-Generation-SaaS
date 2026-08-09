<?php

namespace App\Modules\AI\DTOs;

readonly class AiResponse
{
    public function __construct(
        public string $content,
        public int $promptTokens,
        public int $completionTokens,
        public int $totalTokens,
        public string $finishReason = 'stop',
        public ?array $rawResponse = null
    ) {}
}
