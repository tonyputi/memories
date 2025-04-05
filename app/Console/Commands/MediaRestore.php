<?php

namespace App\Console\Commands;

use App\Jobs\RestoreMedia;
use App\Models\Disk;
use Illuminate\Console\Command;
use Illuminate\Contracts\Console\PromptsForMissingInput;
use Illuminate\Support\Facades\Storage;

use function Laravel\Prompts\search;

class MediaRestore extends Command implements PromptsForMissingInput
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'media:restore {disk} {file}';

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
            'disk' => fn () => search(
                label: 'Which disk should the media be imported to?',
                placeholder: 'Search for a disk...',
                options: fn ($v) => strlen($v) > 0
                    ? Disk::where('name', 'like', "%{$v}%")->pluck('name', 'id')->all()
                    : [],
            ),
            'file' => fn () => search(
                label: 'Which file should be imported?',
                placeholder: 'Search for a file...',
                options: fn ($v) => strlen($v) > 0
                    ? Storage::disk('uploads')->allFiles()
                    : [],
                validate: function ($v) {
                    $validMimeTypes = ['application/zip', 'application/x-zip-compressed'];
                    $storage = Storage::disk('uploads');

                    if (! $storage->exists($v)) {
                        return 'File does not exist';
                    }

                    if (! in_array($storage->mimeType($v), $validMimeTypes)) {
                        return 'File is not a valid archive file';
                    }

                    return null;
                },
            ),
        ];
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        RestoreMedia::dispatch($this->argument('file'), $this->argument('disk'));
    }
}
