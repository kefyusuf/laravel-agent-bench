<?php

namespace Tests\Feature;

use App\Models\Contract;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Encodes the download-link invariant: the URL handed to the customer must
 * actually serve the stored bytes — for a private disk that means the
 * application's authorized streaming route, never a fabricated static path.
 */
class DownloadLinkTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('contracts');
    }

    public function test_the_link_serves_the_stored_contract(): void
    {
        Storage::disk('contracts')->put('contracts/stored.txt', 'CONTRACT-BODY');
        $contract = Contract::create(['customer_name' => 'Kim', 'path' => 'contracts/stored.txt']);

        $response = $this->getJson("/contracts/{$contract->id}/link");
        $response->assertOk();

        $url = $response->json('url');
        // keep path AND query — the signature lives in the query string
        $target = (string) (parse_url($url, PHP_URL_PATH) . (parse_url($url, PHP_URL_QUERY) ? '?' . parse_url($url, PHP_URL_QUERY) : ''));

        $fetched = $this->get($target);

        $fetched->assertOk();
        $this->assertStringContainsString(
            'stored.txt',
            (string) $fetched->headers->get('content-disposition'),
            'The download route served the link target but not the stored contract file.',
        );
    }
}
