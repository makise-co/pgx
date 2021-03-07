<?php

declare(strict_types=1);

namespace MakiseCo\Postgres\Driver\Pgx\Proto;

class ErrorResponse implements BackendMessage
{
    public string $severity = '';
    public string $code = '';
    public string $message = '';
    public string $detail = '';
    public string $hint = '';
    public int $position = 0;
    public int $internalPosition = 0;
    public string $internalQuery = '';
    public string $where = '';
    public string $schemaName = '';
    public string $tableName = '';
    public string $columnName = '';
    public string $dataTypeName = '';
    public string $constraintName = '';
    public string $file = '';
    public int $line = 0;
    public string $routine = '';

    /**
     * @var array<int,string>
     */
    public array $unknownFields;

    protected function reset(): void
    {
        $this->severity = '';
        $this->code = '';
        $this->message = '';
        $this->detail = '';
        $this->hint = '';
        $this->position = 0;
        $this->internalPosition = 0;
        $this->internalQuery = '';
        $this->where = '';
        $this->schemaName = '';
        $this->tableName = '';
        $this->columnName = '';
        $this->dataTypeName = '';
        $this->constraintName = '';
        $this->file = '';
        $this->line = 0;
        $this->routine = '';
    }

    public function decode(string $data)
    {
        $this->reset();

        $offset = 0;

        for (;;) {
            $key = $data[$offset++];
            if ($key === "\0") {
                break;
            }

            $value = '';
            while (true) {
                $char = $data[$offset++];
                if ($char === "\0") {
                    break;
                }

                $value .= $char;
            }

            match ($key) {
                'S' => $this->severity = $value,
                'C' => $this->code = $value,
                'M' => $this->message = $value,
                'D' => $this->detail = $value,
                'H' => $this->hint = $value,
                'P' => $this->position = (int)$value,
                'p' => $this->internalPosition = (int)$value,
                'q' => $this->internalQuery = $value,
                'W' => $this->where = $value,
                's' => $this->schemaName = $value,
                't' => $this->tableName = $value,
                'c' => $this->columnName = $value,
                'd' => $this->dataTypeName = $value,
                'n' => $this->constraintName = $value,
                'F' => $this->file = $value,
                'L' => $this->line = (int)$value,
                'R' => $this->routine = $value,
                default => $this->unknownFields[$key] = $value,
            };
        }
    }

    public function encode(string $dst): string
    {
        // TODO: Implement encode() method.
    }
}