<?php

use App\Modules\Identity\Models\User;
use App\Modules\Organization\Models\Organization;
use App\Modules\Organization\Models\OrganizationMember;
use App\Shared\Enums\OrganizationRole;

it('can create an organization with an owner', function () {
    $user = User::factory()->create();
    $org = Organization::factory()->create(['name' => 'Test Org', 'slug' => 'test-org']);

    OrganizationMember::create([
        'organization_id' => $org->id,
        'user_id' => $user->id,
        'role' => OrganizationRole::OWNER->value,
    ]);

    expect($org->members)->toHaveCount(1);
    expect($org->owner->first()->id)->toBe($user->id);
});

it('can add multiple members to an organization', function () {
    $org = Organization::factory()->create();
    $owner = User::factory()->create();
    $member = User::factory()->create();

    OrganizationMember::create([
        'organization_id' => $org->id,
        'user_id' => $owner->id,
        'role' => OrganizationRole::OWNER->value,
    ]);

    OrganizationMember::create([
        'organization_id' => $org->id,
        'user_id' => $member->id,
        'role' => OrganizationRole::MEMBER->value,
    ]);

    expect($org->members()->count())->toBe(2);
});

it('prevents duplicate user in same organization', function () {
    $org = Organization::factory()->create();
    $user = User::factory()->create();

    OrganizationMember::create([
        'organization_id' => $org->id,
        'user_id' => $user->id,
        'role' => OrganizationRole::OWNER->value,
    ]);

    expect(fn () => OrganizationMember::create([
        'organization_id' => $org->id,
        'user_id' => $user->id,
        'role' => OrganizationRole::MEMBER->value,
    ]))->toThrow(\Illuminate\Database\QueryException::class);
});

it('can switch current organization', function () {
    $user = User::factory()->create();
    $org1 = Organization::factory()->create();
    $org2 = Organization::factory()->create();

    $user->switchOrganization($org1);
    expect($user->fresh()->current_organization_id)->toBe($org1->id);

    $user->switchOrganization($org2);
    expect($user->fresh()->current_organization_id)->toBe($org2->id);
});

it('generates a public_id for organizations', function () {
    $org = Organization::factory()->create();

    expect($org->public_id)->not->toBeNull();
    expect(strlen($org->public_id))->toBe(26); // ULID length
});
