<?php

declare(strict_types=1);

namespace MakiseCo\Postgres\Driver\Pgx;

use MakiseCo\Postgres\Driver\Pgx\Proto\BackendMessage;
use MakiseCo\Postgres\Driver\Pgx\Proto\CommandComplete;
use MakiseCo\Postgres\Driver\Pgx\Proto\EmptyQueryResponse;
use MakiseCo\Postgres\Driver\Pgx\Proto\ErrorResponse;
use MakiseCo\Postgres\Driver\Pgx\Proto\ReadyForQuery;
use MakiseCo\Postgres\Driver\Pgx\Proto\RowDescription;

/**
 * MultiResultReader is a reader for a command that could return multiple results such as Exec or ExecBatch.
 */
class MultiResultReader
{
    private ?ResultReader $rr = null;
    private bool $closed = false;

    public function __construct(
        private $ctx,
        private PgConn $pgConn,
    ) {
    }

    public function __destruct()
    {
        $this->close();
    }

    public function close(): void
    {
        while (!$this->closed) {
            $this->receiveMessage();
        }
    }

    /**
     * @return array<Result>
     */
    public function readAll(): array
    {
        $results = [];

        while ($this->nextResult()) {
            $results[] = $this->getResultReader()->read();
        }

        $this->close();

        return $results;
    }

    public function nextResult(): bool
    {
        if ($this->closed) {
            return false;
        }

        $message = $this->receiveMessage();

        switch ($message::class) {
            case RowDescription::class:
                /** @var RowDescription $message */
                $rr = new ResultReader(
                    ctx: $this->ctx,
                    pgConn: $this->pgConn,
                    multiResultReader: $this,
                    fieldDescriptions: $message->fields,
                );

                $this->rr = $rr;

                return true;
            case CommandComplete::class:
                /** @var CommandComplete $message */
                $rr = new ResultReader(
                    ctx: $this->ctx,
                    pgConn: $this->pgConn,
                    commandTag: $message->commandTag,
                    commandConcluded: true,
                );

                $this->rr = $rr;

                return true;

            case EmptyQueryResponse::class:
                return false;
        }

        return false;
    }

    public function getResultReader(): ResultReader
    {
        return $this->rr;
    }

    // TODO: Should be private
    public function receiveMessage(): BackendMessage
    {
        try {
            $message = $this->pgConn->receiveMessage();
        } catch (\Throwable $e) {
            $this->closed = true;
            // TODO: Possible close connection needed

            throw $e;
        }

        switch ($message::class) {
            case ReadyForQuery::class:
                $this->closed = true;
                $this->pgConn->unlock();
                break;

            case ErrorResponse::class:
                /** @var ErrorResponse $message */
                throw PgConn::errorResponseToPgError($message);
        }

        return $message;
    }
}