<?php

declare(strict_types=1);

namespace MakiseCo\Postgres\Driver\Pgx\Proto;

use PHPinnacle\Buffer\ByteBuffer;

class DataRow implements BackendMessage
{
    /**
     * @var array<int, string>
     */
    public array $values = [];

    public function decode(string $data)
    {
        $this->values = [];

        if (strlen($data) < 2) {
            throw new Exception\InvalidMessageFormat('DataRow');
        }

        $buffer = new ByteBuffer($data);
        $fieldCount = $buffer->consumeInt16();

        for ($i = 0; $i < $fieldCount; $i++) {
            $messageSize = $buffer->consumeInt32();

            if ($messageSize === -1) {
                $this->values[$i] = null;
            } else {
                $this->values[$i] = $buffer->consume($messageSize);
            }
        }
    }

    public function encode(string $dst): string
    {
    }
}