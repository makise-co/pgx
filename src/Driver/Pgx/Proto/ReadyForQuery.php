<?php

declare(strict_types=1);

namespace MakiseCo\Postgres\Driver\Pgx\Proto;

use PHPinnacle\Buffer\ByteBuffer;

class ReadyForQuery implements BackendMessage
{
    /**
     * 1 byte
     */
    public int $txStatus = 0;

    public function decode(string $data)
    {
        if (strlen($data) !== 1) {
            throw new Exception\InvalidMessageLen(
                messageType: 'ReadyForQuery',
                expectedLen: 1,
                actualLen: strlen($data),
            );
        }

        $buffer = new ByteBuffer($data);
        $this->txStatus = $buffer->consumeInt8();
    }

    public function encode(string $dst): string
    {
        // TODO: Implement encode() method.
    }
}