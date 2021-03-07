<?php

declare(strict_types=1);

namespace MakiseCo\Postgres\Driver\Pq;

use MakiseCo\Postgres\Config;
use MakiseCo\Postgres\ConnectionException;
use pq;

class Connector
{
    public function connect(Config $config): Connection
    {
        try {
            $pq = new pq\Connection(
                $config->getDsn(),
                pq\Connection::ASYNC
            );
        } catch (pq\Exception $e) {
            throw new ConnectionException('Could not connect to PostgreSQL server', 0, $e);
        }

        $pq->nonblocking = true;

        // wait until the stream becomes writable
        $w = [$pq->socket];
        $r = $e = null;

        if (stream_select($r, $w, $e, null)) {
            // loop until the connection is established
            while (true) {
                switch ($pq->poll()) {
                    case pq\Connection::POLLING_READING:
                        // we should wait for the stream to be read-ready
                        $r = [$pq->socket];
                        stream_select($r, $w, $e, null);
                        break;

                    case pq\Connection::POLLING_WRITING:
                        // we should wait for the stream to be write-ready
                        $w = [$pq->socket];
                        $r = $e = null;
                        stream_select($r, $w, $e, null);
                        break;

                    case pq\Connection::POLLING_FAILED:
                        throw new ConnectionException($pq->errorMessage);

                    case pq\Connection::POLLING_OK:
                        break 2;
                }
            }
        }

        return new Connection(handle: $pq, config: $config);
    }
}