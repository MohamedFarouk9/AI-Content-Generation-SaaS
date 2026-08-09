<?php

namespace App\Modules\AI\Providers;

use App\Modules\AI\Contracts\AiProviderContract;
use App\Modules\AI\DTOs\AiResponse;
use Illuminate\Support\Facades\Http;
use Exception;

class OpenAiDriver implements AiProviderContract
{
    protected string $apiKey;
    protected string $baseUrl = 'https://api.openai.com/v1';

    public function __construct(string $apiKey)
    {
        $this->apiKey = $apiKey;
    }

    public function generateText(string $modelId, string $prompt, array $options = []): AiResponse
    {
        $temperature = $options['temperature'] ?? 0.7;
        $maxTokens = $options['max_tokens'] ?? null;

        $payload = [
            'model' => $modelId,
            'messages' => [
                ['role' => 'user', 'content' => $prompt]
            ],
            'temperature' => $temperature,
        ];

        if ($maxTokens) {
            $payload['max_tokens'] = $maxTokens;
        }

        $response = Http::withToken($this->apiKey)
            ->timeout(60) // AI requests can be slow
            ->post("{$this->baseUrl}/chat/completions", $payload);

        if ($response->failed()) {
            throw new Exception('OpenAI API Error: ' . $response->body());
        }

        $data = $response->json();

        return new AiResponse(
            content: $data['choices'][0]['message']['content'] ?? '',
            promptTokens: $data['usage']['prompt_tokens'] ?? 0,
            completionTokens: $data['usage']['completion_tokens'] ?? 0,
            totalTokens: $data['usage']['total_tokens'] ?? 0,
            finishReason: $data['choices'][0]['finish_reason'] ?? 'stop',
            rawResponse: $data
        );
    }
}
