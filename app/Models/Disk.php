<?php

namespace App\Models;

use App\Enums\DiskDriver;
use App\Models\Concerns\BelongsToUser;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
class Disk extends Model
{
    use BelongsToUser, HasFactory, HasUuids, SoftDeletes;

    public static function booted(): void
    {
        static::forceDeleted(function (Disk $disk) {
            $disk->storage()->deleteDirectory('/');
        });
    }

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
     * Get the storage config for the disk.
     */
    public function storageConfig(): array
    {
        $config = $this->config;

        if ($this->driver === DiskDriver::Local) {
            $config['driver'] = $this->driver->value;
            $config['root'] = implode(DIRECTORY_SEPARATOR, [
                rtrim(data_get($config, 'root', config('filesystems.disks.local.root')), DIRECTORY_SEPARATOR),
                $this->getKey(),
            ]);
            $config['url'] = implode('/', [
                rtrim(data_get($config, 'url', config('filesystems.disks.local.url')), '/'),
                $this->getKey(),
            ]);
        }

        return $config;
    }

    /**
     * Get the storage instance for the disk.
     */
    public function storage(): Filesystem
    {
        Config::set("filesystems.disks.{$this->getKey()}", $this->storageConfig());
        return Storage::disk($this->getKey());
    }
}
