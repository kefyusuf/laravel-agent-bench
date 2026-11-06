<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_display_name_can_be_updated(): void
    {
        $user = User::create(['name' => 'Kim', 'email' => 'kim@example.test', 'password' => 'secret']);

        $response = $this->actingAs($user)->patchJson('/profile', [
            'name' => 'Kim renamed',
            'timezone' => 'Europe/Berlin',
            'marketing_opt_in' => true,
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('users', ['id' => $user->id, 'name' => 'Kim renamed']);
    }
}
