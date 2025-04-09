<?php

namespace App\Models;

use App\Enums\DiskDriver;
use App\Models\Concerns\BelongsToUser;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Filesystem\ServeFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Route;
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
     * Scope a query to only include local disks.
     */
    public function scopeLocal(Builder $query): Builder
    {
        return $query->where('driver', DiskDriver::Local);
    }

    /**
     * Register the storage for the disk.
     */
    public function registerStorage(): void
    {
        $config = $this->storageConfig();
        Config::set("filesystems.disks.{$this->getKey()}", $config);
        Route::get("{$config['url']}/{path}", function (Request $request, string $path) use ($config) {
            return (new ServeFile($this->getKey(), $config, app()->isProduction()))($request, $path);
        })->where('path', '.*')->name("storage.{$this->getKey()}");
    }

    /**
     * Get the storage config for the disk.
     */
    public function storageConfig(): array
    {
        $config = $this->config;
        $config['driver'] = $this->driver->value;

        if ($this->driver === DiskDriver::Local) {
            $config['root'] = implode(DIRECTORY_SEPARATOR, [
                rtrim(data_get($config, 'root', config('filesystems.disks.local.root')), DIRECTORY_SEPARATOR),
                $this->getKey(),
            ]);

            $config['url'] = implode('/', [
                'storage',
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
        if (! Config::has("filesystems.disks.{$this->getKey()}")) {
            $this->registerStorage();
        }

        return Storage::disk($this->getKey());
    }
}
