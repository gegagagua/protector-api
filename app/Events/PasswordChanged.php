<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PasswordChanged
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly string $actorType,
        public readonly int $actorId,
        public readonly string $changedAt,
    ) {
    }
}
