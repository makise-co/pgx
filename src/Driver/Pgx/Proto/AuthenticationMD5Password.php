<?php

declare(strict_types=1);

namespace MakiseCo\Postgres\Driver\Pgx\Proto;

use BitWasp\Buffertools\Parser;
use BitWasp\Buffertools\Types\Uint32;
use PHPinnacle\Buffer\ByteBuffer;
use Swow\Buffer;

use const MakiseCo\Postgres\Driver\Pgx\AuthTypeCleartextPassword;
use const MakiseCo\Postgres\Driver\Pgx\AuthTypeMD5Password;
use const MakiseCo\Postgres\Driver\Pgx\AuthTypeOk;

class AuthenticationMD5Password implements BackendMessage
{
    /**
     * 4-byte salt
     * @var string
     */
    public string $salt;

    public function decode(string $data)
    {
        if (strlen($data) !== 8) {
            throw new \InvalidArgumentException('bad authentication message size');
        }

        $buffer = new ByteBuffer($data);
        $authType = $buffer->readInt32();

        if ($authType !== AuthTypeMD5Password) {
            throw new \InvalidArgumentException('bad auth type');
        }

        $this->salt = $buffer->read(4, 4);
    }

    public function encode(string $dst): string
    {
        // TODO: Implement encode() method.
    }
}