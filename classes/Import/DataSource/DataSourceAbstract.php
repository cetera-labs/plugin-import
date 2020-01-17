<?php
namespace Import\DataSource;

abstract class DataSourceAbstract {
	
	public $params = [];
	protected $data = [];
	
	/*
	* Флаг того, что данные закончились.
	*/	
	public $eof = false;
	
	/*
	* Метод должен вернуть массив конфигараций полей ввода, служащих для настройки источника данных.
	*/
	public static function setup() {
		return array();
	}
	
	public function __construct($params) {
		if (isset($params['datasource_data'])) {
			$this->wakeup($params['datasource_data']);
			unset($params['datasource_data']);
		}
		$this->params = $params;
	}
	
	/*
	* Метод должен вернуть массив полей, которые есть в источнике данных.
	*/	
	abstract public function getFields();
	
	/*
	* Метод должен вернуть очередную порцию данных. Если данных больше нет, то устанавливает $eof = true и возвращает null
	*/		
	abstract public function fetch();
	
	public function sleep() {
		return serialize($this->data);
	}
	
	public function wakeup($data) {
		$this->data = unserialize($data);
	}	
		
}