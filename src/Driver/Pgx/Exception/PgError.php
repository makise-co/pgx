<?php

declare(strict_types=1);

namespace MakiseCo\Postgres\Driver\Pgx\Exception;

class PgError extends \Exception
{
    public string $severity;
    public string $pgCode;
    public string $pgMessage;
    public string $detail;
    public string $hint;
    public int $position;
    public int $internalPosition;
    public string $internalQuery;
    public string $where;
    public string $schemaName;
    public string $tableName;
    public string $columnName;
    public string $dataTypeName;
    public string $constraintName;
    public string $pgFile;
    public int $pgLine;
    public string $routine;

    public function __construct(
        string $severity,
        string $pgCode,
        string $pgMessage,
        string $detail,
        string $hint,
        int $position,
        int $internalPosition,
        string $internalQuery,
        string $where,
        string $schemaName,
        string $tableName,
        string $columnName,
        string $dataTypeName,
        string $constraintName,
        string $pgFile,
        int $pgLine,
        string $routine
    ) {
        $this->severity = $severity;
        $this->pgCode = $pgCode;
        $this->pgMessage = $pgMessage;
        $this->detail = $detail;
        $this->hint = $hint;
        $this->position = $position;
        $this->internalPosition = $internalPosition;
        $this->internalQuery = $internalQuery;
        $this->where = $where;
        $this->schemaName = $schemaName;
        $this->tableName = $tableName;
        $this->columnName = $columnName;
        $this->dataTypeName = $dataTypeName;
        $this->constraintName = $constraintName;
        $this->pgFile = $pgFile;
        $this->pgLine = $pgLine;
        $this->routine = $routine;

        parent::__construct(
            "{$severity}: {$pgMessage} (SQLSTATE {$pgCode})",
        );
    }
}