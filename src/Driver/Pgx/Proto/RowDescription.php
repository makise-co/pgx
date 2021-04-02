<?php

declare(strict_types=1);

namespace MakiseCo\Postgres\Driver\Pgx\Proto;

use PHPinnacle\Buffer\ByteBuffer;

class RowDescription implements BackendMessage
{
    /**
     * @var array<FieldDescription>
     */
    public array $fields = [];

    public function decode(string $data): void
    {
        $this->fields = [];

        if (strlen($data) < 2) {
            throw new Exception\InvalidMessageFormat('RowDescription');
        }

        $buffer = new ByteBuffer($data);

        $fieldCount = $buffer->consumeInt16();

        for ($i = 0; $i < $fieldCount; $i++) {
            // TODO: Not performant way
            $name = BufferHelper::readCString($buffer->bytes(), 0);
            // remove bytes from buffer, +1 is null-terminator
            $buffer->consume(strlen($name) + 1);

            $tableOID = $buffer->consumeInt32();
            $tableAttributeNumber = $buffer->consumeInt16();
            $dataTypeOID = $buffer->consumeInt32();
            $dataTypeSize = $buffer->consumeInt16();
            $typeModifier = $buffer->consumeInt32();
            $format = $buffer->consumeInt16();

            $this->fields[] = new FieldDescription(
                name: $name,
                tableOID: $tableOID,
                tableAttributeNumber: $tableAttributeNumber,
                dataTypeOID: $dataTypeOID,
                dataTypeSize: $dataTypeSize,
                typeModifier: $typeModifier,
                format: $format,
            );
        }
    }

    public function encode(string $dst): string
    {
        // TODO: Implement encode() method.
    }
}