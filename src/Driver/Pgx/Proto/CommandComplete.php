<?php

declare(strict_types=1);

namespace MakiseCo\Postgres\Driver\Pgx\Proto;

class CommandComplete implements BackendMessage
{
    public string $commandTag = '';

    public function decode(string $data): void
    {
        try {
            $this->commandTag = BufferHelper::readCString($data, 0);
        } catch (\InvalidArgumentException) {
            throw new Exception\InvalidMessageFormat('CommandComplete');
        }
    }

    public function encode(string $dst): string
    {
        // TODO: Implement encode() method.
    }
}