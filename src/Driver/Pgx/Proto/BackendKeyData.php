<?php

declare(strict_types=1);

namespace MakiseCo\Postgres\Driver\Pgx\Proto;

use PHPinnacle\Buffer\ByteBuffer;

class BackendKeyData implements BackendMessage
{
    public int $processId;
    public int $secretKey;

    public function decode(string $data)
    {
        if (strlen($data) !== 8) {
            throw new Exception\InvalidMessageLen(
                messageType: 'BackendKeyData',
                expectedLen: 8,
                actualLen: strlen($data),
            );
        }

        $buffer = new ByteBuffer($data);

        $pid = $buffer->consumeInt32();
        $secretKey = $buffer->consumeInt32();

        $this->processId = $pid;
        $this->secretKey = $secretKey;
    }

    public function encode(string $dst): string
    {
        // TODO: Implement encode() method.
    }
}