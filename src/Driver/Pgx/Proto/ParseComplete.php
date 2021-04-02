<?php

declare(strict_types=1);

namespace MakiseCo\Postgres\Driver\Pgx\Proto;

class ParseComplete implements BackendMessage
{
    public function decode(string $data)
    {
        if ($data !== '') {
            throw new Exception\InvalidMessageLen('ParseComplete', 0, strlen($data));
        }
    }

    public function encode(string $dst): string
    {
        // TODO: Implement encode() method.
    }
}