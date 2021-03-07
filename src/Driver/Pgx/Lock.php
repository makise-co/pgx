<?php

declare(strict_types=1);

namespace MakiseCo\Postgres\Driver\Pgx;

use Swow\Channel;

class Lock
{
    private Channel $ch;

    public function __construct()
    {
        $this->ch = new Channel(1);
    }

    public function lock(): void
    {
        $this->ch->push(null);
    }

    public function unlock(): void
    {
        $this->ch->pop();
    }
}