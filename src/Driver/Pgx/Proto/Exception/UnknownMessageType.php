<?php

declare(strict_types=1);

namespace MakiseCo\Postgres\Driver\Pgx\Proto\Exception;

class UnknownMessageType extends \InvalidArgumentException
{
    private string $type;

    public function __construct(string $type)
    {
        $this->type = $type;

        parent::__construct(
            'Unknown message type: 0x' . bin2hex($type),
        );
    }

    public function getType(): string
    {
        return $this->type;
    }
}