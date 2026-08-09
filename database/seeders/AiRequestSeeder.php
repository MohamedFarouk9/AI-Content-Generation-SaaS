<?php

namespace Database\Seeders;

use App\Modules\AI\Models\AiModel;
use App\Modules\AI\Models\AiRequest;
use App\Modules\Identity\Models\User;
use App\Modules\Organization\Models\Organization;
use App\Shared\Enums\AiRequestStatus;
use Illuminate\Database\Seeder;

class AiRequestSeeder extends Seeder
{
    public function run(): void
    {
        $testUser = User::where('email', 'test@example.com')->first();
        $org = $testUser->currentOrganization;
        $gpt4o = AiModel::where('model_id', 'gpt-4o')->first();
        $gpt4oMini = AiModel::where('model_id', 'gpt-4o-mini')->first();

        if (! $testUser || ! $org || ! $gpt4o) {
            return;
        }

        // Successful requests
        AiRequest::factory()->count(5)->create([
            'user_id' => $testUser->id,
            'organization_id' => $org->id,
            'ai_model_id' => $gpt4o->id,
        ]);

        // A couple with the mini model
        if ($gpt4oMini) {
            AiRequest::factory()->count(3)->create([
                'user_id' => $testUser->id,
                'organization_id' => $org->id,
                'ai_model_id' => $gpt4oMini->id,
            ]);
        }

        // One failed request
        AiRequest::factory()->failed()->create([
            'user_id' => $testUser->id,
            'organization_id' => $org->id,
            'ai_model_id' => $gpt4o->id,
        ]);

        // One pending request
        AiRequest::factory()->pending()->create([
            'user_id' => $testUser->id,
            'organization_id' => $org->id,
            'ai_model_id' => $gpt4o->id,
        ]);
    }
}
