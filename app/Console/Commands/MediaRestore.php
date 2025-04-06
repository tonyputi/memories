<?php

namespace App\Console\Commands;

use App\Jobs\RestoreMedia;
use App\Models\Disk;
use Illuminate\Console\Command;
use Illuminate\Contracts\Console\PromptsForMissingInput;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

use function Laravel\Prompts\search;

class MediaRestore extends Command implements PromptsForMissingInput
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'media:restore {file} {disk} {--d|delete}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Restore media from an archive file';

    /**
     * Prompt for missing input arguments using the returned questions.
     *
     * @return array<string, string>
     */
    protected function promptForMissingArgumentsUsing(): array
    {
        return [
            'file' => fn () => search(
                label: 'Which file should be imported?',
                placeholder: 'Search for a file...',
                options: fn ($v) => $this->availableArchives($v)->all(),
            ),
            'disk' => fn () => search(
                label: 'Which disk should the media be imported to?',
                placeholder: 'Search for a disk...',
                options: fn ($v) => $this->availableDisks($v)->all(),
            ),
        ];
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $file = $this->argument('file');
        $disk = $this->argument('disk');
        $delete = $this->option('delete') ?? false;

        RestoreMedia::dispatchSync($file, $disk, $delete);
    }

    /**
     * Get the available archives.
     */
    public function availableArchives(string $value): Collection
    {
        $storage = Storage::disk('uploads');
        $allowedFormats = ['application/zip'];

        return collect($storage->allFiles())
            ->reject(fn ($file) => ! in_array($storage->mimeType($file), $allowedFormats))
            ->filter(fn ($file) => str_starts_with($file, $value))
            ->values();
    }

    /**
     * Get the available disks.
     */
    public function availableDisks(string $value): Collection
    {
        return Disk::query()
            ->where('name', 'like', '%'.$value.'%')
            ->pluck('name', 'id');
    }
}
