#!/usr/env/php
<?php
ini_set("display_errors", 0);
ini_set("display_startup_errors", 0);
error_reporting(E_ALL);
ini_set("log_errors", 1);
ini_set("error_log", "php://stderr"); # Cronの場合はSTDERRにだしてCronに任せたい

register_shutdown_function(
    function () {
        $e = error_get_last();
        if (
            $e['type'] == E_ERROR ||
            $e['type'] == E_PARSE ||
            $e['type'] == E_CORE_ERROR ||
            $e['type'] == E_COMPILE_ERROR ||
            $e['type'] == E_USER_ERROR
        ) {
            error_log(
                "Error type:\t {$e['type']}\n" .
                    "Error message:\t {$e['message']}\n" .
                    "Error file:\t {$e['file']}\n" .
                    "Error line:\t {$e['line']}\n"
            );
        }
    }
);
set_error_handler(function ($errno, $errstr, $errfile, $errline) {
    throw new ErrorException($errstr, $errno, 0, $errfile, $errline);
});

require_once "vendor/autoload.php";

use Monolog\Logger;
use Monolog\Handler\SocketHandler;
use Dotenv\Dotenv;

## Settings
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

$filepath = getenv("LOG_FILE_PATH");
$pointerpath = getenv("LOG_POINTER_FILE_PATH");
$chunk_size = getenv("CHUNK_SIZE"); # ロガー側が受け入れられる最大サイズに調整すること

try {
    if (!file_exists($pointerpath)) {
        $from_pointer = 0;
        file_put_contents($pointerpath, "0");
    } else {
        $from_pointer = (int) file_get_contents($pointerpath);
    }

    $fp = fopen($filepath, 'rb') or die('open log file failed.');

    $stat = fstat($fp);
    $size = (int) $stat['size'];

    # ログがTruncateされているか確認
    if ($size < $from_pointer) {
        $from_pointer = 0;
        file_put_contents($pointerpath, "0");
        # ポインタを巻き戻すだけで終了、ログは次回処理されます
        return;
    }

    # ログが増分されていなければ終了
    if ($size === $from_pointer) {
        exit; // logは増えていないので終了
    }
    # 増分を保存
    file_put_contents($pointerpath, $size);

    # read chunk
    fseek($fp, $from_pointer);

    $logger = new Logger('myLogger');

    // Add the TCP socket handler wrapped in TLS.
    $handler = new SocketHandler('tls://' . getenv("PAPERTRAIL_SYSLOG_HOST") . ':' . getenv("PAPERTRAIL_SYSLOG_PORT"));
    $handler->setPersistent(true);

    // Tell the logger to use the new socket handler.
    $logger->pushHandler($handler);

    while ($raw = fread($fp, $chunk_size)) {
        # send log here as you like.
        # 一行づつ送るなどは考えないほうが良い…諦めたほうが良い…（ログ出力時点でよほどがんばらないといけない）
        # フィルターを実装するのも吉、$logger->alertにするとか

        # Send error
        $logger->error($raw);
    }
    # done.
} catch (\Throwable $e) {
    error_log("Unhandled Error: {$e->getMessage()} at {$e->getFile()}:{$e->getLine()} {$e->getTraceAsString()}");
}
