<?php

declare(strict_types=1);

namespace MakiseCo\Postgres\Driver\Pgx\Proto;

/**
 * THIS CLASS IS NOT A MESSAGE, IT'S A PART OF RowDescription class
 */
class FieldDescription
{
    public function __construct(
        public string $name,
        public int $tableOID,
        public int $tableAttributeNumber,
        public int $dataTypeOID,
        public int $dataTypeSize,
        public int $typeModifier,
        public int $format,
    ) {
    }
}