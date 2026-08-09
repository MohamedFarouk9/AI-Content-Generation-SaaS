<?php
namespace App\Modules\AI\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\AI\Models\AiModel;
use App\Modules\AI\Models\AiRequest;
use App\Modules\AI\Requests\GenerateTextRequest;
use App\Modules\AI\AiManager;
use App\Shared\Enums\AiRequestStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

class AiController extends Controller
{
    public function __construct(
        protected AiManager $aiManager
    ) {}

    /**
     * List available AI models.
     */
    public function models(): JsonResponse
    {
        $models = AiModel::where('is_active', true)
            ->select(['public_id', 'provider', 'name', 'model_id', 'context_window'])
            ->get();

        return response()->json($models);
    }

    /**
     * Generate text using an AI provider.
     */
    public function generate(GenerateTextRequest $request): JsonResponse
    {
        $user = $request->user();
        $aiModel = AiModel::where('model_id', $request->model_id)
            ->where('is_active', true)
            ->firstOrFail();

        // Create the request record as "pending"
        $aiRequest = AiRequest::create([
            'user_id' => $user->id,
            'organization_id' => $user->current_organization_id,
            'ai_model_id' => $aiModel->id,
            'prompt' => $request->prompt,
            'status' => AiRequestStatus::PENDING->value,
        ]);

        try {
            $aiRequest->update(['status' => AiRequestStatus::PROCESSING->value]);
            $driver = $this->aiManager->driver($aiModel->provider);
            $aiResponse = $driver->generateText(
                $aiModel->model_id,
                $request->prompt,
                options: array_filter([
                    'temperature' => $request->temperature,
                    'max_tokens' => $request->max_tokens,
                ])
            );

            $costInCents = $aiModel->calculateCost(
                $aiResponse->promptTokens,
                $aiResponse->completionTokens
            );
            $aiRequest->update([
                'response' => $aiResponse->content,
                'status' => AiRequestStatus::COMPLETED->value,
                'prompt_tokens' => $aiResponse->promptTokens,
                'completion_tokens' => $aiResponse->completionTokens,
                'total_tokens' => $aiResponse->totalTokens,
                'cost_in_cents' => (int) round($costInCents),
                'metadata' => $aiResponse->rawResponse,
            ]);
            return response()->json([
                'request_id' => $aiRequest->public_id,
                'content' => $aiResponse->content,
                'usage' => [
                    'prompt_tokens' => $aiResponse->promptTokens,
                    'completion_tokens' => $aiResponse->completionTokens,
                    'total_tokens' => $aiResponse->totalTokens,
                ],
            ]);
        } catch (Throwable $e) {
            $aiRequest->update([
                'status' => AiRequestStatus::FAILED->value,
                'error_message' => $e->getMessage(),
            ]);
            return response()->json([
                'error' => 'AI generation failed. Please try again.',
            ], 500);
        }
    }

    /**
     * List user's AI request history.
     */
    public function history(Request $request): JsonResponse
    {
        $requests = AiRequest::where('user_id', $request->user()->id)
            ->with('aiModel:id,name,model_id,provider')
            ->latest()
            ->paginate(20);
        return response()->json($requests);
    }

    /**
     * Show a single AI request.
     */
    public function show(string $publicId): JsonResponse
    {
        $aiRequest = AiRequest::where('public_id', $publicId)
            ->with('aiModel:id,name,model_id,provider')
            ->firstOrFail();
        return response()->json(['request' => $aiRequest]);
    }
}
