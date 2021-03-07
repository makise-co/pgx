<?php

declare(strict_types=1);

namespace MakiseCo\Postgres\Driver\Pgx;

use MakiseCo\Postgres\Config;
use MakiseCo\Postgres\Driver\Pgx\Exception\ConnectError;
use MakiseCo\Postgres\Driver\Pgx\Exception\ConnLockError;
use MakiseCo\Postgres\Driver\Pgx\Exception\PgError;
use MakiseCo\Postgres\Driver\Pgx\Proto\AuthenticationCleartextPassword;
use MakiseCo\Postgres\Driver\Pgx\Proto\AuthenticationMD5Password;
use MakiseCo\Postgres\Driver\Pgx\Proto\AuthenticationOk;
use MakiseCo\Postgres\Driver\Pgx\Proto\BackendKeyData;
use MakiseCo\Postgres\Driver\Pgx\Proto\BackendMessage;
use MakiseCo\Postgres\Driver\Pgx\Proto\ErrorResponse;
use MakiseCo\Postgres\Driver\Pgx\Proto\FrontendMessage;
use MakiseCo\Postgres\Driver\Pgx\Proto\ParameterStatus;
use MakiseCo\Postgres\Driver\Pgx\Proto\PasswordMessage;
use MakiseCo\Postgres\Driver\Pgx\Proto\Query;
use MakiseCo\Postgres\Driver\Pgx\Proto\ReadyForQuery;
use MakiseCo\Postgres\Driver\Pgx\Proto\StartupMessage;
use Swow;
use Swow\Socket\Exception as SocketException;

class PgConn
{
    public const CONN_STATUS_UNINITIALIZED = 0;
    public const CONN_STATUS_CONNECTING = 1;
    public const CONN_STATUS_CLOSED = 2;
    public const CONN_STATUS_IDLE = 3;
    public const CONN_STATUS_BUSY = 4;

    private Swow\Socket $conn;

    private int $pid = -1;
    private int $secretKey = -1;

    /**
     * @var array<string,string>
     */
    private array $parameterStatuses = [];

    private int $txStatus = -1;

    private Frontend $frontend;
    private Config $config;

    /** One of CONN_STATUS* constants */
    private int $status = self::CONN_STATUS_UNINITIALIZED;

    private ?BackendMessage $peekedMessage = null;

    private string $wbuff;
    // TODO: Result reader
    // TODO: Multi result reader
    //

    public static function connect($ctx, Config $config): self
    {
        $pgConn = new PgConn();
        $pgConn->config = $config;
        $pgConn->wbuff = '';

        // TODO: Support fallback configs

        $sock = new Swow\Socket(Swow\Socket::TYPE_TCP);

        $pgConn->conn = $sock;
        $pgConn->frontend = new Frontend($sock);

        $startupMsg = new StartupMessage(
            protocolVersion: StartupMessage::ProtocolVersionNumber,
            parameters: $config->runtimeParams,
        );

        $startupMsg->parameters['user'] = $config->user;

        if ($config->database !== '') {
            $startupMsg->parameters['database'] = $config->database;
        }

        $pgConn->status = self::CONN_STATUS_CONNECTING;

        try {
            $sock->connect($config->host, $config->port);
        } catch (SocketException $e) {
            throw new ConnectError("connect error: {$e->getMessage()}", 0, $e);
        }

        $pgConn->sendMessage($startupMsg);

        for (; ;) {
            $message = $pgConn->receiveMessage();
            var_dump("Received message: " . $message::class);

            switch ($message::class) {
                case BackendKeyData::class:
                    /** @var BackendKeyData $message */
                    $pgConn->pid = $message->processId;
                    $pgConn->secretKey = $message->secretKey;
                    break;

                case AuthenticationOk::class:
                    break;
                case AuthenticationCleartextPassword::class:
                    $pgConn->txPasswordMessage($config->password);
                    break;

                case AuthenticationMD5Password::class:
                    /** @var AuthenticationMD5Password $message */
                    $digestedPassword = "md5" . md5(
                            md5($pgConn->config->password . $pgConn->config->user) . $message->salt
                        );

                    $pgConn->txPasswordMessage($digestedPassword);
                    break;

                case ReadyForQuery::class:
                    $pgConn->status = self::CONN_STATUS_IDLE;

                    return $pgConn;

                case ErrorResponse::class:
                    $sock->close();

                    /** @var ErrorResponse $message */
                    throw self::errorResponseToPgError($message);

                case ParameterStatus::class:
                    // handled by receiveMessage
                    break;

                default:
                    $sock->close();

                    $msg = $message::class;
                    throw new ConnectError("Received unexpected message: {$msg}");
            }
        }
    }

