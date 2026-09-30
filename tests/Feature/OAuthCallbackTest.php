<?php

namespace Tests\Feature;

use App\Models\User;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Psr7\Request as PsrRequest;
use GuzzleHttp\Psr7\Response as PsrResponse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use Tests\TestCase;

class OAuthCallbackTest extends TestCase
{
    use RefreshDatabase;

    public function test_redirect_to_provider_still_goes_to_google(): void
    {
        $response = $this->get('/auth/google/redirect');

        $response->assertStatus(302);
        $this->assertStringStartsWith('https://accounts.google.com/', $response->headers->get('Location'));
    }

    public function test_successful_callback_creates_and_logs_in_a_new_user(): void
    {
        $this->fakeProviderUser('google-123', 'naujas@example.com', 'Naujas Vartotojas');

        $this->get('/auth/google/callback?code=abc&state=xyz')->assertRedirect('/favorites');

        $user = User::where('email', 'naujas@example.com')->firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertSame('google', $user->oauth_provider);
        $this->assertSame('google-123', $user->oauth_provider_id);
    }

    public function test_successful_callback_logs_in_an_existing_user_by_email(): void
    {
        $existing = User::factory()->create(['email' => 'esamas@example.com']);
        $this->fakeProviderUser('google-456', 'esamas@example.com', 'Esamas');

        $this->get('/auth/google/callback?code=abc&state=xyz')->assertRedirect('/favorites');

        $this->assertAuthenticatedAs($existing);
        $this->assertSame(1, User::count());
        $this->assertSame('google-456', $existing->fresh()->oauth_provider_id);
    }

    public function test_bare_callback_url_goes_back_to_login_instead_of_500(): void
    {
        $this->get('/auth/google/callback')->assertRedirect('/?login=1');
        $this->assertGuest();
    }

    public function test_cancelled_consent_goes_back_to_login(): void
    {
        $this->get('/auth/google/callback?error=access_denied&state=xyz')->assertRedirect('/?login=1');
        $this->assertGuest();
    }

    public function test_invalid_state_goes_back_to_login(): void
    {
        $this->fakeProviderFailure(new InvalidStateException());

        $this->get('/auth/google/callback?code=abc&state=stale')->assertRedirect('/?login=1');
        $this->assertGuest();
    }

    public function test_rejected_code_goes_back_to_login(): void
    {
        $this->fakeProviderFailure(new ClientException(
            'invalid_grant',
            new PsrRequest('POST', 'https://www.googleapis.com/oauth2/v4/token'),
            new PsrResponse(400)
        ));

        $this->get('/auth/facebook/callback?code=used&state=xyz')->assertRedirect('/?login=1');
        $this->assertGuest();
    }

    private function fakeProviderUser(string $id, string $email, string $name): void
    {
        $socialUser = (new SocialiteUser())->map(['id' => $id, 'email' => $email, 'name' => $name]);

        $driver = Mockery::mock();
        $driver->shouldReceive('user')->andReturn($socialUser);
        Socialite::shouldReceive('driver')->andReturn($driver);
    }

    private function fakeProviderFailure(\Throwable $e): void
    {
        $driver = Mockery::mock();
        $driver->shouldReceive('user')->andThrow($e);
        Socialite::shouldReceive('driver')->andReturn($driver);
    }
}
