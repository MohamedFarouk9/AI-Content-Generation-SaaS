<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_models', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('provider'); // 'openai', 'anthropic', etc.
            $table->string('name'); // e.g., 'GPT-4o' or ' Claude 4.5 Sonnet' or 'Gemini 3 Pro' or 'Llama 3.3 70B'
            $table->string('model_id')->unique(); // e.g., 'gpt-4o'
            // context_window : the maximum number of tokens that can be processed in a single request. by default 4096
            $table->integer('context_window')->default(4096);
            
            // Storing pricing in cents per 1,000,000 tokens to handle fractions accurately without floats
            // e.g. $5.00 per 1M tokens = 500 cents
            $table->unsignedBigInteger('input_price_per_1m')->default(0);
            $table->unsignedBigInteger('output_price_per_1m')->default(0);
            // is_active : boolean to check if the model is active or not 
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            // 
            $table->index(['provider', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_models');
    }
};
