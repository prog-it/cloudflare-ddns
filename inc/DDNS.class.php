<?php

/**
* DDNS
*
* DDNS класс обновления IP
*
* @copyright	2017 progit
* @link 		https://github.com/prog-it/cloudflare-ddns
*/

class DDNS {
	/** @var string */
	private $ip_prev = null;
	/** @var string */
	private $ip_curr = null;
	/** @var string */
	private $ip_type = 'ipv4';
	/** @var string */
	private $rec_type = 'A';
	
	/**
	* Получить страницу
	*
	* @param array $p Параметры cURL
	*
	* @return boolean FALSE Если страницу не удалось получить
	*/
	private static function getPage($p) {
		$cURL = [
			'Useragent' => Config::get('curl.useragent'),
			'Timeout' => Config::get('curl.timeout'),
			'Codes' => Config::get('curl.codes'),
		];		
		
		$p['Useragent'] = isset($p['Useragent']) ? $p['Useragent'] : $cURL['Useragent'];
		$p['Timeout'] = isset($p['Timeout']) ? $p['Timeout'] : $cURL['Timeout'];
		$p['Codes'] = isset($p['Codes']) ? $p['Codes'] : $cURL['Codes'];
		$p['Cookie'] = isset($p['Cookie']) ? $p['Cookie'] : false;

		$ch = curl_init($p['Url']);
		curl_setopt($ch, CURLOPT_URL, $p['Url']);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($ch, CURLOPT_ENCODING, '');
		curl_setopt($ch, CURLOPT_USERAGENT, $p['Useragent']);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $p['Timeout']);
		curl_setopt($ch, CURLOPT_TIMEOUT, $p['Timeout']);
		curl_setopt($ch, CURLOPT_MAXREDIRS, 10);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
		curl_setopt($ch, CURLOPT_HEADER, false);
		if ($p['Cookie'] !== false) {
			curl_setopt($ch, CURLOPT_COOKIEJAR, $p['Cookie']);
			curl_setopt($ch, CURLOPT_COOKIEFILE, $p['Cookie']);
		}
		$content = curl_exec($ch);
		$hc = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$err = curl_errno($ch);
		$errmsg = curl_error($ch);
		curl_close($ch);
		if ( !$err && !$errmsg && in_array($hc, $p['Codes']) ) { 
			return $content; 
		}
		return false;
	}	
	
	/**
	* IP является IPv4 адресом
	*
	* @return boolean TRUE Если IP является IPv4 адресом
	* @return boolean FALSE Если IP является IPv6 адресом
	*/
	public function is_ipv4() {
		if ( $this->ip_type == 'ipv4' ) {
			return true;
		} else {
			return false;
		}
	}
	
	/**
	* Задать тип IP - IPv4
	*
	* @return void
	*/	
	public function set_type_ipv4() {
		$this->ip_type = 'ipv4';
		$this->rec_type = 'A';
	}
	
	/**
	* Задать тип IP - IPv6
	*
	* @return void
	*/
	public function set_type_ipv6() {
		$this->ip_type = 'ipv6';
		$this->rec_type = 'AAAA';
	}
	
	/**
	* Получить тип создаваемой записи в DNS
	*
	* @return string Тип DNS записи
	*/	
	public function getRecType() {
		return $this->rec_type;
	}
	
	/**
	* Проверка корректности IP адреса
	*
	* @param string $ip IP адрес
	*
	* @return boolean FALSE Если IP не корректен
	*/
	public function validateIP($ip) {
		$res = false;
		$ip = trim($ip);
		if ($ip) {
			if ( $this->is_ipv4() ) {
				$res = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4);
			} else {
				$res = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6);
			}
			$res = $res == $ip ? true : false;
		}
		return $res;
	}

	/**
	* Установить предыдущий IP
	*
	* @param string $ip IP адрес
	*
	* @return void
	*/
	private function setPrevIP($ip) {
		$this->ip_prev = $ip;
	}
	
	/**
	* Получить предыдущий IP
	*
	* @return string IP адрес
	*/
	public function getPrevIP() {
		$res = $this->ip_prev;
		if ( !$res ) {
			$path = $this->is_ipv4() ? Config::get('db.ipv4') : Config::get('db.ipv6');
			if ( file_exists($path) ) {
				$ip = trim( file_get_contents($path) );
				if ($ip) {
					$this->setPrevIP($ip);
					$res = $ip;
				}
			} else {
				file_put_contents($path, '');
			}
		}
		return $res;
	}	
	
	/**
	* Обновить предыдущий IP
	*
	* @return boolean FALSE Если IP НЕ удалось записать в файл
	*/	
	public function updatePrevIP() {
		$res = false;
		$path = $this->is_ipv4() ? Config::get('db.ipv4') : Config::get('db.ipv6');
		if ( file_exists($path) ) {
			$fp = file_put_contents( $path, $this->getCurrIP() );
			$res = $fp !== false ? true : false;
		}
		return $res;
	}	
	
	/**
	* Вырезать IP из WEB-страницы
	*
	* @param string $content Контент WEB-страницы
	*
	* @return string IP адрес
	* @return NULL Если IP НЕ найден на странице
	*/
	private function cutIPFromPage($content) {
		$res = null;
		if ( $this->is_ipv4() ) {
			$regex = '#\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}#';
		} else {
			$regex = '#(?:(?:(?:[0-9A-Fa-f]{1,4}:){6}|::(?:[0-9A-Fa-f]{1,4}:){5}|(?:[0-9A-Fa-f]{1,4})?::(?:[0-9A-Fa-f]{1,4}:){4}|(?:(?:[0-9A-Fa-f]{1,4}:){0,1}[0-9A-Fa-f]{1,4})?::(?:[0-9A-Fa-f]{1,4}:){3}|(?:(?:[0-9A-Fa-f]{1,4}:){0,2}[0-9A-Fa-f]{1,4})?::(?:[0-9A-Fa-f]{1,4}:){2}|(?:(?:[0-9A-Fa-f]{1,4}:){0,3}[0-9A-Fa-f]{1,4})?::[0-9A-Fa-f]{1,4}:|(?:(?:[0-9A-Fa-f]{1,4}:){0,4}[0-9A-Fa-f]{1,4})?::)(?:[0-9A-Fa-f]{1,4}:[0-9A-Fa-f]{1,4}|(?:(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.){3}(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?))|(?:(?:[0-9A-Fa-f]{1,4}:){0,5}[0-9A-Fa-f]{1,4})?::[0-9A-Fa-f]{1,4}|(?:(?:[0-9A-Fa-f]{1,4}:){0,6}[0-9A-Fa-f]{1,4})?::)#i';
		}
		preg_match($regex, $content, $out);
		if ( isset($out[0]) ) {
			$res = trim($out[0]);
		}
		return $res;
	}

	/**
	* Установить текущий IP
	*
	* @param string $ip IP адрес
	*
	* @return void
	*/
	private function setCurrIP($ip) {
		$this->ip_curr = $ip;
	}
	
	/**
	* Получить текущий IP
	*
	* @return string IP адрес
	*/
	public function getCurrIP() {
		return $this->ip_curr;
	}	
	
	/**
	* Получить новый IP
	*
	* @return string IP адрес
	* @return NULL Если IP получить НЕ удалось
	*/
	public function getNewIP() {
		$res = null;
		$services = $this->is_ipv4() ? Config::get('services.ipv4') : Config::get('services.ipv6');
		$loops = Config::get('ip.loops');
		shuffle($services);
		
		for ($i = 0; $i < $loops; $i++) {
			$rndKey = array_rand($services);
			// Случайный URL
			$p['Url'] = $services[$rndKey];
			$content = $this->getPage($p);
			if ( $content !== false ) {
				$content = Replacer($content);
				// Вырезать IP из страницы
				$ip = Replacer( $this->cutIPFromPage($content) );
				// Включена проверка корректности IP
				if ( Config::get('ip.validate') === true ) {
					// IP корректный
					if ( $this->validateIP($ip) === true ) {
						$res = $ip;
						break;
					}
				} else {
					if ($ip) {
						$res = $ip;
						break;					
					}
				}
			}
			unset($services[$rndKey]);
		}
		$this->setCurrIP($ip);
		return $res;
	}
	
	/**
	* Изменился ли текущий IP
	*
	* @return TRUE Если IP адрес изменился
	*/
	public function isChanged() {
		return $this->getPrevIP() != $this->getCurrIP();
	}
	
	/**
	* Очистка Лога при превышении размера файла
	*
	* @return void
	*/
	private function cleanLog() {
		$path = Config::get('db.log');
		if ( Config::get('log.clean') === true && file_exists($path) && filesize($path) > Config::get('log.max_filesize')*1024 ) {
			file_put_contents($path, '', LOCK_EX);
		}
	}	
	
	/**
	* Запись в Лог
	*
	* @param string $text Текст записи
	* @param boolean $detail Является ли запись подробной
	*
	* @return boolean TRUE Если запись в файл успешна
	*/
	public function writeLog($text, $detail = false) {
		$res = false;
		if ( Config::get('log.enabled') === true &&  ( (Config::get('log.detail') === false && $detail === false) || Config::get('log.detail') === true ) ) {
			if ($text) {
				if ( $this->getPrevIP() || $this->getCurrIP() ) {
					$text = $this->getPrevIP() . Config::get('log.delim') . $this->getCurrIP() . Config::get('log.delim') . $text;
				}
				$this->cleanLog();
				$fp = file_put_contents( Config::get('db.log'), getTime() . Config::get('log.delim') . $text . PHP_EOL, FILE_APPEND | LOCK_EX );
				$res = $fp !== false ? true : false;
			}
		}
		return $res;
	}

}


