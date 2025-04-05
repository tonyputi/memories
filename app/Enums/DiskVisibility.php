<?php

namespace App\Enums;

enum DiskVisibility: string
{
    case Public = 'public';

    case Private = 'private';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Public => 'Public',
            self::Private => 'Private',
        };
    }
}
