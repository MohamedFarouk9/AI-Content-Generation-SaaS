<?php

namespace Database\Seeders;

use App\Modules\Identity\Models\User;
use App\Modules\Organization\Models\Organization;
use App\Modules\Organization\Models\OrganizationMember;
use App\Shared\Enums\OrganizationRole;
use Illuminate\Database\Seeder;

class OrganizationSeeder extends Seeder
{
    public function run(): void
    {
        // --- 1. Main test user with their own organization ---
        $testUser = User::where('email', 'test@example.com')->first();

        $acmeCorp = Organization::factory()->create([
            'name' => 'Acme Corp',
            'slug' => 'acme-corp',
        ]);

        OrganizationMember::create([
            'organization_id' => $acmeCorp->id,
            'user_id' => $testUser->id,
            'role' => OrganizationRole::OWNER->value,
        ]);

        $testUser->update(['current_organization_id' => $acmeCorp->id]);

        // --- 2. Second organization owned by a different user ---
        $janeDoe = User::factory()->create([
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
        ]);

        $globalTech = Organization::factory()->create([
            'name' => 'Global Tech',
            'slug' => 'global-tech',
        ]);

        OrganizationMember::create([
            'organization_id' => $globalTech->id,
            'user_id' => $janeDoe->id,
            'role' => OrganizationRole::OWNER->value,
        ]);

        $janeDoe->update(['current_organization_id' => $globalTech->id]);

        // Add test user as a member of Global Tech too
        OrganizationMember::create([
            'organization_id' => $globalTech->id,
            'user_id' => $testUser->id,
            'role' => OrganizationRole::MEMBER->value,
        ]);

        // --- 3. Extra users and orgs for volume testing ---
        $extraUsers = User::factory(5)->create();

        foreach ($extraUsers as $user) {
            $org = Organization::factory()->create();

            OrganizationMember::create([
                'organization_id' => $org->id,
                'user_id' => $user->id,
                'role' => OrganizationRole::OWNER->value,
            ]);

            $user->update(['current_organization_id' => $org->id]);

            // Add 1-3 random additional members to each org
            $randomMembers = User::factory(rand(1, 3))->create();
            foreach ($randomMembers as $member) {
                OrganizationMember::create([
                    'organization_id' => $org->id,
                    'user_id' => $member->id,
                    'role' => fake()->randomElement([
                        OrganizationRole::ADMIN->value,
                        OrganizationRole::MEMBER->value,
                    ]),
                ]);
                $member->update(['current_organization_id' => $org->id]);
            }
        }
    }
}
