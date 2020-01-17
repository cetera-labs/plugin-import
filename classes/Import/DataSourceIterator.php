<?php
namespace Import;

class DataSourceIterator {
	
	protected static $dataSources = array();
	
	/*
	* Метод должен вернуть массив конфигараций полей ввода, служащих для настройки источника данных.
	*/
	public static function add($ds)
	{
		self::$dataSources[] = $ds;
	}
	
	public static function enum()
	{
		return self::$dataSources;
	}
	
	public static function factory( $id, $params )
	{
		foreach (self::$dataSources as $ds)
		{
			if ($ds['id'] == $id)
			{
				return new $ds['id']( $params );
			}
		}
		throw new \Exception( 'Не найден источник данных '.$id );
	}	
		
}