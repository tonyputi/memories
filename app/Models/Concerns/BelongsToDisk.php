<?php

namespace App\Models\Concerns;

use App\Models\Disk;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToDisk
{
    /**
     * Get the disk that owns the model.
     */
    public function disk(): BelongsTo
    {
        return $this->belongsTo(Disk::class);
    }
}
