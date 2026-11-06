<?php

namespace Tests\Feature;

use App\Mail\UnsubscribeConfirmed;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * Encodes the consent invariant: the signed URL authorizes the action, not one
 * execution of it — replay must be idempotent (stay unsubscribed, one email).
 */
class UnsubscribeReplayTest extends TestCase
{
    use RefreshDatabase;

    public function test_replaying_the_signed_link_never_resubscribes_or_spams(): void
    {
        $user = User::create(['name' => 'Kim', 'email' => 'kim@example.test', 'password' => 'secret', 'marketing_opt_in' => true]);

        Mail::fake();

        $url = URL::temporarySignedRoute('unsubscribe', now()->addDays(7), ['user' => $user->id]);

        $this->get($url)->assertOk();

        // Email scanners, prefetchers and back-buttons replay the same URL.
        $this->get($url)->assertOk();
        $this->get($url)->assertOk();

        $this->assertFalse(
            (bool) $user->fresh()->marketing_opt_in,
            'Replaying the unsubscribe link flipped the consent flag back on: the handler must write the one-way opt-out state unconditionally, not toggle it.',
        );

        Mail::assertSent(
            UnsubscribeConfirmed::class,
            1,
        );
    }
}
