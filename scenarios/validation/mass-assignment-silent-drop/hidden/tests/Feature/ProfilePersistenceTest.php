<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Encodes the persistence contract: every validated profile field persists,
 * and no unvalidated field becomes mass-assignable.
 */
class ProfilePersistenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_timezone_and_consent_really_persist(): void
    {
        $user = User::create(['name' => 'Kim', 'email' => 'kim@example.test', 'password' => 'secret']);

        $response = $this->actingAs($user)->patchJson('/profile', [
            'name' => 'Kim',
            'timezone' => 'Europe/Berlin',
            'marketing_opt_in' => true,
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Kim',
            'timezone' => 'Europe/Berlin',
            'marketing_opt_in' => true,
        ]);
    }

    public function test_privileged_columns_cannot_be_set_through_the_profile_endpoint(): void
    {
        $user = User::create(['name' => 'Kim', 'email' => 'kim@example.test', 'password' => 'secret']);

        $this->actingAs($user)->patchJson('/profile', [
            'name' => 'Kim',
            'timezone' => 'Europe/Berlin',
            'marketing_opt_in' => true,
            'is_admin' => true,
        ])->assertOk();

        $this->assertFalse(
            (bool) $user->fresh()->is_admin,
            'The profile endpoint wrote is_admin=true: the fix opened a privilege-escalation hole. Only validated, legitimate fields may become mass-assignable.',
        );
    }
}
