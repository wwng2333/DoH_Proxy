<?php
use Workerman\Worker;
use Workerman\Connection\TcpConnection;
use Workerman\Protocols\Http\Request;
use Workerman\Protocols\Http\Response;

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

$http_worker->count = 4;
$http_worker->name = 'DoH Proxy';
$http_worker->onMessage = function(TcpConnection $connection, Request $request)
{	
	if(DEBUG) printf("recv:%s, %s at %s\n", $request->method(), $request->header('accept'), $request->uri());
	$response_400 = new Response(400, [
			'Content-Type' => 'text/plain; charset=utf-8'
		], 'Bad Request');
	if($request->uri()==ENDPOINT_PATH and !empty($request->rawBody()))
	{
		$curl = curl_init();
		curl_setopt($curl, CURLOPT_URL, DOH_UPSTREAM);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_POSTFIELDS, $request->rawBody());
		curl_setopt($curl, CURLOPT_HTTPHEADER, array("Content-Type: application/dns-message"));
		$res = curl_exec($curl);
		curl_close($curl);
		$response = new Response(200, [
			'Content-Type' => 'application/dns-message'
		], $res);
		return $connection->close($response);
	} 
	else if(stristr($request->uri(), ENDPOINT_PATH.'?')) 
	{
		$t = explode('dns-query', $request->uri());
		$query_url = DOH_UPSTREAM.$t[1];
		switch(strtolower($request->header('accept')))
		{
			case "application/dns-json":
				$is_json = 1;
				$out = dns_query($query_url, 'json');
			break;
			
			case "application/dns-message":
				$out = dns_query($query_url, 'dns');
			break;
			
			default: return $connection->close($response_400);
		}
		if(!empty($is_json)) $header = array(["Content-Type: application/json; charset=UTF-8"]);
		else $header = array(["Content-Type: application/dns-message"]);
		$response = new Response(200, $header, $out);
		return $connection->close($response);
	} 
	else return $connection->close($response_400);
};

function dns_query($url, $method)
{
	$headerArray = ($method == 'dns') ? array("Accept: application/dns-message") : array("Accept: application/dns-json");
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_TIMEOUT_MS, 1000);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_HTTPHEADER, $headerArray);
	$output = curl_exec($ch);
	curl_close($ch);
	return $output;
}

Worker::runAll();
