<?php

namespace Tests\Feature;

use App\Mail\PriceWatchDiscountMail;
use App\Models\Category;
use App\Models\Discount;
use App\Models\MagicLoginLink;
use App\Models\Product;
use App\Models\ProductFavorite;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

// Every link in the price-watch digest must carry UTM params, or GA files
// the click under Direct / (none) (email clients send no referrer).
class PriceWatchEmailTrackingTest extends TestCase
{
    use RefreshDatabase;

    private const UTM = 'utm_source=price_watch&utm_medium=email&utm_campaign=price_drop';

    public function test_digest_links_carry_utm_params(): void
    {
        Mail::fake();

        $user = User::factory()->create(['email' => 'seka@example.com']);
        $category = Category::factory()->create(['slug' => 'pieno-produktai']);
        $product = Product::factory()->create(['slug' => 'pienas-1-l', 'category_id' => $category->id]);
        Discount::factory()->create(['product_id' => $product->id]);
        ProductFavorite::create(['user_id' => $user->id, 'product_id' => $product->id]);

        $this->artisan('price-watch:notify')->assertSuccessful();

        $mailable = null;
        Mail::assertSent(PriceWatchDiscountMail::class, function (PriceWatchDiscountMail $mail) use (&$mailable) {
            $mailable = $mail;

            return true;
        });

        // Favorites CTA: UTM rides on the magic link's redirect target.
        $link = MagicLoginLink::where('email', $user->email)->sole();
        $this->assertSame('/favorites?'.self::UTM.'&utm_content=favorites_cta', $link->redirect_to);

        // Product rows link straight to the public product page.
        $html = $mailable->render();
        $this->assertStringContainsString(
            e('https://evaistine.lt/akcijos/pieno-produktai/pienas-1-l?'.self::UTM.'&utm_content=product'),
            $html
        );
    }

    public function test_magic_link_lands_on_favorites_with_utm_params(): void
    {
        $link = MagicLoginLink::create([
            'email' => 'seka@example.com',
            'token' => str()->random(64),
            'expires_at' => now()->addDay(),
            'redirect_to' => PriceWatchDiscountMail::trackedUrl('/favorites', 'favorites_cta'),
        ]);

        $this->get("/auth/magic-link/{$link->token}")
            ->assertRedirect('/favorites?'.self::UTM.'&utm_content=favorites_cta');
    }

    public function test_logged_in_user_with_used_or_expired_link_stays_logged_in_and_lands_on_target(): void
    {
        $user = User::factory()->create(['email' => 'seka@example.com']);
        $target = PriceWatchDiscountMail::trackedUrl('/favorites', 'favorites_cta');
        $used = MagicLoginLink::create(['email' => $user->email, 'token' => str()->random(64), 'expires_at' => now()->addDay(), 'used_at' => now()->subHour(), 'redirect_to' => $target]);
        $expired = MagicLoginLink::create(['email' => $user->email, 'token' => str()->random(64), 'expires_at' => now()->subDay(), 'redirect_to' => $target]);

        foreach ([$used, $expired] as $link) {
            $this->actingAs($user)->get("/auth/magic-link/{$link->token}")->assertRedirect($target);
            $this->assertAuthenticatedAs($user);
        }

        $this->actingAs($user)->get('/auth/magic-link/nera-tokio-tokeno')->assertRedirect('/favorites');
        $this->assertAuthenticatedAs($user);
    }

    public function test_guest_with_expired_link_is_sent_to_login(): void
    {
        $link = MagicLoginLink::create(['email' => 'seka@example.com', 'token' => str()->random(64), 'expires_at' => now()->subDay(), 'redirect_to' => '/favorites']);

        $this->get("/auth/magic-link/{$link->token}")->assertRedirect('/?login=1&magic_link_expired=1');
        $this->assertGuest();
    }
}
