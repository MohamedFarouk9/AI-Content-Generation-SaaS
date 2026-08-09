<?php

use App\Modules\Identity\Models\User;

it('can logout an authenticated user', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post('/api/auth/logout')
        ->assertOk()
        ->assertJson(['message' => 'Logged out']);

    $this->assertGuest();
});

it('cannot logout when not authenticated', function () {
    $this->post('/api/auth/logout')
        ->assertRedirect(); // Redirects to login
});
