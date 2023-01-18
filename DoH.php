<?php
//composer install workerman
//bump to 4.x
use Workerman\Worker;
use Workerman\Connection\TcpConnection;
use Workerman\Protocols\Http\Request;
use Workerman\Protocols\Http\Response;
require_once __DIR__ . '/vendor/autoload.php';
define('DOH_UPSTREAM', 'https://1.1.1.1/dns-query');

// HTTPS 模式
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

// HTTP 模式
//$worker->onMessage = function(TcpConnection $con, $msg) {
//    $con->send('ok');
//};
//$http_worker = new Worker("http://0.0.0.0:2345");

$http_worker->count = 4;
$http_worker->name = 'DoH Proxy';
$http_worker->onMessage = function(TcpConnection $connection, Request $request)
{	
	$response_400 = new Response(400, [
			'Content-Type' => 'text/plain; charset=utf-8'
		], 'Bad Request');
	//var_dump($data);
	if($request->uri()=='/dns-query' and $request->rawBody()!='')
	{
		$curl = curl_init();
		curl_setopt($curl, CURLOPT_URL, DOH_UPSTREAM);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_POSTFIELDS, $request->rawBody());
		curl_setopt($curl, CURLOPT_HTTPHEADER, array("Content-Type: application/dns-message"));
		$res = curl_exec($curl);
		curl_close($curl);
		//var_dump('post', base64_encode($request->rawBody()));
		
		$response = new Response(200, [
			'Content-Type' => 'application/dns-message'
		], $res);
		return $connection->close($response);
	} 
	else if(stristr($request->uri(), '/dns-query?')) 
	{
		$t = explode('dns-query', $request->uri());
		$query_url = DOH_UPSTREAM.$t[1];
		
		//var_dump($request->header('accept'));
		switch(strtolower($request->header('accept')))
		{
			case "application/dns-json":
				$is_json = 1;
				$out = dns_query($query_url, 'json');
				//var_dump('dns-json: '.$query_url);
			break;
			
			case "application/dns-message":
				$is_json = 0;
				$out = dns_query($query_url, 'dns');
				//var_dump('dns-msg: '.$query_url);
			break;
			
			default: return $connection->close($response_400);
		}
		
		if($is_json) $header = array(["Content-Type: application/json; charset=UTF-8"]);
		else $header = array(["Content-Type: application/dns-message"]);

		$response = new Response(200, $header, $out);
		return $connection->close($response);
	} 
	else return $connection->close($response_400);
};

Worker::runAll();

function dns_query($url, $method)
{
	if($method == 'dns') $headerArray = array("Accept: application/dns-message");
	else $headerArray = array("Accept: application/dns-json");
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_TIMEOUT_MS, 1000);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_HTTPHEADER, $headerArray);
	$output = curl_exec($ch);
	curl_close($ch);
	return $output;
}
