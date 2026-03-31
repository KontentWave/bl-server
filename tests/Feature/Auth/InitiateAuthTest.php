<?php

namespace Tests\Feature\Auth;

use App\Models\AuthChallenge;
use App\Services\AuthChallengeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class InitiateAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_it_generates_a_password_and_stores_only_a_hash(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 3, 31, 12, 0, 0, 'UTC'));

        $response = $this->postJson('/api/auth/initiate', [
            'phone_number' => '+421900123456',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('code', 'auth.initiated')
            ->assertJsonPath('data.phone_number', '+421900123456');

        $plainTextPassword = $response->json('data.password');
        $authChallenge = AuthChallenge::query()
            ->where('phone_number', '+421900123456')
            ->firstOrFail();

        $this->assertNotSame($plainTextPassword, $authChallenge->password_hash);
        $this->assertTrue($authChallenge->hasValidPassword($plainTextPassword));
        $this->assertTrue($authChallenge->expires_at->equalTo(now()->addHour()));
    }

    public function test_password_remains_valid_at_sixty_minutes_and_expires_afterwards(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 3, 31, 12, 0, 0, 'UTC'));

        [$authChallenge, $plainTextPassword] = app(AuthChallengeService::class)->issue('+421900123456');

        Carbon::setTestNow(now()->addMinutes(60));

        $this->assertTrue($authChallenge->fresh()->hasValidPassword($plainTextPassword));

        Carbon::setTestNow(now()->addSecond());

        $this->assertFalse($authChallenge->fresh()->hasValidPassword($plainTextPassword));
    }

    public function test_requesting_a_new_password_invalidates_the_previous_one(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 3, 31, 12, 0, 0, 'UTC'));

        $firstResponse = $this->postJson('/api/auth/initiate', [
            'phone_number' => '+421900123456',
        ]);

        $firstResponse
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('code', 'auth.initiated');

        $firstPassword = $firstResponse->json('data.password');

        Carbon::setTestNow(now()->addMinutes(10));

        $secondResponse = $this->postJson('/api/auth/initiate', [
            'phone_number' => '+421900123456',
        ]);

        $secondResponse
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('code', 'auth.initiated');

        $secondPassword = $secondResponse->json('data.password');
        $authChallenge = AuthChallenge::query()->where('phone_number', '+421900123456')->firstOrFail();

        $this->assertSame(1, AuthChallenge::query()->count());
        $this->assertFalse($authChallenge->hasValidPassword($firstPassword));
        $this->assertTrue($authChallenge->hasValidPassword($secondPassword));
    }
}
