<?php

declare(strict_types=1);

namespace MakiseCo\Postgres;

class Config
{
    public string $host;
    public int $port;
    public string $database;
    public string $user;
    public string $password;

    public int $connectTimeout;
    public array $runtimeParams;

    public function __construct(
        string $host,
        int $port,
        string $database,
        string $user,
        string $password,
        int $connectTimeout,
        array $runtimeParams = [],
    ) {
        $this->host = $host;
        $this->port = $port;
        $this->database = $database;
        $this->user = $user;
        $this->password = $password;
        $this->connectTimeout = $connectTimeout;
        $this->runtimeParams = $runtimeParams;
    }

    public function getDsn(): string
    {
        return "host={$this->host} port={$this->port} user={$this->user} dbname={$this->database} password={$this->password}";
    }
}