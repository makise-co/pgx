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
use MakiseCo\Postgres\Driver\Pgx\Proto\Bind;
use MakiseCo\Postgres\Driver\Pgx\Proto\CancelRequest;
use MakiseCo\Postgres\Driver\Pgx\Proto\Describe;
use MakiseCo\Postgres\Driver\Pgx\Proto\ErrorResponse;
use MakiseCo\Postgres\Driver\Pgx\Proto\Execute;
use MakiseCo\Postgres\Driver\Pgx\Proto\FrontendMessage;
use MakiseCo\Postgres\Driver\Pgx\Proto\ParameterStatus;
use MakiseCo\Postgres\Driver\Pgx\Proto\Parse;
use MakiseCo\Postgres\Driver\Pgx\Proto\PasswordMessage;
use MakiseCo\Postgres\Driver\Pgx\Proto\Query;
use MakiseCo\Postgres\Driver\Pgx\Proto\ReadyForQuery;
use MakiseCo\Postgres\Driver\Pgx\Proto\StartupMessage;
use MakiseCo\Postgres\Driver\Pgx\Proto\Sync;
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

    // TODO: Result reader
    // TODO: Multi result reader
    //

    public static function connect($ctx, Config $config): self
    {
        $pgConn = new PgConn();
        $pgConn->config = $config;

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

        while (true) {
            $message = $pgConn->receiveMessage();

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

    /**
     * @param $ctx
     * @param string $sql
     *
     * @return MultiResultReader must be closed before PgConn can be used again.
     *
     * @throws \Throwable
     */
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

    /**
     * ExecParams executes a command via the PostgreSQL extended query protocol.
     * ResultReader must be closed before PgConn can be used again.
     *
     * @param $ctx
     * @param string $sql is a SQL command string. It may only contain one query.
     * Parameter substitution is positional using $1, $2, $3, etc.
     *
     * @param array<string> $paramValues are the parameter values.
     * It must be encoded in the format given by paramFormats.
     *
     * @param array<int> $paramOIDs is an array of data type OIDs for paramValues.
     * If paramOIDs is empty, the server will infer the data type for all parameters.
     * Any paramOID element that is 0 that will cause the server to infer the data type for that parameter.
     *
     * @param array<int> $paramFormats is an array of format codes determining for each paramValue column
     * whether it is encoded in text or binary format.
     * If paramFormats is empty all params are text format.
     *
     * @param array<int> $resultFormats is an array of format codes determining for each result column
     * whether it is encoded in text or binary format.
     * If resultFormats is empty all results will be in text format.
     *
     * @return ResultReader must be closed before PgConn can be used again.
     */
    public function execParams(
        $ctx,
        string $sql,
        array $paramValues = [],
        array $paramOIDs = [],
        array $paramFormats = [],
        array $resultFormats = [],
    ): ResultReader {
        $this->lock();

        $parseMessage = new Parse(
            name: '',
            query: $sql,
            parameterOIDs: $paramOIDs,
        );
        $bindMessage = new Bind(
            destinationPortal: '',
            preparedStatement: '',
            parameterFormatCodes: $paramFormats,
            parameters: $paramValues,
            resultFormatCodes: $resultFormats,
        );
        $describeMessage = new Describe(
            objectType: 'P',
            name: '',
        );
        $executeMessage = new Execute(
            portal: '',
            maxRows: 0,
        );
        $syncMessage = new Sync();

        try {
            $this->sendMessagesInBatch(
                $parseMessage,
                $bindMessage,
                $describeMessage,
                $executeMessage,
                $syncMessage,
            );
        } catch (\Throwable $e) {
            $this->unlock();

            throw $e;
        }

        $rr = new ResultReader(
            ctx: $ctx,
            pgConn: $this,
        );

        try {
            $rr->readUntilRowDescription();
        } catch (\Throwable $e) {
            $this->unlock();

            throw  $e;
        }

        return $rr;
    }

    /**
     * ExecPrepared enqueues the execution of a prepared statement via the PostgreSQL extended query protocol.
     *
     * @param $ctx
     * @param string $stmtName is a prepared statement name
     *
     * @param array<string> $paramValues are the parameter values.
     * It must be encoded in the format given by paramFormats.
     *
     * @param array<int> $paramFormats is an array of format codes determining for each paramValue column
     * whether it is encoded in text or binary format.
     * If paramFormats is empty all params are text format.
     *
     * @param array<int> $resultFormats is an array of format codes determining for each result column
     * whether it is encoded in text or binary format.
     * If resultFormats is empty all results will be in text format.
     *
     * @return ResultReader must be closed before PgConn can be used again.
     */
    public function execPrepared(
        $ctx,
        string $stmtName,
        array $paramValues,
        array $paramFormats,
        array $resultFormats
    ): ResultReader {
        $this->lock();

        $bindMessage = new Bind(
            destinationPortal: '',
            preparedStatement: $stmtName,
            parameterFormatCodes: $paramFormats,
            parameters: $paramValues,
            resultFormatCodes: $resultFormats,
        );
        $describeMessage = new Describe(
            objectType: 'P',
            name: '',
        );
        $executeMessage = new Execute(
            portal: '',
            maxRows: 0,
        );
        $syncMessage = new Sync();

        try {
            $this->sendMessagesInBatch(
                $bindMessage,
                $describeMessage,
                $executeMessage,
                $syncMessage,
            );
        } catch (\Throwable $e) {
            $this->unlock();

            throw $e;
        }

        $rr = new ResultReader(
            ctx: $ctx,
            pgConn: $this,
        );

        try {
            $rr->readUntilRowDescription();
        } catch (\Throwable $e) {
            $this->unlock();

            throw  $e;
        }

        return $rr;
    }

    /**
     * Prepare creates a prepared statement. If the name is empty, the anonymous prepared statement will be used. This
     * allows Prepare to also to describe statements without creating a server-side prepared statement.
     *
     * @param $ctx
     * @param string $name statement name
     * @param string $sql statement query
     * @param array<int> $paramOIDs
     *
     * @return StatementDescription
     *
     * @throws PgError
     * @throws \Throwable
     */
    public function prepare($ctx, string $name, string $sql, array $paramOIDs): StatementDescription
    {
        $this->lock();
        // TODO: Use coroutine defer Unlock

        $parseMessage = new Parse(
            name: $name,
            query: $sql,
            parameterOIDs: $paramOIDs,
        );
        $describeMessage = new Describe(
            objectType: 'S',
            name: $name,
        );
        $syncMessage = new Sync();

        try {
            $this->sendMessagesInBatch(
                $parseMessage,
                $describeMessage,
                $syncMessage,
            );
        } catch (\Throwable $e) {
            $this->unlock();

            throw $e;
        }

        /** @var array<int> $receivedParamOIDs */
        $receivedParamOIDs = [];
        /** @var array<Proto\FieldDescription> $receivedFields */
        $receivedFields = [];

        while (true) {
            try {
                $message = $this->receiveMessage();
            } catch (\Throwable $e) {
                $this->unlock();

                throw $e;
            }

            switch ($message::class) {
                case Proto\ParameterDescription::class:
                    /** @var Proto\ParameterDescription $message */
                    $receivedParamOIDs = $message->parameterOIDs;
                    break;
                case Proto\RowDescription::class:
                    /** @var Proto\RowDescription $message */
                    $receivedFields = $message->fields;
                    break;
                case Proto\ErrorResponse::class:
                    $this->unlock();

                    /** @var Proto\ErrorResponse $message */
                    throw PgConn::errorResponseToPgError($message);
                case Proto\ReadyForQuery::class:
                    // break from loop where postgres is ready for query
                    break 2;
            }
        }

        $this->unlock();

        return new StatementDescription(
            name: $name,
            sql: $sql,
            paramOIDs: $receivedParamOIDs,
            fields: $receivedFields,
        );
    }

    /**
     * CancelRequest sends a cancel request to the PostgreSQL server.
     * It throws an error if unable to deliver the cancel request,
     * but lack of an error does not ensure that the query was canceled.
     *
     * As specified in the documentation, there is no way to be sure a query was canceled.
     *
     * @see https://www.postgresql.org/docs/11/protocol-flow.html#id-1.10.5.7.9
     *
     * @param $ctx
     *
     * @throws \Throwable
     */
    public function cancelRequest($ctx): void
    {
        $serverAddr = $this->conn->getPeerAddress();
        $serverPort = $this->conn->getPeerPort();

        $cancelConn = new Swow\Socket($this->conn->getType());
        $cancelConn->connect($serverAddr, $serverPort);

        $message = new CancelRequest(pid: $this->pid, secretKey: $this->secretKey);

        try {
            $cancelConn->sendString($message->encode(''));
            $cancelConn->recvString(1);
        } catch (SocketException $e) {
            // server should close connection
            if ($e->getCode() !== Swow\Errno\ECONNRESET) {
                throw $e;
            }
        } finally {
            $cancelConn->close();
        }
    }

    private function sendMessagesInBatch(FrontendMessage ...$messages): void
    {
        $encoded = '';

        foreach ($messages as $message) {
            $msgName = $message::class;

            $encodedMsg = $message->encode('');

            var_dump("[Batch] Sending message {$msgName}");
            var_dump("[Batch] Sending message body: " . bin2hex($encodedMsg));

            $encoded .= $encodedMsg;
        }

        $this->conn->sendString($encoded);
    }

    private function sendMessage(FrontendMessage $message): void
    {
        $encoded = $message->encode('');

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
//        var_dump('Receive message called');
//        var_dump(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 5));

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

    // TODO: Should be private
    public function peekMessage(): BackendMessage
    {
        if ($this->peekedMessage !== null) {
            return $this->peekedMessage;
        }

        $msg = $this->frontend->receive();

        $this->peekedMessage = $msg;

        return $msg;
    }
}