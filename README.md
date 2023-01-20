# DoH_Proxy
A simple DNS over HTTPS proxy based on workerman, support RFC1035 and RFC9230

# Start mode:
```php
define('START_MODE', 'HTTPS'); //HTTP or HTTPS
...
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
```
# install requirement
Ubuntu 20.04:
```
apt update
apt install php7.4-cli php7.4-curl composer -y
```
Fedora 37:
```
dnf update
dnf install php-cli php-json composer
```
then
```bash
git clone https://github.com/wwng2333/DoH_Proxy.git
composer install
php DoH_Proxy/DoH.php start -d
```
# Test 
Tool: https://github.com/natesales/q
```
root@OpenWrt:~# q @https://ip:port google.com
google.com. 5m0s A 142.251.42.238
google.com. 1h0m0s NS ns4.google.com.
google.com. 1h0m0s NS ns1.google.com.
google.com. 1h0m0s NS ns2.google.com.
google.com. 1h0m0s NS ns3.google.com.
google.com. 2m31s MX 10 smtp.google.com.
google.com. 1h0m0s TXT "facebook-domain-verification=22rm551cu4k0ab0bxsw536tlds4h95"
google.com. 1h0m0s TXT "google-site-verification=TV9-DBe4R80X4v0M4U_bd_J9cpOJM0nikft0jAgjmsQ"
google.com. 1h0m0s TXT "onetrust-domain-verification=de01ed21f2fa4d8781cbc3ffb89cf4ef"
google.com. 1h0m0s TXT "google-site-verification=wD8N7i1JTNTkezJ49swvWW48f8_9xveREV4oB-0Hf5o"
google.com. 1h0m0s TXT "MS=E4A68B9AB2BB9670BCE15412F62916164C0B20BB"
google.com. 1h0m0s TXT "v=spf1 include:_spf.google.com ~all"
google.com. 1h0m0s TXT "globalsign-smime-dv=CDYX+XFHUw2wml6/Gb8+59BsH31KzUr6c1l2BPvqKX8="
google.com. 1h0m0s TXT "docusign=1b0a6754-49b1-4db5-8540-d2c12664b289"
google.com. 1h0m0s TXT "atlassian-domain-verification=5YjTmWmjI92ewqkx2oXmBaD60Td9zWon9r6eakvHX6B77zzkFQto8PQ9QsKnbf4I"
google.com. 1h0m0s TXT "docusign=05958488-4752-4ef2-95eb-aa7ba8a3bd0e"
google.com. 1h0m0s TXT "apple-domain-verification=30afIBcvSuDV2PLX"
google.com. 1h0m0s TXT "webexdomainverification.8YX6G=6e6922db-e3e6-4a36-904e-a805c28087fa"

```
