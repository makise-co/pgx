<?php

declare(strict_types=1);

namespace MakiseCo\Postgres\Driver\Pgx;

use MakiseCo\Postgres\Driver\Pgx\Proto\BackendMessage;
use MakiseCo\Postgres\Driver\Pgx\Proto\CommandComplete;
use MakiseCo\Postgres\Driver\Pgx\Proto\ErrorResponse;
use MakiseCo\Postgres\Driver\Pgx\Proto\ReadyForQuery;
use MakiseCo\Postgres\Driver\Pgx\Proto\RowDescription;

class MultiResultReader
{
    private ?ResultReader $rr = null;

    private bool $closed = false;

    public function __construct(
        private $ctx,
        private PgConn $pgConn,
    ) {
    }

    /**
     * @return array<Result>
     */
    public function readAll(): array
    {
        $results = [];

        while ($this->nextResult()) {
            $results[] = $this->resultReader()->read();
        }

        return $results;
    }

    public function nextResult(): bool
    {
        while (!$this->closed) {
            $msg = $this->receiveMessage();

            switch ($msg::class) {
                case RowDescription::class:
                    /** @var RowDescription $msg */
                    $rr = new ResultReader(
                        ctx: $this->ctx,
                        pgConn: $this->pgConn,
                        fieldDescriptions: $msg->fields,
                    );

                    $this->rr = $rr;

                    return true;
                case CommandComplete::class:
                    $rr = new ResultReader(

                    );

                    return true;
            }
        }

        return false;
    }

    public function resultReader(): ?ResultReader
    {
        return $this->rr;
    }

    private function receiveMessage(): BackendMessage
    {
        try {
            $msg = $this->pgConn->receiveMessage();
        } catch (\Throwable $e) {
            $this->closed = true;

            throw $e;
        }

        switch ($msg::class) {
            case ReadyForQuery::class:
                $this->closed = true;
                $this->pgConn->unlock();
                break;

            case ErrorResponse::class:
                throw PgConn::errorResponseToPgError($msg);
        }

        return $msg;
    }
}