<?php

declare(strict_types=1);

namespace MakiseCo\Postgres\Driver\Pgx\Proto;

use PHPinnacle\Buffer\ByteBuffer;

class Describe implements FrontendMessage
{
    /**
     * Prepared statement
     */
    public const OBJECT_TYPE_STATEMENT = 'S';

    /**
     * Portal
     */
    public const OBJECT_TYPE_PORTAL = 'P';

    /**
     * @param string $objectType ('S' = prepared statement, 'P' = portal)
     * @param string $name
     */
    public function __construct(
        public string $objectType,
        public string $name,
    ) {
    }

    public function decode(string $data)
    {
        // TODO: Implement decode() method.
    }

    public function encode(string $dst): string
    {
        if ($this->objectType !== self::OBJECT_TYPE_STATEMENT && $this->objectType !== self::OBJECT_TYPE_PORTAL) {
            throw new Exception\InvalidMessageFormat('Describe');
        }

        $buffer = new ByteBuffer($dst);
        $buffer->append('D');
        // 4 - message size, 1 - objectType, strlen - size of object name in bytes, 1 - null-terminator
        $buffer->appendInt32(4 + 1 + strlen($this->name) + 1);
        $buffer->append($this->objectType);
        $buffer->append($this->name);
        $buffer->appendInt8(0);

        return $buffer->bytes();
    }
}