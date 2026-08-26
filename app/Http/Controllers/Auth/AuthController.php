<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Laravel\Socialite\Facades\Socialite;

// Session-based auth for the Blade/Livewire frontend, replacing the old
// Sanctum-token + NextAuth-JWT flow that Api\AuthController still serves to
// the old Next.js app during its parallel run (see that controller).
class AuthController extends Controller
{
    public function login(Request $request): RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput($request->only('email'));
        }

        if (! Auth::attempt($request->only('email', 'password'), $request->boolean('remember'))) {
            return back()->withErrors(['email' => 'Neteisingas el. paštas arba slaptažodis.'])->withInput($request->only('email'));
        }

        $request->session()->regenerate();

        return back();
    }

    public function register(Request $request): RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput($request->only('email'));
        }

        $user = User::create([
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'name' => $request->email,
        ]);

        Auth::login($user);
        $request->session()->regenerate();

        return back();
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }

    public function redirectToProvider(string $provider): RedirectResponse
    {
        return Socialite::driver($provider)->redirect();
    }

    public function handleProviderCallback(string $provider): RedirectResponse
    {
        $socialUser = Socialite::driver($provider)->user();

        // Match by provider id first (returning OAuth user), then fall back
        // to email — this is how the old NextAuth bridge matched accounts
        // too, so a user who first signed up there keeps the same row here.
        $user = User::where('oauth_provider', $provider)
            ->where('oauth_provider_id', $socialUser->getId())
            ->first()
            ?? User::where('email', $socialUser->getEmail())->first();

        if ($user) {
            $user->update([
                'oauth_provider' => $provider,
                'oauth_provider_id' => $socialUser->getId(),
                'email_verified_at' => $user->email_verified_at ?? now(),
            ]);
        } else {
            $user = User::create([
                'name' => $socialUser->getName() ?: $socialUser->getEmail(),
                'email' => $socialUser->getEmail(),
                'password' => Hash::make(str()->random(32)),
                'oauth_provider' => $provider,
                'oauth_provider_id' => $socialUser->getId(),
                'email_verified_at' => now(),
            ]);
        }

        Auth::login($user, remember: true);

        return redirect('/');
    }
}
