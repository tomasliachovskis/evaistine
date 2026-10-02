<?php

namespace Tests\Feature;

use App\Mail\MagicLinkMail;
use App\Models\MagicLoginLink;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class MagicLoginCodeTest extends TestCase
{
    use RefreshDatabase;

    private function requestEmail(string $email = 'senjoras@example.com'): MagicLinkMail
    {
        Mail::fake();
        $this->postJson('/login', ['email' => $email])->assertOk();

        $sent = null;
        Mail::assertSent(MagicLinkMail::class, function (MagicLinkMail $mail) use (&$sent) {
            $sent = $mail;

            return true;
        });

        return $sent;
    }

    public function test_email_carries_a_six_digit_code_next_to_the_link(): void
    {
        $mail = $this->requestEmail();

        $this->assertMatchesRegularExpression('/^\d{6}$/', $mail->code);
        $this->assertStringContainsString('/auth/magic-link/', $mail->loginUrl);
    }

    public function test_the_code_logs_in_and_creates_the_account(): void
    {
        $mail = $this->requestEmail();

        // Typed with a space, the way the email shows it.
        $this->postJson('/login/code', ['email' => 'senjoras@example.com', 'code' => substr($mail->code, 0, 3).' '.substr($mail->code, 3)])
            ->assertOk()
            ->assertJson(['redirect' => '/favorites']);

        $this->assertAuthenticatedAs(User::where('email', 'senjoras@example.com')->firstOrFail());
    }

    public function test_using_the_code_uses_the_link_too(): void
    {
        $mail = $this->requestEmail();
        $this->postJson('/login/code', ['email' => 'senjoras@example.com', 'code' => $mail->code])->assertOk();
        auth()->logout();

        $this->get($mail->loginUrl)->assertRedirect('/?login=1&magic_link_expired=1');
    }

    public function test_using_the_link_retires_the_code(): void
    {
        $mail = $this->requestEmail();
        $this->get($mail->loginUrl)->assertRedirect('/favorites');
        auth()->logout();

        $this->postJson('/login/code', ['email' => 'senjoras@example.com', 'code' => $mail->code])
            ->assertStatus(422)
            ->assertJsonPath('errors.code.0', 'Šis kodas nebegalioja. Paspauskite „Siųsti naują laišką“.');
        $this->assertGuest();
    }

    public function test_five_wrong_codes_block_the_right_one(): void
    {
        $mail = $this->requestEmail();
        $wrong = $mail->code === '000000' ? '111111' : '000000';

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/login/code', ['email' => 'senjoras@example.com', 'code' => $wrong])->assertStatus(422);
        }

        $this->postJson('/login/code', ['email' => 'senjoras@example.com', 'code' => $mail->code])->assertStatus(422);
        $this->assertGuest();
    }

    public function test_a_new_email_retires_the_older_code(): void
    {
        $first = $this->requestEmail();
        $second = $this->requestEmail();

        if ($first->code !== $second->code) {
            $this->postJson('/login/code', ['email' => 'senjoras@example.com', 'code' => $first->code])->assertStatus(422);
        }
        $this->postJson('/login/code', ['email' => 'senjoras@example.com', 'code' => $second->code])->assertOk();
    }

    public function test_an_expired_code_is_refused(): void
    {
        $mail = $this->requestEmail();
        MagicLoginLink::query()->update(['expires_at' => now()->subMinute()]);

        $this->postJson('/login/code', ['email' => 'senjoras@example.com', 'code' => $mail->code])->assertStatus(422);
        $this->assertGuest();
    }

    public function test_a_code_for_another_address_does_not_work(): void
    {
        $mail = $this->requestEmail('a@example.com');

        $this->postJson('/login/code', ['email' => 'b@example.com', 'code' => $mail->code])->assertStatus(422);
        $this->assertGuest();
    }
}
