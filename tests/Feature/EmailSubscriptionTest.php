<?php

namespace Tests\Feature;

use App\Mail\NewLeafletMail;
use App\Mail\WeeklyDigestConfirmMail;
use App\Mail\WeeklyDigestMail;
use App\Models\Category;
use App\Models\CuratedDeal;
use App\Models\Discount;
use App\Models\EmailSubscriber;
use App\Models\Product;
use App\Models\Store;
use App\Models\StoreFlyer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class EmailSubscriptionTest extends TestCase
{
    use RefreshDatabase;

    private function flyer(Store $store, array $attributes = []): StoreFlyer
    {
        return StoreFlyer::create($attributes + [
            'store_id' => $store->id,
            'slug' => 'leidinys-'.str()->random(6),
            'title' => 'Savaitės pasiūlymai',
            'valid_from' => now()->subDay(),
            'valid_to' => now()->addDays(6),
            'is_active' => true,
            'processing_status' => StoreFlyer::STATUS_READY,
        ]);
    }

    public function test_signup_needs_confirmation_before_anything_is_sent(): void
    {
        Mail::fake();
        Store::factory()->create(['slug' => 'lidl']);

        $this->postJson('/pranesimai', ['email' => 'Senjora@Example.com', 'stores' => ['lidl', 'nera']])->assertOk()->assertJson(['sent' => true]);

        $subscriber = EmailSubscriber::firstOrFail();
        $this->assertSame('senjora@example.com', $subscriber->email);
        $this->assertSame(['lidl'], $subscriber->store_slugs);
        $this->assertNull($subscriber->confirmed_at);
        Mail::assertSent(WeeklyDigestConfirmMail::class, fn ($mail) => str_contains($mail->confirmUrl, $subscriber->token));

        $this->get('/pranesimai/patvirtinti/'.$subscriber->token)->assertRedirect('/pranesimai/'.$subscriber->token.'?patvirtinta=1');
        $this->assertNotNull($subscriber->fresh()->confirmed_at);
    }

    public function test_someone_else_cannot_change_a_confirmed_address_through_the_form(): void
    {
        Mail::fake();
        Store::factory()->create(['slug' => 'lidl']);
        Store::factory()->create(['slug' => 'iki']);
        EmailSubscriber::create(['email' => 'a@example.com', 'token' => 't1', 'store_slugs' => ['lidl'], 'confirmed_at' => now()]);

        $this->postJson('/pranesimai', ['email' => 'a@example.com', 'stores' => ['iki']])->assertOk();

        $this->assertSame(['lidl'], EmailSubscriber::first()->store_slugs);
    }

    public function test_signed_in_user_with_the_same_address_skips_confirmation(): void
    {
        Mail::fake();
        $user = User::factory()->create(['email' => 'b@example.com', 'email_verified_at' => now()]);

        $this->actingAs($user)->postJson('/pranesimai', ['email' => 'b@example.com'])->assertOk()->assertJson(['confirmed' => true]);

        $this->assertNotNull(EmailSubscriber::first()->confirmed_at);
        Mail::assertNothingSent();
    }

    public function test_settings_page_saves_stores_and_emails_and_can_unsubscribe(): void
    {
        Store::factory()->create(['slug' => 'rimi']);
        $user = User::factory()->create(['price_watch_unsubscribed_at' => now()]);
        $subscriber = EmailSubscriber::create(['email' => 'c@example.com', 'token' => 't2', 'user_id' => $user->id, 'confirmed_at' => now()]);

        $this->get('/pranesimai/t2')->assertOk()->assertSee('Pranešimai el. paštu');

        $this->post('/pranesimai/t2', ['stores' => ['rimi'], 'wants_new_leaflets' => '1', 'wants_price_drops' => '1'])->assertRedirect('/pranesimai/t2');
        $subscriber->refresh();
        $this->assertSame(['rimi'], $subscriber->store_slugs);
        $this->assertFalse($subscriber->wants_weekly);
        $this->assertTrue($subscriber->wants_new_leaflets);
        $this->assertNull($user->fresh()->price_watch_unsubscribed_at);

        $this->post('/pranesimai/t2/atsisakyti')->assertRedirect('/pranesimai/t2');
        $this->assertNotNull($subscriber->fresh()->unsubscribed_at);
        $this->assertNotNull($user->fresh()->price_watch_unsubscribed_at);
    }

    public function test_signed_in_user_opens_own_settings_without_being_signed_up(): void
    {
        $user = User::factory()->create(['email' => 'f@example.com', 'preferred_store_slugs' => ['iki']]);

        $response = $this->actingAs($user)->get('/pranesimai');

        $subscriber = EmailSubscriber::firstOrFail();
        $response->assertRedirect('/pranesimai/'.$subscriber->token);
        $this->assertSame($user->id, $subscriber->user_id);
        $this->assertSame(['iki'], $subscriber->store_slugs);
        $this->assertFalse($subscriber->wants_weekly);
        $this->assertFalse($subscriber->wants_new_leaflets);

        $this->actingAs($user)->get('/pranesimai')->assertRedirect('/pranesimai/'.$subscriber->token);
        $this->assertSame(1, EmailSubscriber::count());
    }

    public function test_weekly_email_goes_to_confirmed_subscribers_once_a_week(): void
    {
        Mail::fake();
        $store = Store::factory()->create(['slug' => 'maxima', 'name' => 'Maxima']);
        $this->flyer($store);
        $discount = Discount::factory()->create(['store_id' => $store->id, 'product_id' => Product::factory()->create(['category_id' => Category::factory()->create()->id])->id]);
        CuratedDeal::create(['store_id' => $store->id, 'scope' => 'store_top_offers', 'position' => 0, 'discount_id' => $discount->id, 'deal_score' => 10]);
        EmailSubscriber::create(['email' => 'd@example.com', 'token' => 't3', 'store_slugs' => ['maxima'], 'confirmed_at' => now()]);
        EmailSubscriber::create(['email' => 'unconfirmed@example.com', 'token' => 't4', 'store_slugs' => ['maxima']]);

        $this->artisan('weekly-digest:send')->assertSuccessful();
        $this->artisan('weekly-digest:send')->assertSuccessful();

        Mail::assertSent(WeeklyDigestMail::class, 1);
        Mail::assertSent(WeeklyDigestMail::class, fn ($mail) => $mail->hasTo('d@example.com') && count($mail->leaflets) === 1 && $mail->offers->count() === 1);
    }

    public function test_new_leaflet_is_announced_once_and_only_if_added_after_signup(): void
    {
        Mail::fake();
        $store = Store::factory()->create(['slug' => 'norfa', 'name' => 'Norfa']);
        $old = $this->flyer($store);
        $old->forceFill(['created_at' => now()->subDays(3)])->save();
        EmailSubscriber::create(['email' => 'e@example.com', 'token' => 't5', 'store_slugs' => ['norfa'], 'confirmed_at' => now()->subDay()]);
        $new = $this->flyer($store);

        $this->artisan('leaflets:notify-subscribers')->assertSuccessful();
        $this->artisan('leaflets:notify-subscribers')->assertSuccessful();

        Mail::assertSent(NewLeafletMail::class, 1);
        Mail::assertSent(NewLeafletMail::class, fn ($mail) => count($mail->leaflets) === 1 && str_contains($mail->leaflets[0]['url'], $new->slug));
    }
}
