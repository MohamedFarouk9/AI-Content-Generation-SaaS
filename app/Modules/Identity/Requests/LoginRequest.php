<?php

namespace App\Modules\Identity\Requests;

use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
            'remember' => ['boolean'],
        ];
    }

    /**
     * Authenticate the user.
     *
     * This method is usually called from the controller:
     *
     * $request->authenticate();
     */

    public function authenticate(): void
    {
        // First check if the user has exceeded the allowed login attempts.
        $this->ensureIsNotRateLimited();
        
        if(! Auth::attempt($this->only('email' , 'password'), $this->boolean('remember'))) {
            // Increase the number of failed attempts after a failed login attempt
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'email' => trans('auth.failed')
            ]);
        }
        
        // Remove any previous failed attempts so that the counter is reset on successful login
        RateLimiter::clear($this->throttleKey());
    }


    /**
     * Check whether the user has exceeded
     * the maximum allowed login attempts.
     */
    public function ensureIsNotRateLimited(): void 
    {
       
        if(! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return; // Return early if the user has not reached the maximum number of failed attempts.
        }

        // Fire the lockout event so Laravel can listen to this event and trigger other actions.
        event(new Lockout($this));

        // Calculate the number of seconds until the user can try again
        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    public function throttleKey(): string 
    {
        return Str::transliterate(Str::lower($this->input('email')).'|'.$this->ip());
    }

}
