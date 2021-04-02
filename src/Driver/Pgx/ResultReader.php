<?php

declare(strict_types=1);

namespace MakiseCo\Postgres\Driver\Pgx;

use MakiseCo\Postgres\Driver\Pgx\Proto\BackendMessage;
use MakiseCo\Postgres\Driver\Pgx\Proto\CommandComplete;
use MakiseCo\Postgres\Driver\Pgx\Proto\CommandTag;
use MakiseCo\Postgres\Driver\Pgx\Proto\DataRow;
use MakiseCo\Postgres\Driver\Pgx\Proto\EmptyQueryResponse;
use MakiseCo\Postgres\Driver\Pgx\Proto\ErrorResponse;
use MakiseCo\Postgres\Driver\Pgx\Proto\FieldDescription;
use MakiseCo\Postgres\Driver\Pgx\Proto\ReadyForQuery;
use MakiseCo\Postgres\Driver\Pgx\Proto\RowDescription;

/**
 * ResultReader is a reader for the result of a single query.
 */
class ResultReader
{
    /** @var array<int, string> */
    private array $rowValues = [];

    /**
     * ResultReader constructor.
     * @param $ctx
     * @param PgConn $pgConn
     * @param MultiResultReader|null $multiResultReader
     * @param array<FieldDescription> $fieldDescriptions
     * @param CommandTag|null $commandTag
     * @param bool $commandConcluded
     * @param bool $closed
     */
    public function __construct(
        private $ctx,
        private PgConn $pgConn,
        private ?MultiResultReader $multiResultReader = null,
        /** @var array<FieldDescription> $fieldDescriptions */
        private array $fieldDescriptions = [],
        private ?CommandTag $commandTag = null,
        private bool $commandConcluded = false,
        private bool $closed = false,
    ) {
    }

    public function close(): void
    {
        if ($this->closed) {
            return;
        }
        $this->closed = true;

        while (!$this->commandConcluded) {
            $this->receiveMessage();
        }

        if ($this->multiResultReader === null) {
            while (true) {
                $message = $this->receiveMessage();

                switch ($message::class) {
                    case ErrorResponse::class:
                        /** @var ErrorResponse $message */
                        throw PgConn::errorResponseToPgError($message);

                    case ReadyForQuery::class:
                        $this->pgConn->unlock();
                        return;
                }
            }
        }
    }

    public function read(): Result
    {
        $rows = [];

        while ($this->nextRow()) {
            $rows[] = $this->rowValues;
        }

        $this->close();

        return new Result(
            fieldDescriptions: $this->fieldDescriptions,
            rows: $rows,
            commandTag: $this->commandTag,
        );
    }

    private function nextRow(): bool
    {
        while (!$this->commandConcluded) {
            $message = $this->receiveMessage();

            switch ($message::class) {
                case DataRow::class:
                    /** @var DataRow $message */
                    $this->rowValues = $message->values;

                    return true;
            }
        }

        return false;
    }

    private function receiveMessage(): BackendMessage
    {
        if ($this->multiResultReader === null) {
            $message = $this->pgConn->receiveMessage();
        } else {
            $message = $this->multiResultReader->receiveMessage();
        }

        switch ($message::class) {
            case RowDescription::class:
                /** @var RowDescription $message */
                $this->fieldDescriptions = $message->fields;
                break;
            case CommandComplete::class:
                /** @var CommandComplete $message */

                $this->concludeCommand($message->commandTag);
                break;
            case EmptyQueryResponse::class:
                /** @var EmptyQueryResponse $message */
                $this->concludeCommand(null);
                break;

            case ErrorResponse::class:
                // TODO: Possible close needed

                $this->concludeCommand(null);

                /** @var ErrorResponse $message */
                throw PgConn::errorResponseToPgError($message);
        }

        return $message;
    }

    /**
     * readUntilRowDescription ensures the ResultReader's fieldDescriptions are loaded.
     *
     * @throws Exception\PgError
     */
    public function readUntilRowDescription(): void
    {
        while (!$this->commandConcluded) {
            // Peek before receive to avoid consuming a DataRow if the result set does not include a RowDescription method.
            // This should never happen under normal pgconn usage, but it is possible if SendBytes and ReceiveResults are
            // manually used to construct a query that does not issue a describe statement.
            $message = $this->pgConn->peekMessage();

            if ($message instanceof DataRow) {
                return;
            }

            // Consume the message
            $message = $this->receiveMessage();
            if ($message instanceof RowDescription) {
                return;
            }
        }
    }

    private function concludeCommand(?CommandTag $commandTag): void
    {
        if ($this->commandConcluded) {
            return;
        }

        $this->commandTag = $commandTag;
        $this->commandConcluded = true;
        $this->rowValues = [];
    }
}