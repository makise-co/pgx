<?php

declare(strict_types=1);

namespace MakiseCo\Postgres\Driver\Pgx;

use MakiseCo\Postgres\Driver\Pgx\Proto\CommandTag;
use MakiseCo\Postgres\Driver\Pgx\Proto\FieldDescription;

class Result
{
    /**
     * Result constructor.
     * @param array<FieldDescription> $fieldDescriptions
     * @param array<array<int, string>> $rows
     * @param CommandTag $commandTag
     */
    public function __construct(
        public array $fieldDescriptions,
        public array $rows,
        public CommandTag $commandTag,
    ) {
    }
}