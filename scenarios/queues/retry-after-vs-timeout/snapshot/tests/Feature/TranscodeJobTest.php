<?php

namespace Tests\Feature;

use App\Jobs\TranscodeVideo;
use App\Models\Media;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TranscodeJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_transcode_marks_its_media_as_transcoded(): void
    {
        $media = Media::create([
            'owner_id' => 1,
            'filename' => 'lecture-04.mp4',
            'status' => 'pending',
        ]);

        (new TranscodeVideo($media))->handle();

        $this->assertDatabaseHas('media', [
            'id' => $media->id,
            'status' => 'transcoded',
        ]);
    }

    public function test_the_transcode_queue_is_configured_for_long_jobs(): void
    {
        $connection = config('queue.connections.database');

        $this->assertSame('database', $connection['driver']);
        $this->assertArrayHasKey('retry_after', $connection);
    }
}
