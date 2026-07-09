<?php

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;

it('registers a new user with a valid password', function () {
    $this->post(route('register'), [
        'name' => 'Nguyen Van A',
        'email' => 'a@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ])->assertRedirect(route('events.index'));

    $this->assertAuthenticated();
    expect(User::where('email', 'a@example.com')->exists())->toBeTrue();
});

it('rejects registration with a password under 8 characters', function () {
    $this->post(route('register'), [
        'name' => 'A',
        'email' => 'a@example.com',
        'password' => 'short',
        'password_confirmation' => 'short',
    ])->assertSessionHasErrors('password');

    $this->assertGuest();
});

it('rejects a duplicate email', function () {
    User::factory()->create(['email' => 'dup@example.com']);

    $this->post(route('register'), [
        'name' => 'B',
        'email' => 'dup@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ])->assertSessionHasErrors('email');
});

it('logs a user in and out', function () {
    $user = User::factory()->create(['password' => 'password123']);

    $this->post(route('login'), ['email' => $user->email, 'password' => 'password123'])
        ->assertRedirect(route('events.index'));
    $this->assertAuthenticatedAs($user);

    $this->post(route('logout'))->assertRedirect(route('events.index'));
    $this->assertGuest();
});

it('sends a password reset email when requested', function () {
    Notification::fake();
    $user = User::factory()->create();

    $this->post(route('password.email'), ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class);
});
