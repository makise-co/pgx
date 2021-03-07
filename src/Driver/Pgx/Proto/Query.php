<?php

declare(strict_types=1);

namespace MakiseCo\Postgres\Driver\Pgx\Proto;

use PHPinnacle\Buffer\ByteBuffer;

class Query implements FrontendMessage
{
    public string $query;

    public function __construct(string $query)
    {
        $this->query = $query;
    }

    public function decode(string $data)
    {
        // TODO: Implement decode() method.
    }

    public function encode(string $dst): string
    {
        $buffer = new ByteBuffer($dst);

        $buffer->append('Q');
        $buffer->appendInt32(4 + strlen($this->query) + 1);
        $buffer->append($this->query);
        $buffer->appendInt8(0);

        return $buffer->bytes();
    }
}