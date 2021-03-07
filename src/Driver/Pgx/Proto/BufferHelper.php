<?php

declare(strict_types=1);

namespace MakiseCo\Postgres\Driver\Pgx\Proto;

class BufferHelper
{
    /**
     * @param string $str
     * @param int $offset
     * @return string
     */
    public static function readCString(string $str, int $offset): string
    {
        $nullTerminatorPos = strpos($str, "\0", $offset);
        if ($nullTerminatorPos === false) {
            throw new \InvalidArgumentException('str is not null-terminated');
        }

        return substr($str, $offset, $nullTerminatorPos);
    }
}