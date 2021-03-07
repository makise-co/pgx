<?php

declare(strict_types=1);

namespace MakiseCo\Postgres\Driver\Pgx\Proto\Exception;

use InvalidArgumentException;

class InvalidMessageFormat extends InvalidArgumentException
{
    public function __construct(
        private string $messageType,
    ) {
        parent::__construct(
            sprintf(
                '%s body is invalid',
                $this->messageType,
            )
        );
    }

    public function getMessageType(): string
    {
        return $this->messageType;
    }
}