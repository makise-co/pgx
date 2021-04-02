<?php

declare(strict_types=1);

namespace MakiseCo\Postgres\Driver\Pgx\Proto;

use PHPinnacle\Buffer\ByteBuffer;

class Bind implements FrontendMessage
{
    /**
     * @param string $destinationPortal
     * @param string $preparedStatement
     * @param array<int> $parameterFormatCodes
     * @param array<string|null> $parameters
     * @param array<int> $resultFormatCodes
     */
    public function __construct(
        public string $destinationPortal,
        public string $preparedStatement,
        public array $parameterFormatCodes,
        public array $parameters,
        public array $resultFormatCodes,
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
        $buffer->append('B');

        // offset that contains message length
        $offset = $buffer->size();

        // message len
        $buffer->appendInt32(-1);

        // destination portal
        $buffer->append($this->destinationPortal);
        // null-terminator
        $buffer->appendInt8(0);

        // prepared statement
        $buffer->append($this->preparedStatement);
        // null-terminator
        $buffer->appendInt8(0);

        // parameter format codes count
        $buffer->appendInt16(count($this->parameterFormatCodes));
        foreach ($this->parameterFormatCodes as $parameterFormatCode) {
            $buffer->appendInt16($parameterFormatCode);
        }

        // parameters count
        $buffer->appendInt16(count($this->parameters));
        foreach ($this->parameters as $parameter) {
            if ($parameter === null) {
                // PostgreSQL representation of null value is -1 (int32)
                $buffer->appendInt32(-1);
                continue;
            }

            // parameter length
            $buffer->appendInt32(strlen($parameter));
            // parameter value
            $buffer->append($parameter);
        }

        // result format codes count
        $buffer->appendInt16(count($this->resultFormatCodes));
        foreach ($this->resultFormatCodes as $resultFormatCode) {
            $buffer->appendInt16($resultFormatCode);
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