<?php

namespace App\Models;

use App\Models\Concerns\BelongsToDisk;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Database\Eloquent\SoftDeletes;

class Medium extends Model
{
    use BelongsToDisk, HasFactory, SoftDeletes;

    public static function booted(): void
    {
        static::saving(function (Medium $medium) {
            $storage = $medium->disk->storage();
            if ($storage->exists($medium->path)) {
                $medium->type = $storage->mimeType($medium->path);
                $medium->hash = md5_file($storage->path($medium->path));
                $medium->size = $storage->size($medium->path);
            }
        });

        static::forceDeleted(function (Medium $medium) {
            $medium->disk->storage()->delete($medium->path);
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
            'meta' => 'collection',
        ];
    }

    public function user(): HasOneThrough
    {
        return $this->hasOneThrough(User::class, Disk::class);
    }

    public function url(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $this->disk->storage()->url($this->path),
        );
    }
}
