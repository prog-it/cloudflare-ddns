<?php

/**
* Класс удобного использования файла конфигурации
*
* @param $path Путь к конфиг-файлу
* @param $default Возвращаемое значение по умолчанию, если параметра в конфиге нет
*
*/

class Config {
	/** @var string */
    protected static $path = './inc/config.php';
	/** @var array */
    protected static $data;
	/** @var */
    protected static $default = null;
	
	/**
	* Инициализация конфиг-файла
	*
	* @return array Массив параметров
	*/
	private static function init() {
		if ( !isset(self::$data) ) {
			if ( file_exists(self::$path) ) {
				self::$data = require_once self::$path;
			} else {
				exit('File '.self::$path.' not exists'.PHP_EOL);
			}
		}		
		return self::$data;
	}
	
	/**
	* Задать другой путь к конфиг-файлу
	*
	* @param string $path Путь к другому конфиг-файлу
	*
	* @return boolean TRUE Если удалось установить новый путь к конфиг-файлу
	*/
	public static function setPath($path) {
		$res = false;
		if ( isset($path) && file_exists($path) ) {
			self::$path = $path;
			$res = true;
		}
		return $res;
	}

	/**
	* Получить значение параметра
	*
	* @param string $key Параметр, значение которого необходимо получить
	* @param $default Возвращаемое значение, если такой параметр отсутствует
	*
	* @return Значение конфига
	*/
    public static function get($key, $default = null) {
        self::$default = $default;

        $segments = explode('.', $key);
        $data = self::init();

        foreach ($segments as $segment) {
            if (isset($data[$segment])) {
                $data = $data[$segment];
            } else {
                $data = self::$default;
                break;
            }
        }
        return $data;
    }
	
	/**
	* Существует ли такой параметр
	*
	* @param string $key Параметр, значение которого необходимо проверить
	*
	* @return boolean TRUE Если такой параметр существует
	*/
    public static function exists($key) {
        return self::get($key) !== self::$default;
    }
}