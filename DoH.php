<?php

use Workerman\Connection\TcpConnection;
use Workerman\Protocols\Http\Request;
use Workerman\Protocols\Http\Response;
use Workerman\Worker;
use WpOrg\Requests\Requests;

require_once __DIR__ . '/vendor/autoload.php';
#define('DOH_UPSTREAM', 'https://1.1.1.1/dns-query');
define('DOH_UPSTREAM', 'udp://156.154.70.1');
define('ENDPOINT_PATH', '/dns-query');
define('START_MODE', 'HTTPS'); //HTTP or HTTPS
define('DEBUG', 1);

if (START_MODE == 'HTTPS') {
    $http_worker = new Worker('http://0.0.0.0:2345', [
        'ssl' => [
            'local_cert' => '/root/1.cer',
            'local_pk' => '/root/1.key',
            'verify_peer' => false,
            'allow_self_signed' => false,
        ],
    ]);
    $http_worker->transport = 'ssl';
} else {
    $http_worker = new Worker('http://0.0.0.0:2345');
}

$http_worker->count = 4;
$http_worker->name = 'DoH Proxy';
$http_worker->onMessage = function (TcpConnection $connection, Request $request) {
    $url = parse_url(DOH_UPSTREAM);

    if ($request->method() == 'POST' and $request->uri() == ENDPOINT_PATH and !empty($request->rawBody())) {
        $t_fin = $request->rawBody();
    } elseif ($request->method() == 'GET' and stristr($request->uri(), ENDPOINT_PATH . '?') and !empty($request->header('accept'))) {
        $t = explode('?dns=', $request->uri());
        $t_fin = base64_decode($t[1]);
    }

    if ($url['scheme'] == 'udp' and !empty($t_fin)) {
        $port_attend = isset($url['port']) ? '' : ':53';
        $fp = stream_socket_client(DOH_UPSTREAM . $port_attend, $errno, $errstr);
        stream_set_timeout($fp, 1);
        fwrite($fp, $t_fin);
        $r_body = fread($fp, 8192);
        fclose($fp);
        $r_flag = ($errno == 0) ? 1 : 0;
    } elseif (stristr($url['scheme'], 'http')) {
        $res = Requests::post(DOH_UPSTREAM, [
            'accept' => $request->header('accept'),
            'content-type' => $request->header('accept'),
        ], $t_fin);
        $r_flag = $res->success;
        $r_body = $res->body;
    }

    if ($r_flag) {
        return $connection->send(new Response(200, [
            'Content-Type' => 'application/dns-message',
        ], $r_body));
    }

    return $connection->close(new Response(400, ['Content-Type' => 'text/plain; charset=utf-8'], 'Bad Request'));
};
Worker::runAll();
