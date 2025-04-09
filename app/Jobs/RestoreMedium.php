<?php

namespace App\Jobs;

use App\Models\Disk;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

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
    abstract public function handle(): void;

    /**
     * Get the meta data for the medium.
     */
    // abstract public function meta(): array;
}
