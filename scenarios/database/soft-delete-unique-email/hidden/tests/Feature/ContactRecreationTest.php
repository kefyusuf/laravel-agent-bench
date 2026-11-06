<?php

namespace Tests\Feature;

use App\Models\Contact;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Encodes the soft-delete-aware uniqueness invariant: a trashed email must be
 * reusable, while two active contacts with the same email remain impossible.
 */
class ContactRecreationTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_soft_deleted_contact_email_can_be_recreated(): void
    {
        $contact = Contact::create(['name' => 'Kim Bay', 'email' => 'kim@example.test']);
        $contact->delete();

        $response = $this->postJson('/contacts', ['name' => 'Kim Bay (returning)', 'email' => 'kim@example.test']);

        $this->assertSame(
            201,
            $response->status(),
            'Re-creating a soft-deleted contact failed with HTTP '.$response->status().': uniqueness must be scoped to active rows at both the database index and the validation rule.',
        );

        $this->assertDatabaseHas('contacts', ['email' => 'kim@example.test', 'deleted_at' => null]);
    }

    public function test_two_active_contacts_with_the_same_email_are_still_rejected(): void
    {
        Contact::create(['name' => 'First', 'email' => 'dupe@example.test']);

        $response = $this->postJson('/contacts', ['name' => 'Second', 'email' => 'dupe@example.test']);

        $this->assertNotSame(
            201,
            $response->status(),
            'Two active contacts share an email; uniqueness for active rows must still be enforced.',
        );
    }

    public function test_recreating_after_two_soft_deletes_still_works(): void
    {
        Contact::create(['name' => 'Cycle', 'email' => 'revolving@example.test'])->delete();

        $this->travel(1)->minutes(); // ensure distinct deletion timestamps

        Contact::create(['name' => 'Cycle', 'email' => 'revolving@example.test'])->delete();

        $this->travel(1)->minutes();

        $response = $this->postJson('/contacts', ['name' => 'Cycle (third)', 'email' => 'revolving@example.test']);

        $this->assertSame(
            201,
            $response->status(),
            'Repeated delete/recreate cycles must not wedge the unique constraint (HTTP '.$response->status().').',
        );
    }
}
