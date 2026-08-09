<?php

namespace App\Modules\AI\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GenerateTextRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Auth is handled by middleware
    }

    public function rules(): array
    {
        return [
            'model_id' => ['required', 'string', 'exists:ai_models,model_id'],
            'prompt' => ['required', 'string', 'max:100000'],
            'temperature' => ['sometimes', 'numeric', 'between:0,2'],
            'max_tokens' => ['sometimes', 'integer', 'min:1', 'max:128000'],
        ];
    }
}
