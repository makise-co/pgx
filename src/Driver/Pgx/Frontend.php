<?php

declare(strict_types=1);

namespace MakiseCo\Postgres\Driver\Pgx;

use MakiseCo\Postgres\Driver\Pgx\Proto\AuthenticationCleartextPassword;
use MakiseCo\Postgres\Driver\Pgx\Proto\AuthenticationMD5Password;
use MakiseCo\Postgres\Driver\Pgx\Proto\AuthenticationOk;
use MakiseCo\Postgres\Driver\Pgx\Proto\BackendKeyData;
use MakiseCo\Postgres\Driver\Pgx\Proto\BackendMessage;
use MakiseCo\Postgres\Driver\Pgx\Proto\BindComplete;
use MakiseCo\Postgres\Driver\Pgx\Proto\CloseComplete;
use MakiseCo\Postgres\Driver\Pgx\Proto\CommandComplete;
use MakiseCo\Postgres\Driver\Pgx\Proto\DataRow;
use MakiseCo\Postgres\Driver\Pgx\Proto\EmptyQueryResponse;
use MakiseCo\Postgres\Driver\Pgx\Proto\ErrorResponse;
use MakiseCo\Postgres\Driver\Pgx\Proto\Exception\UnknownAuthenticationType;
use MakiseCo\Postgres\Driver\Pgx\Proto\Exception\UnknownMessageType;
use MakiseCo\Postgres\Driver\Pgx\Proto\ParameterDescription;
use MakiseCo\Postgres\Driver\Pgx\Proto\ParameterStatus;
use MakiseCo\Postgres\Driver\Pgx\Proto\ParseComplete;
use MakiseCo\Postgres\Driver\Pgx\Proto\ReadyForQuery;
use MakiseCo\Postgres\Driver\Pgx\Proto\RowDescription;
use PHPinnacle\Buffer\ByteBuffer;
use Swow\Socket;

const AuthTypeOk = 0;
const AuthTypeCleartextPassword = 3;
const AuthTypeMD5Password = 5;
const AuthTypeSASL = 10;
const AuthTypeSASLContinue = 11;
const AuthTypeSASLFinal = 12;

class Frontend
{
    private int $bodyLen = 0;
    private int $msgType = 0;
    private bool $partialMsg = false;

    private AuthenticationOk $authenticationOk;
    private AuthenticationCleartextPassword $authenticationCleartextPassword;
    private AuthenticationMD5Password $authenticationMD5Password;
    private CommandComplete $commandComplete;
    private DataRow $dataRow;

    private ParseComplete $parseComplete;
    private BindComplete $bindComplete;
    private CloseComplete $closeComplete;
    private ReadyForQuery $readyForQuery;
    private BackendKeyData $backendKeyData;
    private ErrorResponse $errorResponse;
    private EmptyQueryResponse $emptyQueryResponse;

    private ParameterStatus $parameterStatus;

    private RowDescription $rowDescription;

    private ParameterDescription $parameterDescription;

    private Socket $sock;

    public function __construct(Socket $sock)
    {
        $this->sock = $sock;

        $this->authenticationOk = new AuthenticationOk();
        $this->authenticationCleartextPassword = new AuthenticationCleartextPassword();
        $this->authenticationMD5Password = new AuthenticationMD5Password();
        $this->commandComplete = new CommandComplete();
        $this->dataRow = new DataRow();

        $this->parseComplete = new ParseComplete();
        $this->bindComplete = new BindComplete();
        $this->closeComplete = new CloseComplete();
        $this->readyForQuery = new ReadyForQuery();
        $this->backendKeyData = new BackendKeyData();
        $this->errorResponse = new ErrorResponse();
        $this->emptyQueryResponse = new EmptyQueryResponse();
        $this->parameterStatus = new ParameterStatus();
        $this->parameterDescription = new ParameterDescription();

        $this->rowDescription = new RowDescription();
    }

    public function receive(): BackendMessage
    {
        $buffer = new ByteBuffer();

        if (!$this->partialMsg) {
            $header = $this->sock->recvString(5);
            $buffer->append($header);

            $this->msgType = $buffer->consumeUint8();
            $this->bodyLen = $buffer->consumeUint32() - 4;
            $this->partialMsg = true;

            $chrType = chr($this->msgType);
            var_dump("Received header of message: type={$this->msgType} ({$chrType}) bodyLen={$this->bodyLen}");
        }

        if ($this->bodyLen > 0) {
            $msgBody = $this->sock->recvString($this->bodyLen);
        } else {
            $msgBody = '';
        }

        $buffer->append($msgBody);

        var_dump("Received message body: " . bin2hex($msgBody));

        $this->partialMsg = false;

        $msg = match (chr($this->msgType)) {
            '1' => $this->parseComplete,
            '2' => $this->bindComplete,
            '3' => $this->closeComplete,
            'C' => $this->commandComplete,
            'D' => $this->dataRow,
            'I' => $this->emptyQueryResponse,
            'R' => $this->findAuthenticationMessageType($buffer),
            'K' => $this->backendKeyData,
            'Z' => $this->readyForQuery,
            'E' => $this->errorResponse,
            'S' => $this->parameterStatus,
            'T' => $this->rowDescription,
            't' => $this->parameterDescription,
            default => throw new UnknownMessageType(chr($this->msgType)),
        };

        var_dump("Received message: " . $msg::class);

        $msg->decode($buffer->bytes());

        return $msg;
    }

    private function findAuthenticationMessageType(ByteBuffer $buffer): BackendMessage
    {
        if ($buffer->size() < 4) {
            throw new \InvalidArgumentException("authentication message too short");
        }

        $authType = $buffer->readInt32();

        var_dump("Auth type: {$authType}");

        return match ($authType) {
            AuthTypeOk => $this->authenticationOk,
            AuthTypeCleartextPassword => $this->authenticationCleartextPassword,
            AuthTypeMD5Password => $this->authenticationMD5Password,
            default => throw new UnknownAuthenticationType($authType),
        };
    }
}