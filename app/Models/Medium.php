<?php

namespace App\Models;

use App\Models\Concerns\BelongsToDisk;
use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Medium extends Model
{
    use BelongsToDisk, BelongsToUser, HasFactory, SoftDeletes;

    protected $guarded = [];

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

    public function url(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => Storage::build(array_merge($this->disk->config, ['driver' => $this->disk->driver]))->url($this->path),
        );
    }
}
