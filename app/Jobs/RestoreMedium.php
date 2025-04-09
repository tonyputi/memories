<?php

namespace App\Jobs;

use App\Models\Disk;
use App\Models\Medium;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\File;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

abstract class RestoreMedium implements ShouldQueue
{
    use Batchable, Queueable;

    /**
     * The number of seconds the job can run before timing out.
     *
     * @var int
     */
    public $timeout = 300;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public string $path,
        public Disk $disk,
    ) {
        //
    }

    /**
     * Execute the job.
     */
    /**
     * Execute the job.
     */
    public function handle(): void
    {
        if ($this->batch()->cancelled()) {
            return;
        }

        if (! file_exists($this->path)) {
            Log::error("File {$this->path} does not exist...");

            return;
        }

        $meta = $this->meta();
        $hash = md5_file($this->path);
        $path = Str::lower(sprintf('%s.%s', $hash, pathinfo($this->path, PATHINFO_EXTENSION)));

        if (! $this->disk->storage()->putFileAs('/', new File($this->path), $path)) {
            Log::error("Failed to copy file {$this->path} to {$path}...");

            return;
        }

        Medium::updateOrCreate([
            'disk_id' => $this->disk->getKey(),
            'name' => data_get($meta, 'filename', basename($this->path)),
            'hash' => $hash,
            'path' => $path,
        ], [
            'meta' => $meta,
            'created_at' => Carbon::parse(data_get($meta, 'taken_at') ?? filemtime($this->path)),
            'updated_at' => Carbon::parse(data_get($meta, 'taken_at') ?? filemtime($this->path)),
        ]);
    }

    /**
     * Get the meta data for the medium.
     */
    abstract public function meta(): array;
}
