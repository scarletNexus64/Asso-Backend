<?php

namespace Tests\Feature;

use App\Mail\OtpCodeMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class EmailAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_sends_verification_code_by_email(): void
    {
        Mail::fake();
        $response = $this->postJson('/api/v1/auth/register-email', [
            'email' => 'client@example.com', 'password' => 'secret12', 'password_confirmation' => 'secret12',
        ]);
        $response->assertOk()->assertJsonPath('success', true);
        Mail::assertSent(OtpCodeMail::class, fn ($mail) => $mail->hasTo('client@example.com'));
        $this->assertNotNull(User::where('email', 'client@example.com')->firstOrFail()->otp_code);
    }

    public function test_password_can_be_reset_with_emailed_code(): void
    {
        Mail::fake();
        $user = User::factory()->create(['email' => 'client@example.com', 'password' => Hash::make('ancien')]);
        $this->postJson('/api/v1/auth/password/forgot', ['email' => $user->email])->assertOk();
        Mail::assertSent(OtpCodeMail::class);
        $code = $user->fresh()->otp_code;

        $this->postJson('/api/v1/auth/password/reset', [
            'email' => $user->email, 'otp_code' => $code,
            'password' => 'nouveau12', 'password_confirmation' => 'nouveau12',
        ])->assertOk()->assertJsonPath('success', true);

        $this->assertTrue(Hash::check('nouveau12', $user->fresh()->password));
        $this->assertNull($user->fresh()->otp_code);
    }
}
