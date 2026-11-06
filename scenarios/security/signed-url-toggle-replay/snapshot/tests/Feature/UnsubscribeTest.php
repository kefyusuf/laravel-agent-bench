<?php

namespace Tests\Feature;

use App\Mail\UnsubscribeConfirmed;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class UnsubscribeTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_signed_link_unsubscribes_the_user_and_confirms_once(): void
    {
        $user = User::create(['name' => 'Kim', 'email' => 'kim@example.test', 'password' => 'secret', 'marketing_opt_in' => true]);

        Mail::fake();

        $url = URL::temporarySignedRoute('unsubscribe', now()->addDays(7), ['user' => $user->id]);

        $this->get($url)->assertOk();

        $this->assertDatabaseHas('users', ['id' => $user->id, 'marketing_opt_in' => false]);
        Mail::assertSent(UnsubscribeConfirmed::class, 1);
    }

    public function test_an_unsigned_link_is_rejected(): void
    {
        $user = User::create(['name' => 'Kim', 'email' => 'kim@example.test', 'password' => 'secret', 'marketing_opt_in' => true]);

        $this->get("/unsubscribe/{$user->id}")->assertForbidden();

        $this->assertDatabaseHas('users', ['id' => $user->id, 'marketing_opt_in' => true]);
    }
}
