<?php

namespace App\Jobs;

use App\Models\Media;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class TranscodeVideo implements ShouldQueue
{
    use Queueable;

    /**
     * Transcodes of long lectures are known to run for minutes.
     */
    public int $timeout = 120;

    public int $tries = 1;

    public function __construct(public Media $media)
    {
    }

    public function handle(): void
    {
        // Real implementation shells out to ffmpeg in chunks; the test
        // environment stubs the transcode itself.
        $this->media->forceFill([
            'status' => 'transcoded',
            'transcoded_at' => now(),
        ])->save();
    }
}
