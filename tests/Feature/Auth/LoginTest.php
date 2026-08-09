<?php

use App\Modules\Identity\Models\User;

it('can login with valid credentials', function () {
    User::factory()->create([
        'email' => 'john@example.com',
        'password' => 'Password123!',
    ]);

    $response = $this->post('/api/auth/login', [
        'email' => 'john@example.com',
        'password' => 'Password123!',
    ]);

    $response->assertOk()
        ->assertJsonStructure(['user']);

    $this->assertAuthenticated();
});

it('cannot login with invalid credentials', function () {
    User::factory()->create([
        'email' => 'john@example.com',
        'password' => 'Password123!',
    ]);

    $response = $this->post('/api/auth/login', [
        'email' => 'john@example.com',
        'password' => 'WrongPassword!',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors('email');

    $this->assertGuest();
});

it('throttles login after 5 failed attempts', function () {
    User::factory()->create([
        'email' => 'john@example.com',
        'password' => 'Password123!',
    ]);

    for ($i = 0; $i < 5; $i++) {
        $this->post('/api/auth/login', [
            'email' => 'john@example.com',
            'password' => 'WrongPassword!',
        ]);
    }

    $response = $this->post('/api/auth/login', [
        'email' => 'john@example.com',
        'password' => 'WrongPassword!',
    ]);

    $response->assertStatus(422);
    expect($response->json('errors.email.0'))->toContain('Too many');
});
