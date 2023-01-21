<?php
use Workerman\Worker;
use Workerman\Connection\TcpConnection;
use Workerman\Protocols\Http\Request;
use Workerman\Protocols\Http\Response;
use WpOrg\Requests\Requests;

require_once __DIR__ . '/vendor/autoload.php';
define('DOH_UPSTREAM', 'https://1.1.1.1/dns-query');
define('ENDPOINT_PATH', '/dns-query');
define('START_MODE', 'HTTPS'); //HTTP or HTTPS
define('DEBUG', 1);

if(START_MODE == 'HTTPS')
{
	$context = array(
		'ssl' => array(
			'local_cert'        => '/root/1.cer', // 也可以是crt文件
			'local_pk'          => '/root/1.key',
			'verify_peer'       => false,
			'allow_self_signed' => false, //如果是自签名证书需要开启此选项
		)
	);
	$http_worker = new Worker('http://0.0.0.0:2345', $context);
	$http_worker->transport = 'ssl';
} else {
	$http_worker = new Worker("http://0.0.0.0:2345");
}

$http_worker->count = 6;
$http_worker->name = 'DoH Proxy';
$http_worker->onMessage = function(TcpConnection $connection, Request $request)
{
	if($request->method()== 'POST' and $request->uri()==ENDPOINT_PATH and !empty($request->rawBody()))
	{
		//if(DEBUG) printf("POST %s %s\n", $request->header('accept'), base64_encode($request->rawBody()));
		$res = Requests::post(DOH_UPSTREAM, ['accept' => $request->header('accept'), 'content-type' => $request->header('content-type')], $request->rawBody());
	} 
	else if($request->method() == 'GET' and stristr($request->uri(), ENDPOINT_PATH.'?') and !empty($request->header('accept')))
	{
		//if(DEBUG) printf("GET %s %s\n", $request->header('accept'), $request->uri());
		$t = explode('dns-query', $request->uri());
		$res = Requests::get(DOH_UPSTREAM.$t[1], ['accept' => $request->header('accept')]);
	}
	if($res->success) return $connection->send(new Response(200, ['Content-Type' => $res->headers['content-type']], $res->body));
	return $connection->close(new Response(400, ['Content-Type' => 'text/plain; charset=utf-8'], 'Bad Request'));
};

Worker::runAll();
