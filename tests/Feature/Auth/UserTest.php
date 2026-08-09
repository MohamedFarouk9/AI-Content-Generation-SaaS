<?php

use App\Modules\Identity\Models\User;

it('can get the authenticated user', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/api/auth/user')
        ->assertOk()
        ->assertJsonStructure(['user' => ['name', 'email', 'public_id']]);
});

it('cannot get user when not authenticated', function () {
    $this->get('/api/auth/user')
        ->assertRedirect();
});
