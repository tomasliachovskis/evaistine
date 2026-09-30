<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\MagicLinkMail;
use App\Models\MagicLoginLink;
use App\Models\ProductFavorite;
use App\Models\User;
use GuzzleHttp\Exception\ClientException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;

// Session-based auth for the Blade/Livewire frontend, replacing the old
// Sanctum-token + NextAuth-JWT flow that Api\AuthController still serves to
// the old Next.js app during its parallel run (see that controller).
class AuthController extends Controller
{
    // Stashed by product-price-watch-banner.blade.php's toggle() the moment
    // it hits a 401 and opens the login modal — the click that got them here
    // in the first place, so it survives both login paths (the magic-link
    // click, and the full external redirect an OAuth provider does) rather
    // than trying to carry it through a form field, which OAuth can't.
    private const PENDING_FAVORITE_SESSION_KEY = 'pending_favorite_product_id';

    private const MAGIC_LINK_TTL_MINUTES = 30;

    public function rememberPendingFavorite(Request $request): JsonResponse
    {
        $request->validate(['product_id' => 'required|integer']);

        $request->session()->put(self::PENDING_FAVORITE_SESSION_KEY, (int) $request->input('product_id'));

        return response()->json(['ok' => true]);
    }

    // Every successful login/OAuth callback lands on /favorites —
    // there's no other "account home" in this app. If it was triggered by
    // the price-watch button's login-required popup, complete that pending
    // favorite first so the product they clicked is already there.
    //
    // JSON-aware: the auth modal submits via fetch(Accept: application/json)
    // now instead of a plain form POST, so it can show errors inline without
    // a full page reload — but the modal still finishes a real navigation on
    // success (window.location.href = data.redirect), same end state as the
    // old back()/redirect() responses, just told via JSON instead of a
    // redirect response. A no-JS <form> submit still gets a real redirect.
    private function redirectAfterAuth(Request $request, ?string $redirectTo = null): RedirectResponse|JsonResponse
    {
        $productId = session()->pull(self::PENDING_FAVORITE_SESSION_KEY);

        if ($productId) {
            ProductFavorite::firstOrCreate(['user_id' => auth()->id(), 'product_id' => $productId]);
        }

        $redirectTo = $redirectTo ?: '/favorites';

        if ($request->wantsJson()) {
            return response()->json(['redirect' => $redirectTo]);
        }

        return redirect($redirectTo);
    }

    private function validationFailed(Request $request, $validator): RedirectResponse|JsonResponse
    {
        if ($request->wantsJson()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        return back()->withErrors($validator)->withInput($request->only('email'));
    }

    // Passwordless login: no registration flow, no password field. A user
    // enters their email, we mail them a single-use link that logs them in,
    // creating the account automatically the first time it's used.
    public function sendMagicLink(Request $request): RedirectResponse|JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
        ], [
            'email.required' => 'Įveskite el. paštą.',
            'email.email' => 'Neteisingas el. pašto formatas.',
        ]);

        if ($validator->fails()) {
            return $this->validationFailed($request, $validator);
        }

        $link = MagicLoginLink::create([
            'email' => $request->email,
            'token' => str()->random(64),
            'expires_at' => now()->addMinutes(self::MAGIC_LINK_TTL_MINUTES),
        ]);

        Mail::to($link->email)->send(new MagicLinkMail(url("/auth/magic-link/{$link->token}")));

        if ($request->wantsJson()) {
            return response()->json(['sent' => true]);
        }

        return back()->with('status', 'Prisijungimo nuoroda išsiųsta.');
    }

    public function verifyMagicLink(Request $request, string $token): RedirectResponse|JsonResponse
    {
        $link = MagicLoginLink::where('token', $token)->first();

        if (! $link || ! $link->isValid()) {
            // Already logged in (e.g. a second click on the same single-use
            // price-watch email button): the link has nothing left to do, so
            // go where it was headed instead of opening the login modal.
            // redirect_to is always an internal path we set ourselves.
            if (Auth::check()) {
                return redirect($link?->redirect_to ?: '/favorites');
            }

            return redirect('/?login=1&magic_link_expired=1');
        }

        $link->update(['used_at' => now()]);

        $user = User::firstOrCreate(
            ['email' => $link->email],
            ['name' => $link->email, 'password' => Hash::make(str()->random(32)), 'email_verified_at' => now()],
        );

        if (! $user->email_verified_at) {
            $user->update(['email_verified_at' => now()]);
        }

        Auth::login($user, remember: true);
        $request->session()->regenerate();

        return $this->redirectAfterAuth($request, $link->redirect_to);
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

    public function handleProviderCallback(Request $request, string $provider): RedirectResponse
    {
        // No `code` means the provider isn't handing back a login: the user
        // cancelled on the consent screen (`?error=access_denied`) or a bot
        // opened the bare URL — Socialite threw a 500 on both (Search
        // Console listed /auth/google/callback as a server error). A stale
        // state or an already-used/expired code (back button, double
        // submit) throws too; all of these just go back to the login modal.
        if (! $request->filled('code')) {
            return redirect('/?login=1');
        }

        try {
            $socialUser = Socialite::driver($provider)->user();
        } catch (InvalidStateException|ClientException $e) {
            report($e);

            return redirect('/?login=1');
        }

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

        return $this->redirectAfterAuth($request);
    }
}
