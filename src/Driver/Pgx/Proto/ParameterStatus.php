<?php

declare(strict_types=1);

namespace MakiseCo\Postgres\Driver\Pgx\Proto;

class ParameterStatus implements BackendMessage
{
    public string $name = '';
    public string $value = '';

    public function decode(string $data)
    {
        $offset = 0;
        $name = BufferHelper::readCString($data, $offset);

        $offset += strlen($name);
        $value = BufferHelper::readCString($data, $offset);

        $this->name = $name;
        $this->value = $value;
    }

    public function encode(string $dst): string
    {
    }
}