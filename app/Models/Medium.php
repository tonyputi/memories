<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Medium extends Model
{
    use HasFactory, SoftDeletes;

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

    /**
     * Get the user that owns the medium.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
