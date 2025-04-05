<?php

namespace App\Jobs;

use App\Models\Disk;
use App\Models\Medium;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class RestoreMedium implements ShouldQueue
{
    use Batchable, Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public string $path,
        public string $disk_id,
    ) {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        if ($this->batch()->cancelled()) {
            return;
        }

        $disk = Disk::findOrFail($this->disk_id);

        $uploads = Storage::disk('uploads');

        if (! $uploads->exists($this->path)) {
            Log::error("File {$this->path} does not exist...");

            return;
        }

        $meta = [];

        if ($uploads->exists("{$this->path}.json")) {
            $meta = json_decode($uploads->get("{$this->path}.json"), true);
        }

        $name = basename($this->path);

        $extension = pathinfo($this->path, PATHINFO_EXTENSION);
        $hash = md5_file($uploads->path($this->path));
        $createdAt = $uploads->lastModified($this->path);
        $updatedAt = $uploads->lastModified($this->path);
        $path = Str::lower("/{$hash}.{$extension}");

        if (! copy($uploads->path($this->path), $disk->storage()->path($path))) {
            Log::error("Failed to copy file {$this->path} to {$path}...");

            return;
        }

        Medium::updateOrCreate([
            'user_id' => $disk->user_id,
            'disk_id' => $disk->getKey(),
            'name' => $name,
            'hash' => $hash,
            'path' => $path,
        ], [
            'meta' => $meta ?? [],
            'created_at' => $createdAt,
            'updated_at' => $updatedAt,
        ]);
    }
}
