<?php

declare(strict_types=1);

namespace MakiseCo\Postgres\Driver\Pgx\Proto;

use PHPinnacle\Buffer\ByteBuffer;

class EmptyQueryResponse implements BackendMessage
{
    public function decode(string $data)
    {
        if ($data !== '') {
            throw new Exception\InvalidMessageLen('EmptyQueryResponse', 0, strlen($data));
        }
    }

    public function encode(string $dst): string
    {
        $buffer = new ByteBuffer($dst);
        $buffer->append('I');
        $buffer->appendInt32(4);

        return $buffer->bytes();
    }
}