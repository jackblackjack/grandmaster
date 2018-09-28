<?php

/**
 * Usage:   php import.php [SYMBOL] [DATE]
 *     e.g. php import.php tvix 2018-09-25
 */

require('./vendor/autoload.php');

use Carbon\Carbon;
use Dotenv\Dotenv;

$dotenv = new Dotenv(__DIR__);
$dotenv->load();

if ($argc < 3) {
    exit();
}

$symbol = strtolower($argv[1]);
$date   = $argv[2];

$db = new mysqli($_ENV['MYSQL_HOST'], $_ENV['MYSQL_USER'], $_ENV['MYSQL_PASS'], $_ENV['MYSQL_DB']);

$client = new GuzzleHttp\Client();
$request = $client->request(
    'GET',
    'https://sandbox.tradier.com/v1/markets/timesales?symbol=' . strtoupper($symbol) . '&interval=1min&start=' . $date . 'T00:00:00&end=' . $date . 'T23:59:59&session_filter=open',
    [
        'headers' => [
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $_ENV['TRADIER_KEY']
        ]
    ]
);
$data = $request->getBody();
file_put_contents("./data/" . strtoupper($symbol) . "_{$date}.json", $data);
$file = json_decode($data, true);

foreach ($file['series']['data'] as $bar) {
    $start      = (new Carbon($bar['time']))->format('Y-m-d H:i:s');
    $open       = (double)$bar['open'];
    $close      = (double)$bar['close'];
    $high       = (double)$bar['high'];
    $low        = (double)$bar['low'];
    $volume     = (double)$bar['volume'];

    $db->query("INSERT IGNORE INTO {$symbol} VALUES('{$start}', '{$open}', '{$high}', '{$low}', '{$close}', '{$volume}')");

    if ($db->error) {
        fwrite(STDOUT, "[!] MYSQL ERROR: {$db->error}" . PHP_EOL);
    }
}

fwrite(STDOUT, "[+] Processed: ./data/{$symbol}_{$date}.json" . PHP_EOL);

$db->close();
