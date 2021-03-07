<?php

declare(strict_types=1);

namespace MakiseCo\Postgres\Driver\Pgx\Proto\Exception;

use InvalidArgumentException;

class InvalidMessageLen extends InvalidArgumentException
{
    private string $messageType;
    private int $expectedLen;
    private int $actualLen;

    public function __construct(string $messageType, int $expectedLen, int $actualLen)
    {
        $this->messageType = $messageType;
        $this->expectedLen = $expectedLen;
        $this->actualLen = $actualLen;

        parent::__construct(
            sprintf(
                '%s body must have length of %d, but it is %d',
                $this->messageType,
                $this->expectedLen,
                $this->actualLen
            )
        );
    }

    public function getMessageType(): string
    {
        return $this->messageType;
    }

    public function getExpectedLen(): int
    {
        return $this->expectedLen;
    }

    public function getActualLen(): int
    {
        return $this->actualLen;
    }
}