<?php

declare(strict_types=1);

namespace MakiseCo\Postgres\Driver\Pgx\Proto;

use PHPinnacle\Buffer\ByteBuffer;

use const MakiseCo\Postgres\Driver\Pgx\AuthTypeCleartextPassword;

class AuthenticationCleartextPassword implements BackendMessage
{
    public function decode(string $data)
    {
        if (strlen($data) !== 4) {
            throw new \InvalidArgumentException('bad authentication message size');
        }

        $buffer = new ByteBuffer($data);
        $authType = $buffer->readInt32();

        if ($authType !== AuthTypeCleartextPassword) {
            throw new \InvalidArgumentException('bad auth type');
        }
    }

    public function encode(string $dst): string
    {
        // TODO: Implement encode() method.
    }
}