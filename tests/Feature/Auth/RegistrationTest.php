<?php

use App\Modules\Identity\Models\User;

it('can register a new user', function () {
    $response = $this->post('/api/auth/register', [
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
    ]);

    $response->assertStatus(201)
        ->assertJsonStructure(['user' => ['name', 'email']]);

    $this->assertDatabaseHas('users', ['email' => 'john@example.com']);
    $this->assertAuthenticated();
});

it('cannot register with an existing email', function () {
    User::factory()->create(['email' => 'john@example.com']);

    $response = $this->post('/api/auth/register', [
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors('email');
});

it('cannot register without required fields', function () {
    $response = $this->post('/api/auth/register', []);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['name', 'email', 'password']);
});

it('cannot register with mismatched password confirmation', function () {
    $response = $this->post('/api/auth/register', [
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'password' => 'Password123!',
        'password_confirmation' => 'DifferentPassword!',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors('password');
});
