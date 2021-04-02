<?php

declare(strict_types=1);

namespace MakiseCo\Postgres\Driver\Pgx\Proto;

use PHPinnacle\Buffer\ByteBuffer;

class Parse implements FrontendMessage
{
    /**
     * @param string $name
     * @param string $query
     * @param array<int> $parameterOIDs
     */
    public function __construct(
        public string $name,
        public string $query,
        public array $parameterOIDs,
    ) {
    }

    public function decode(string $data)
    {
        // TODO: Implement decode() method.
    }

    public function encode(string $dst): string
    {
        $buffer = new ByteBuffer($dst);

        // message type
        $buffer->append('P');

        // offset that contains message length
        $offset = $buffer->size();

        // message len
        $buffer->appendInt32(-1);

        $buffer->append($this->name);
        // null-terminator
        $buffer->appendInt8(0);

        $buffer->append($this->query);
        $buffer->appendInt8(0);

        $buffer->appendInt16(count($this->parameterOIDs));
        foreach ($this->parameterOIDs as $parameterOID) {
            $buffer->appendInt32($parameterOID);
        }

        $messageSize = (new ByteBuffer())
            ->appendInt32($buffer->size() - $offset)
            ->bytes();

        $result = $buffer->bytes();

        // replace message size (0xFFFFFFFF) with the correct one
        $result[$offset + 0] = $messageSize[0];
        $result[$offset + 1] = $messageSize[1];
        $result[$offset + 2] = $messageSize[2];
        $result[$offset + 3] = $messageSize[3];

        return $result;
    }
}