<?php

namespace App\Http\Controllers;

use App\Mail\WeeklyDigestConfirmMail;
use App\Models\EmailSubscriber;
use App\Models\Store;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Email notifications without an account (EmailSubscriber): signup from the
 * site, double opt-in, and the settings page (/pranesimai/{token}) linked
 * from every email, where the stores and the emails ("Atpigo sekamos
 * prekės", "Savaitės santrauka", "Naujas leidinys") are chosen.
 */
class EmailSubscriptionController extends Controller
{
    public function subscribe(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => 'required|email|max:190',
            'stores' => 'nullable|array|max:20',
            'stores.*' => 'string|max:100',
        ], [
            'email.required' => 'Įveskite el. paštą.',
            'email.email' => 'Neteisingas el. pašto formatas.',
        ]);

        $email = mb_strtolower(trim($data['email']));
        $stores = $this->knownStores($data['stores'] ?? []);
        $subscriber = EmailSubscriber::firstOrNew(['email' => $email]);
        $isNew = ! $subscriber->exists;

        if ($isNew) {
            $subscriber->token = str()->random(48);
        }

        // A confirmed, active address keeps its settings: anyone could type
        // someone else's email here, so changes go through its own link.
        if ($isNew || ! $subscriber->confirmed_at || $subscriber->unsubscribed_at) {
            $subscriber->store_slugs = $stores ?: null;
            $subscriber->wants_weekly = true;
            $subscriber->wants_new_leaflets = true;
            $subscriber->unsubscribed_at = null;
        }

        // Signed in with this very (verified) address: no confirmation step.
        $user = $request->user();
        if ($user && mb_strtolower($user->email) === $email && $user->email_verified_at) {
            // Their own address, so the tap itself switches the emails on
            // (also when the settings page was opened before with them off).
            $subscriber->user_id = $user->id;
            $subscriber->confirmed_at ??= now();
            $subscriber->wants_weekly = true;
            $subscriber->wants_new_leaflets = true;
            $subscriber->unsubscribed_at = null;
            if ($stores) {
                $subscriber->store_slugs = $stores;
            }
            $subscriber->save();

            return response()->json(['confirmed' => true, 'settings' => $subscriber->settingsUrl()]);
        }

        $subscriber->save();
        Mail::to($email)->send(new WeeklyDigestConfirmMail(url('/pranesimai/patvirtinti/'.$subscriber->token)));

        return response()->json(['sent' => true]);
    }

    // Signed-in users' way in (side menu, /favorites): their own settings
    // page, created on the first visit. Their email is verified, so it's
    // confirmed already; the subscriber emails start off until ticked, so
    // opening the page never signs anyone up.
    public function mine(Request $request): RedirectResponse
    {
        $user = $request->user();
        $subscriber = EmailSubscriber::where('user_id', $user->id)->first()
            ?? EmailSubscriber::where('email', mb_strtolower($user->email))->first();

        if (! $subscriber) {
            $subscriber = EmailSubscriber::create([
                'email' => mb_strtolower($user->email),
                'user_id' => $user->id,
                'store_slugs' => $user->preferred_store_slugs ?: null,
                'wants_weekly' => false,
                'wants_new_leaflets' => false,
                'token' => str()->random(48),
                'confirmed_at' => now(),
            ]);
        } elseif (! $subscriber->user_id) {
            $subscriber->forceFill(['user_id' => $user->id, 'confirmed_at' => $subscriber->confirmed_at ?? now()])->save();
        }

        return redirect('/pranesimai/'.$subscriber->token);
    }

    public function confirm(string $token): RedirectResponse
    {
        $subscriber = EmailSubscriber::where('token', $token)->firstOrFail();

        if (! $subscriber->confirmed_at || $subscriber->unsubscribed_at) {
            $subscriber->forceFill(['confirmed_at' => $subscriber->confirmed_at ?? now(), 'unsubscribed_at' => null])->save();
        }

        return redirect('/pranesimai/'.$token.'?patvirtinta=1');
    }

    public function settings(Request $request, string $token): View
    {
        $subscriber = EmailSubscriber::where('token', $token)->firstOrFail();

        return view('subscriptions.settings', [
            'subscriber' => $subscriber,
            'justConfirmed' => $request->boolean('patvirtinta'),
        ]);
    }

    public function update(Request $request, string $token): RedirectResponse
    {
        $subscriber = EmailSubscriber::where('token', $token)->firstOrFail();

        $data = $request->validate([
            'stores' => 'nullable|array|max:40',
            'stores.*' => ['string', Rule::exists('stores', 'slug')],
        ]);

        $subscriber->fill([
            'store_slugs' => $this->knownStores($data['stores'] ?? []) ?: null,
            'wants_weekly' => $request->boolean('wants_weekly'),
            'wants_new_leaflets' => $request->boolean('wants_new_leaflets'),
            'unsubscribed_at' => null,
            'confirmed_at' => $subscriber->confirmed_at ?? now(),
        ])->save();

        if ($subscriber->user) {
            $subscriber->user->forceFill([
                'price_watch_unsubscribed_at' => $request->boolean('wants_price_drops') ? null : ($subscriber->user->price_watch_unsubscribed_at ?? now()),
            ])->save();
        }

        return redirect('/pranesimai/'.$token)->with('status', 'Nustatymai išsaugoti.');
    }

    public function unsubscribe(string $token): RedirectResponse
    {
        $subscriber = EmailSubscriber::where('token', $token)->firstOrFail();
        $subscriber->forceFill(['unsubscribed_at' => now()])->save();
        $subscriber->user?->forceFill(['price_watch_unsubscribed_at' => now()])->save();

        return redirect('/pranesimai/'.$token)->with('status', 'Laiškų nebesiųsime. Bet kada galite vėl įjungti.');
    }

    /**
     * @param  list<string>  $slugs
     * @return list<string>
     */
    private function knownStores(array $slugs): array
    {
        $known = Store::whereIn('slug', $slugs)->pluck('slug')->all();

        return array_values(array_unique(array_filter($slugs, fn ($slug) => in_array($slug, $known, true))));
    }
}
