<?php

namespace Tests\Feature;

use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Encodes the encryption-at-rest invariant: the raw column value must be
 * ciphertext (decryptable with the app key back to the plaintext tax ID),
 * never the plaintext itself. A homemade obfuscation (base64) also fails:
 * it is trivially reversible without the app key.
 */
class TaxIdEncryptionAtRestTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_tax_id_is_stored_as_ciphertext_decryptable_with_the_app_key(): void
    {
        $customer = Customer::create(['name' => 'Kim', 'tax_id' => 'TAX-123']);

        $raw = DB::table('customers')->where('id', $customer->id)->value('tax_id');

        $this->assertNotSame(
            'TAX-123',
            $raw,
            'The tax ID is stored as plaintext in the raw column: encryption at rest is missing. The model must declare the encrypted cast.',
        );

        $decrypted = decrypt($raw, false);

        $this->assertSame(
            'TAX-123',
            $decrypted,
            'The raw column is not app-key ciphertext: a database dump must not expose tax IDs without the application key.',
        );
    }

    public function test_the_app_level_round_trip_stays_transparent(): void
    {
        $customer = Customer::create(['name' => 'Kim', 'tax_id' => 'TAX-123']);

        $this->assertSame('TAX-123', $customer->fresh()->tax_id);
    }
}
