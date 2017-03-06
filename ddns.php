<?php error_reporting(E_ALL | E_STRICT); ini_set('display_errors', 'On'); 

chdir(__DIR__);

require_once 'inc/Config.class.php';
require_once 'inc/Cloudflare.class.php';
require_once 'inc/func.php';
require_once 'inc/BackgroundProcess.class.php';


$args = getopt('', ['token:']);
if ( !isset($args['token']) || $args['token'] != Config::get('cron.token') ) {
	exit('Incorrect startup token' . PHP_EOL);
}

# Заполнены необходимые поля в конфиге
foreach (['email', 'key', 'domain', 'ttl'] as $key) {
	$value = Config::get('api.'.$key);
	if ( !isset($value) || $value == '' ) {
		exit('In config is empty value: ' . $key . PHP_EOL);
	}
}

# Заполнены в конфиге имена записей, параметры которых нужно изменить
$records = Config::get('api.records');
if ( !isset($records) || count($records) == 0 )	{
	exit('In config are not set name entries , the settings you want to change' . PHP_EOL);
}

# Включена проверка изменения IPv4 или IPv6
if ( Config::get('ip.ipv4_enabled') === false && Config::get('ip.ipv6_enabled') === false ) {
	exit('In config checking IP changes is disabled' . PHP_EOL);
}

# Запуск проверки изменения IPv4
if ( Config::get('ip.ipv4_enabled') === true ) {
	$cmd = Config::get('php.path') . ' worker.php --token="'.Config::get('cron.token').'" --type="ipv4"';
	$proc = new BackgroundProcess($cmd);
	$proc->run();
	echo 'Checking IPv4 changes is started' . PHP_EOL;
	unset($proc);
}

# Запуск проверки изменения IPv6
if ( Config::get('ip.ipv6_enabled') === true ) {
	$cmd = Config::get('php.path') . ' worker.php --token="'.Config::get('cron.token').'" --type="ipv6"';
	$proc = new BackgroundProcess($cmd);
	$proc->run();
	echo 'Checking IPv6 changes is started' . PHP_EOL;
	unset($proc);
}


