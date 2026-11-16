<?php

namespace Tests\Feature;

use App\Models\Contract;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ContractStorageTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_upload_stores_the_contract_on_the_private_disk(): void
    {
        Storage::fake('contracts');

        $response = $this->postJson('/contracts', [
            'customer_name' => 'Kim',
            'contents' => 'CONTRACT-BODY',
        ]);

        $response->assertCreated();

        $contract = Contract::first();
        Storage::disk('contracts')->assertExists($contract->path);
    }

    public function test_the_link_endpoint_returns_a_url(): void
    {
        $contract = $this->storedContract();

        $response = $this->getJson("/contracts/{$contract->id}/link");

        $response->assertOk();
        $response->assertJsonStructure(['url']);
    }

    private function storedContract(): Contract
    {
        Storage::fake('contracts');
        Storage::disk('contracts')->put('contracts/stored.txt', 'CONTRACT-BODY');

        return Contract::create(['customer_name' => 'Kim', 'path' => 'contracts/stored.txt']);
    }
}
