<?php

declare(strict_types=1);

use MakiseCo\Postgres\Config;
use Swow\Channel;
use Swow\Channel\Selector;
use Swow\Coroutine;

require_once __DIR__ . '/vendor/autoload.php';

$config = new Config(
    host: "host.docker.internal",
    port: 5432,
    database: "makise",
    user: "makise",
    password: "el-psy-congroo",
    connectTimeout: 0,
    runtimeParams: [
              'application_name' => 'Makise PHP \\hey'
          ],

);

$pgConn = \MakiseCo\Postgres\Driver\Pgx\PgConn::connect(null, $config);
//var_dump($pgConn);

//$result = $pgConn->exec(null, "SELECT 1, 2;");
//var_dump($result->readAll());
//
//try {
//    $result = $pgConn->execParams(null, "SELECT $1::int + $2::int;", ['1', '2']);
//} catch (Throwable $e) {
//    var_dump($e);
//    exit;
//}
//var_dump('hello');
//var_dump($result->read());
//var_dump($pgConn);

$stmtDesc = $pgConn->prepare(null, 'tmp_stmt', 'SELECT $1::int', []);
var_dump($stmtDesc);

$rr = $pgConn->execPrepared(null, $stmtDesc->name, ['228'], [], []);
$res = $rr->read();
var_dump($res);

$rr = $pgConn->execPrepared(null, $stmtDesc->name, ['322'], [], []);
$res = $rr->read();
var_dump($res);

//$connector = new Connector();
//$conn = $connector->connect($config);
//var_dump($conn);

//$doneChan = new Channel();
//$timerChan = new Channel();
//
//Coroutine::run(function () use ($timerChan) {
//    // 1ms sleep
//    for (;;usleep(1000)) {
//        $timerChan->push(1);
//    }
//});

//Coroutine::run(function () use ($connector, $config, $doneChan) {
//    $connector->connect($config);
//    $doneChan->push(1);
//});
//Coroutine::run(function () use ($doneChan, $timerChan) {
//    $selector = new Selector();
//
//    for (;;) {
//        $chan = $selector
//            ->pop($timerChan)
//            ->pop($doneChan)
//            ->commit();
//
//        if ($chan === $timerChan) {
//            echo "Connecting..." . PHP_EOL;
//        } elseif ($chan === $doneChan) {
//            echo "Connected!" . PHP_EOL;
//            break;
//        }
//    }
//});
