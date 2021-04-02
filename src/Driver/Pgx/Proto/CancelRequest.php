<?php

declare(strict_types=1);

namespace MakiseCo\Postgres\Driver\Pgx\Proto;

use PHPinnacle\Buffer\ByteBuffer;

class CancelRequest implements FrontendMessage
{
    public function __construct(
        private int $pid,
        private int $secretKey,
    ) {
    }

    public function decode(string $data)
    {
        // TODO: Implement decode() method.
    }

    public function encode(string $dst): string
    {
        $buffer = new ByteBuffer($dst);

        // Length of message contents in bytes, including self.
        $buffer->appendInt32(16);

        // The cancel request code.
        // The value is chosen to contain 1234 in the most significant 16 bits,
        // and 5678 in the least significant 16 bits.
        // (To avoid confusion, this code must not be the same as any protocol version number.)
        $buffer->appendInt32(80877102);

        // The process ID of the target backend.
        $buffer->appendInt32($this->pid);

        // The secret key for the target backend.
        $buffer->appendInt32($this->secretKey);

        return $buffer->bytes();
    }
}