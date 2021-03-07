<?php

declare(strict_types=1);

namespace MakiseCo\Postgres\Driver\Pgx\Proto;

use Swow\Buffer;

interface Message
{
    public function decode(string $data);

    public function encode(string $dst): string;
}