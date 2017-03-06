<?php error_reporting(E_ALL | E_STRICT); ini_set('display_errors', 'On'); 

chdir(__DIR__);

require_once 'inc/Config.class.php';
require_once 'inc/Cloudflare.class.php';
require_once 'inc/func.php';
require_once 'inc/DDNS.class.php';

##
# Воркер обновления IPv4 и IPv6
##


$args = getopt('', ['token:', 'type:']);

# Указан ключ запуска CRON и тип IP (IPv4, IPv6)
if ( 
	!isset($args['token']) || $args['token'] != Config::get('cron.token') || 
	!isset($args['type']) || !in_array($args['type'], ['ipv4', 'ipv6']) 
	) {
	exit;
}

$type = 'IPv4';
$ddns = new DDNS;
# Установить тип IP
if ( $args['type'] == 'ipv4') {
	$ddns->set_type_ipv4();
} else {
	$ddns->set_type_ipv6();
	$type = 'IPv6';
}

# Старый IP
$ip['prev'] = $ddns->getPrevIP();
# Новый IP
$ip['curr'] = $ddns->getNewIP();	

# Удалось получить текущий IP
if ( $ip['curr'] !== null ) {
	# IP изменился
	if ( $ddns->isChanged() ) {
		$api = new Cloudflare( Config::get('api.email'), Config::get('api.key') );
		# Задать доп. параметры cURL
		$api->setCurlParams([
			'useragent' => Config::get('curl.useragent'),
			'timeout'   => Config::get('curl.timeout'),
			'cookie'    => Config::get('db.cookie'),
		]);
		$domain = Config::get('api.domain');
		$zone = $api->getZone($domain);
		if ( !$zone )  {
			$ddns->writeLog('Domain ' . $domain . ' not found', true);
			exit;
		}
		# Количество ошибок обновления IP
		$errors = 0;
		$records = Config::get('api.records');
		foreach ($records as $rec) {
			$rec = trim($rec);
			# Пропуск пустой записи
			if ( !$rec ) {
				continue;
			}
			$dnsRecords = $api->getZoneDnsRecords($zone->id, ['name' => $rec]);
			$dnsRecord = null;
			if ($dnsRecords) {
				$dnsSearch = array_filter($dnsRecords, function($a) {
					global $rec, $ddns;
					return $a->name == $rec && $a->type == $ddns->getRecType();
				});
				if ($dnsSearch) {
					$dnsRecord = array_shift($dnsSearch);
				}
			}

			# Отсутствует DNS запись
			if ( !$dnsRecord ) {
				$ans = $api->createDnsRecord( $zone->id, $ddns->getRecType(), $rec, $ip['curr'], ['ttl' => Config::get('api.ttl')] );
				# Запись НЕ создана
				if ( !isset($ans->id) ) {
					$errors += 1;
					$ddns->writeLog('Failed create '.$type.' DNS record: ' . $rec, true);
				} else {
					$ddns->writeLog('Created '.$type.' DNS record: ' . $rec, true);
				}
			}
			# Параметры DNS записи отличаются
			else if ( $dnsRecord->type != $ddns->getRecType() || $dnsRecord->content != $ip['curr'] || $dnsRecord->ttl != Config::get('api.ttl') ) {
				$ans = $api->updateDnsRecord($zone->id, $dnsRecord->id, [
				  'type'    => $ddns->getRecType(),
				  'name'    => $rec,
				  'content' => $ip['curr'],
				  'ttl'     => Config::get('api.ttl'),
				]);
				# Запись НЕ обновлена
				if ( !isset($ans->id) ) {
					$errors += 1;
					$ddns->writeLog('Failed update '.$type.' DNS record: ' . $rec, true);
				} else {
					$ddns->writeLog('Updated '.$type.' DNS record: ' . $rec, true);
				}
			}
			else {
				$ddns->writeLog('Settings '.$type.' DNS record: ' . $rec . ' not changed', true);
			}
		}		

		# Обновлен IP у всех записей
		if ( $errors === 0 ) {
			$ddns->updatePrevIP();
			$ddns->writeLog('All ' . $type . ' DNS records updated');
		} else {
			$ddns->writeLog('Failed edit IP for some records');
		}		
		
	} else {
		$ddns->writeLog($type . ' not changed', true);
	}
	
} else {
	$ddns->writeLog('Failed get current ' . $type, true);
}


