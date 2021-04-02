<?php

declare(strict_types=1);

namespace MakiseCo\Postgres\Driver\Pgx\Proto;

use PHPinnacle\Buffer\ByteBuffer;

class Sync implements FrontendMessage
{
    public function decode(string $data)
    {
        // TODO: Implement decode() method.
    }

    public function encode(string $dst): string
    {
        $buffer = new ByteBuffer($dst);

        $buffer->append('S');
        $buffer->appendInt32(4);

        return $buffer->bytes();
    }
}