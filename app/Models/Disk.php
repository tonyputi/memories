<?php

namespace App\Models;

use App\Enums\DiskDriver;
use App\Models\Concerns\BelongsToUser;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Disk extends Model
{
    use BelongsToUser, HasFactory, HasUuids, SoftDeletes;
    
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'config' => 'json',
            'driver' => DiskDriver::class,
        ];
    }

    /**
     * Get the storage instance for the disk.
     */
    public function storage(): Filesystem
    {
        return Storage::build(array_merge($this->config, ['driver' => $this->driver->value]));
    }
}
