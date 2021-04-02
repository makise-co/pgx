<?php

declare(strict_types=1);

namespace MakiseCo\Postgres\Driver\Pgx\Proto;

use PHPinnacle\Buffer\ByteBuffer;

class ParameterDescription implements BackendMessage
{
    /**
     * @var array<int>
     */
    public array $parameterOIDs = [];

    public function decode(string $data)
    {
        $this->parameterOIDs = [];

        if (strlen($data) < 2) {
            throw new Exception\InvalidMessageFormat('ParameterDescription');
        }

        $buffer = new ByteBuffer($data);
        // Reported parameter count will be incorrect when number of args is greater than uint16
        $buffer->consume(2);
        // Instead infer parameter count by remaining size of message
        $parameterCount = $buffer->size() / 4;

        for ($i = 0; $i < $parameterCount; $i++) {
            $this->parameterOIDs[] = $buffer->consumeInt32();
        }
    }

    public function encode(string $dst): string
    {
        // TODO: Implement encode() method.
    }
}