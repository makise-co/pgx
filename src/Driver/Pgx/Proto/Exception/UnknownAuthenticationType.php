<?php

declare(strict_types=1);

namespace MakiseCo\Postgres\Driver\Pgx\Proto\Exception;

class UnknownAuthenticationType extends \InvalidArgumentException
{
    private int $type;

    public function __construct(int $type)
    {
        $this->type = $type;

        parent::__construct(
            'Unknown authentication type: ' . $type,
        );
    }

    public function getType(): int
    {
        return $this->type;
    }
}