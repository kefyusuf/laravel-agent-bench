<?php

namespace Tests\Feature;

use App\Models\Contact;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContactLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_contact_can_be_created_listed_and_deleted(): void
    {
        $response = $this->postJson('/contacts', ['name' => 'Kim Bay', 'email' => 'kim@example.test']);
        $response->assertStatus(201);

        $this->getJson('/contacts')->assertOk()->assertJsonCount(1, 'data');

        $contact = Contact::first();
        $this->deleteJson("/contacts/{$contact->id}")->assertOk();

        $this->getJson('/contacts')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_a_duplicate_active_email_is_rejected(): void
    {
        Contact::create(['name' => 'First', 'email' => 'dupe@example.test']);

        $this->postJson('/contacts', ['name' => 'Second', 'email' => 'dupe@example.test'])
            ->assertStatus(422);
    }
}
