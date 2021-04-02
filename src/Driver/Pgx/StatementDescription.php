<?php

declare(strict_types=1);

namespace MakiseCo\Postgres\Driver\Pgx;

class StatementDescription
{
    /**
     * StatementDescription constructor.
     * @param string $name
     * @param string $sql
     * @param int[] $paramOIDs
     * @param Proto\FieldDescription[] $fields
     */
    public function __construct(
        public string $name,
        public string $sql,
        public array $paramOIDs,
        public array $fields,
    ) {
    }
}