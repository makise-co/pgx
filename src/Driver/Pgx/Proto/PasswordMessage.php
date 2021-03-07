<?php

declare(strict_types=1);

namespace MakiseCo\Postgres\Driver\Pgx\Proto;

use PHPinnacle\Buffer\ByteBuffer;

class PasswordMessage implements FrontendMessage
{
    public string $password;

    public function __construct(string $password)
    {
        $this->password = $password;
    }

    public function decode(string $data)
    {
        // TODO: Implement decode() method.
    }

    public function encode(string $dst): string
    {
        $buffer = new ByteBuffer($dst);

        $buffer->append('p');
        // 4 is size of message size, 1 is null-terminator of password string
        $buffer->appendInt32(4 + strlen($this->password) + 1);

        $buffer->append($this->password);
        $buffer->appendInt8(0);

        return $buffer->bytes();
    }
}