<?php

namespace App\Modules\Identity\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Identity\Models\OAuthProvider;
use App\Modules\Identity\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

class OAuthController extends Controller
{
    protected array $allowedProviders = ['google', 'github'];

    public function redirect(string $provider)
    {
        abort_unless(in_array($provider, $this->allowedProviders), 404);
        
        return Socialite::driver($provider)->stateless()->redirect();
    }

    public function callback(string $provider): JsonResponse|\Illuminate\Http\RedirectResponse
    {
        abort_unless(in_array($provider, $this->allowedProviders), 404);

        try {
            $socialUser = Socialite::driver($provider)->stateless()->user();
            
            $user = DB::transaction(function () use ($provider, $socialUser) {
                // 1. Check if identity exists
                $oauth = OAuthProvider::where('provider', $provider)
                    ->where('provider_id', $socialUser->getId())
                    ->with('user')
                    ->first();

                if ($oauth) {
                    return $oauth->user;
                }

                // 2. Check if email exists to link, otherwise create
                $user = User::where('email', $socialUser->getEmail())->first();

                if (! $user) {
                    $user = User::create([
                        'name' => $socialUser->getName() ?? $socialUser->getNickname(),
                        'email' => $socialUser->getEmail(),
                        'email_verified_at' => now(), // Trust provider email
                    ]);
                }

                // 3. Create linked provider
                $user->oauthProviders()->create([
                    'provider' => $provider,
                    'provider_id' => $socialUser->getId(),
                ]);

                return $user;
            });

            Auth::login($user);
            request()->session()->regenerate();

            // Redirect to frontend app
            return redirect(config('app.frontend_url') . '/dashboard');
            
        } catch (Throwable $e) {
            Log::error("OAuth Error: {$e->getMessage()}");
            return redirect(config('app.frontend_url') . '/login?error=oauth_failed');
        }
    }
}
