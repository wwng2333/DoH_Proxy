<?php
use Workerman\Worker;
use Workerman\Connection\TcpConnection;
use Workerman\Protocols\Http\Request;
use Workerman\Protocols\Http\Response;
use WpOrg\Requests\Requests;

require_once __DIR__ . '/vendor/autoload.php';
define('DOH_UPSTREAM', 'https://223.6.6.6/dns-query');
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

$http_worker->count = 4;
$http_worker->name = 'DoH Proxy';
$http_worker->onMessage = function(TcpConnection $connection, Request $request)
{
	if(DEBUG) printf("recv:%s, %s at %s\n", $request->method(), $request->header('accept'), $request->uri());
	if($request->uri()==ENDPOINT_PATH and !empty($request->rawBody()))
	{
		$post = Requests::post(DOH_UPSTREAM, ['Accept' => 'application/dns-message'], $request->rawBody());
		return $connection->close(new Response(200, ['Content-Type' => 'application/dns-message'], $post->body));
	} 
	else if(stristr($request->uri(), ENDPOINT_PATH.'?')) 
	{
		switch(strtolower($request->header('accept')))
		{
			case "application/dns-json":
				$Request_header = ['Accept' => 'application/json'];
				$Return_header = ["Content-Type' => 'application/json; charset=UTF-8"];
			break;

			case "application/dns-message":
				$Request_header = ['Accept' => 'application/dns-message'];
				$Return_header = ['Content-Type' => 'application/dns-message'];
			break;

			default: break;
		}
		$t = explode('dns-query', $request->uri());
		$res = Requests::get(DOH_UPSTREAM.$t[1], $Request_header);
		return $connection->close(new Response(200, $Return_header, $res->body));
	}
	return $connection->close(new Response(400, ['Content-Type' => 'text/plain; charset=utf-8'], 'Bad Request'));
};

Worker::runAll();
