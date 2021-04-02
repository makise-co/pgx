<?php

declare(strict_types=1);

namespace MakiseCo\Postgres\Driver\Pgx\Proto;

use PHPinnacle\Buffer\ByteBuffer;

class Execute implements FrontendMessage
{
    public function __construct(
        public string $portal,
        public int $maxRows,
    ) {
    }

    public function decode(string $data)
    {
        // TODO: Implement decode() method.
    }

    public function encode(string $dst): string
    {
        $buffer = new ByteBuffer($dst);

        $buffer->append('E');
        // 4 - message size, strlen - size of portal in bytes, 1 - null-terminator, 4 - max rows
        $buffer->appendInt32(4 + strlen($this->portal) + 1 + 4);

        $buffer->append($this->portal);
        $buffer->appendInt8(0);

        $buffer->appendInt32($this->maxRows);

        return $buffer->bytes();
    }
}