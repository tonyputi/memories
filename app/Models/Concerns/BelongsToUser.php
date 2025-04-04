<?php

namespace App\Models\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToUser
{
    /**
     * Get the user that owns the model.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
