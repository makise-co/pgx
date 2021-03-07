<?php

declare(strict_types=1);

use MakiseCo\Postgres\Config;
use MakiseCo\Postgres\Driver\Pq\Connector;
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
var_dump($pgConn);

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
