<?php

declare(strict_types=1);

namespace App\ApiResource;

class Login
{
    public function __construct(
        public readonly string $username,
        public readonly string $password,
    ) {
    }
}
