<?php

declare(strict_types=1);

namespace MakiseCo\Postgres\Driver\Pgx;

use MakiseCo\Postgres\Driver\Pgx\Proto\FieldDescription;

class ResultReader
{
    public function __construct(
        private $ctx,
        private PgConn $pgConn,
        /** @var array<FieldDescription> $fieldDescriptions */
        private array $fieldDescriptions,
    ) {
    }

}