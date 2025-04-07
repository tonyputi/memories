<?php

use App\Models\Disk;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('disks:purge', function () {
    Disk::withTrashed()->each(fn (Disk $disk) => $disk->forceDelete());
    Storage::disk('local')->deleteDirectory('*');
    Storage::disk('public')->deleteDirectory('*');
});
