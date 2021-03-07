<?php

declare(strict_types=1);

namespace MakiseCo\Postgres\Driver\Pgx\Proto;

use PHPinnacle\Buffer\ByteBuffer;

class StartupMessage implements FrontendMessage
{
    public const ProtocolVersionNumber = 196608; // 3.0

    public int $protocolVersion;

    /**
     * @var array<string,string>
     */
    public array $parameters;

    public function __construct(int $protocolVersion, array $parameters)
    {
        $this->protocolVersion = $protocolVersion;
        $this->parameters = $parameters;
    }

    public function decode(string $data)
    {
    }

    public function encode(string $dst): string
    {
        $buffer = new ByteBuffer($dst);
        $buffer->appendInt32(-1);
        $buffer->appendInt32($this->protocolVersion);

        // offset that contains message length
        $offset = strlen($dst);

        // write parameters
        foreach ($this->parameters as $key => $value) {
            $buffer->append($key);
            $buffer->appendInt8(0);

            $buffer->append($value);
            $buffer->appendInt8(0);
        }

        // end of parameters
        $buffer->appendInt8(0);

        $messageSize = (new ByteBuffer())
            ->appendInt32($buffer->size() - $offset)
            ->bytes();

        $result = $buffer->bytes();

        // replace message size (0xFFFFFFFF) with the correct one
        $result[0] = $messageSize[0];
        $result[1] = $messageSize[1];
        $result[2] = $messageSize[2];
        $result[3] = $messageSize[3];

        return $result;
    }
}