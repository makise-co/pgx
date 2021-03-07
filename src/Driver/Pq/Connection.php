<?php

declare(strict_types=1);

namespace MakiseCo\Postgres\Driver\Pq;

use MakiseCo\Postgres\Config;

class Connection
{
    protected \pq\Connection $handle;
    protected Config $config;

    public function __construct(\pq\Connection $handle, Config $config)
    {
        $this->handle = $handle;
        $this->config = $config;
    }


}