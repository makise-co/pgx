<?php

declare(strict_types=1);

namespace MakiseCo\Postgres\Driver\Pgx\Proto;

class BindComplete implements BackendMessage
{
    public function decode(string $data)
    {
        if ($data !== '') {
            throw new Exception\InvalidMessageLen('BindComplete', 0, strlen($data));
        }
    }

    public function encode(string $dst): string
    {
        // TODO: Implement encode() method.
    }
}