    public static function errorResponseToPgError(ErrorResponse $msg): PgError
    {
        return new PgError(
            severity: $msg->severity,
            pgCode: $msg->code,
            pgMessage: $msg->message,
            detail: $msg->detail,
            hint: $msg->hint,
            position: $msg->position,
            internalPosition: $msg->internalPosition,
            internalQuery: $msg->internalQuery,
            where: $msg->where,
            schemaName: $msg->schemaName,
            tableName: $msg->tableName,
            columnName: $msg->columnName,
            dataTypeName: $msg->dataTypeName,
            constraintName: $msg->constraintName,
            pgFile: $msg->file,
            pgLine: $msg->line,
            routine: $msg->routine,
        );
    }

    public function exec($ctx, string $sql): MultiResultReader
    {
        $this->lock();

        $queryMessage = new Query(
            query: $sql,
        );

        try {
            $this->sendMessage($queryMessage);
        } catch (\Throwable $e) {
            $this->unlock();

            throw $e;
        }

        return new MultiResultReader(
            ctx: $ctx,
            pgConn: $this,
        );
    }

    private function sendMessage(FrontendMessage $message): void
    {
        $encoded = $message->encode($this->wbuff);

        $msgName = $message::class;
        var_dump("Sending message {$msgName}");
        var_dump("Sending message body: " . bin2hex($encoded));

        $this->conn->sendString($encoded);
    }

    private function txPasswordMessage(string $password): void
    {
        $passwordMessage = new PasswordMessage(
            password: $password,
        );

        $this->sendMessage($passwordMessage);
    }

    private function lock(): void
    {
        match ($this->status) {
            self::CONN_STATUS_BUSY => throw new ConnLockError('conn busy'),
            self::CONN_STATUS_CLOSED => throw new ConnLockError('conn closed'),
            self::CONN_STATUS_UNINITIALIZED => throw new ConnLockError('conn uninitialized'),
            default => $this->status = self::CONN_STATUS_BUSY,
        };
    }

    // TODO: Should be private
    public function unlock(): void
    {
        match ($this->status) {
            self::CONN_STATUS_BUSY => $this->status = self::CONN_STATUS_IDLE,
            default => throw new ConnLockError('cannot unlock unlocked connection')
        };
    }

    /**
     * @return BackendMessage
     * @internal
     * TODO: Should be private
     */
    public function receiveMessage(): BackendMessage
    {
        $msg = $this->peekMessage();
        $this->peekedMessage = null;

        switch ($msg::class) {
            case ReadyForQuery::class:
                /** @var ReadyForQuery $msg */
                $this->txStatus = $msg->txStatus;
                break;
            case ParameterStatus::class:
                /** @var ParameterStatus $msg */
                $this->parameterStatuses[$msg->name] = $msg->value;
                break;
        }

        // TODO: Handle message

        return $msg;
    }

    private function peekMessage(): BackendMessage
    {
        if ($this->peekedMessage !== null) {
            return $this->peekedMessage;
        }

        $msg = $this->frontend->receive();

        $this->peekedMessage = $msg;

        return $msg;
    }
}