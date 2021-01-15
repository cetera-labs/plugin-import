<?php
namespace Import\DataSource;

class MoySklad extends DataSourceAbstract 
{
	
	protected $fields = null;
	
	protected $page = 0;
	protected $index = 0;
	protected $goods_data = null;
	protected $client = null;
	
	const PAGE_SIZE = 10;
	
	public static function setup()
	{
		$t = \Cetera\Application::getInstance()->getTranslator();
		
		return array(
			array (
				'xtype'      => 'textfield',
				'fieldLabel' => 'Login',
				'allowBlank' => false,
				'name'       => 'login',
			),
			array (
				'xtype'      => 'textfield',
				'inputType'  => 'password',
				'fieldLabel' => 'Password',
				'allowBlank' => false,
				'name'       => 'password',
			),	
			array (
				'xtype'          => 'checkbox',
				'hideEmptyLabel' => false,
				'boxLabel'       => $t->_('пересчитывать цены, включив НДС'),
				'name'           => 'calculate_vat',
                'uncheckedValue' => 0,
			),			
		);
	}

	public function getFields()
	{
		$t = \Cetera\Application::getInstance()->getTranslator();
		
		$this->fields = array(
			'id' => 'ID Товара в формате UUID',
			'updated' => 'Время последнего обновления',
			'name' => 'Наименование Товара',
			'description' => 'Описание Товара',
			'code' => 'Код Товара',
			'externalCode' => 'Внешний код Товара',
			'archived' => 'Отметка о том, добавлен ли Товар в архив',
			'pathName' => 'Раздел',
			'vat' => 'НДС %',
			'effectiveVat' => 'Реальный НДС %',
			'image' => 'Изображение Товара',
			'minPrice' => 'Минимальная цена',
			'buyPrice' => 'Закупочная цена',
			//'salePrices' => 'Цены продажи',
			//'supplier' => 'Контрагент-поставщик',
			//'attributes' => 'Коллекция доп. полей.',
			//'country' => 'Ссылка на страну в формате Метаданных',
			//'article' => 'Артикул',
			//'weighed' => 'Весовой товар',
			'weight' => 'Вес',
			'volume' => 'Объём',
			//'packs' => 'Ссылка на упаковки Товара',
			//'barcodes' => 'Массив штрихкодов товара',
			//'alcoholic' => 'Объект, содержащий поля алкогольной продукции',
			//'modificationsCount' => 'Количество модификаций у данного товара',
			'minimumBalance' => 'Неснижаемый остаток',
			//'isSerialTrackable' => 'Учет по серийным номерам. Не может быть указан вместе с alcoholic и weighed',
			//'things' => 'Серийные номера',	
			'stock'		 => 'Остаток', 
			'reserve'	 => 'Резерв', 
			'inTransit'	 => 'Ожидание', 
			'quantity'	 => 'Доступно',
		);
		
		if (!$this->client) $this->client = new \GuzzleHttp\Client();	
		$clientParams = ['auth' => [$this->params['login'], $this->params['password']]];
		$res = $this->client->request('GET', 'https://online.moysklad.ru/api/remap/1.1/entity/product/metadata', $clientParams);
		$data = json_decode($res->getBody(), true);
		
		if (is_array($data['priceTypes'])) 
			foreach ($data['priceTypes'] as $key => $value) {
				$this->fields['salePrices_'.$key] = $value['name'];
			}
		
		return $this->fields;
	}
		
	public function fetch()
	{
		if (!$this->goods_data || !isset($this->goods_data[ $this->index ])) {
			$this->fetchPage();
		}
		if (!isset($this->goods_data[ $this->index ])) {
			$this->eof = true;
		}
		else {
			
			$key = $this->index++;
			
			$value = $this->goods_data[$key];
			unset($this->goods_data[$key]['meta']);
			unset($this->goods_data[$key]['owner']);
			unset($this->goods_data[$key]['group']);
			unset($this->goods_data[$key]['productFolder']);
			unset($this->goods_data[$key]['uom']);
			unset($this->goods_data[$key]['barcodes']);
			
			$this->goods_data[$key]['stock'] = floor($this->goods_data[$key]['stock']);
			if ($this->goods_data[$key]['stock'] < 0) $this->goods_data[$key]['stock'] = 0;
			
			$this->goods_data[$key]['reserve'] = floor($this->goods_data[$key]['reserve']);
			if ($this->goods_data[$key]['reserve'] < 0) $this->goods_data[$key]['reserve'] = 0;
			
			$this->goods_data[$key]['inTransit'] = floor($this->goods_data[$key]['inTransit']);
			if ($this->goods_data[$key]['inTransit'] < 0) $this->goods_data[$key]['inTransit'] = 0;
			
			$this->goods_data[$key]['quantity'] = floor($this->goods_data[$key]['quantity']);
			if ($this->goods_data[$key]['quantity'] < 0) $this->goods_data[$key]['quantity'] = 0;
			
			if ($value['buyPrice']) $this->goods_data[$key]['buyPrice'] = $value['buyPrice']['value'];
			
			if ($value['pathName']) {
				$this->goods_data[$key]['pathName'] = array();
				foreach (explode('/',$value['pathName']) as $section) {
					$this->goods_data[$key]['pathName'][] = array(
						'name' => trim($section),
					);
				}
			}
			
			if ($value['image']) {
				
				$cacheDir = CACHE_DIR.'/moysklad';
				if (!file_exists($cacheDir)) mkdir($cacheDir);
				$cacheFile = $cacheDir.'/'.$this->goods_data[$key]['id'];
				
				if (!file_exists($cacheFile) || time() - filemtime($cacheFile) > 3600*24  ) {
					$client = new \GuzzleHttp\Client();
					$clientParams = ['auth' => [$this->params['login'], $this->params['password']]];
					$res = $client->request('GET', $value['image']['meta']['href'], $clientParams);			
					$this->goods_data[$key]['image']['data'] = (string) $res->getBody();	
					file_put_contents($cacheFile, $this->goods_data[$key]['image']['data']);
				}
				else {
					$this->goods_data[$key]['image']['data'] = file_get_contents($cacheFile);
				}

				$this->goods_data[$key]['image']['filename'] = 	$this->goods_data[$key]['code'].'_'.$this->goods_data[$key]['image']['filename'];
				unset($this->goods_data[$key]['image']['meta']);
				unset($this->goods_data[$key]['image']['miniature']);
				unset($this->goods_data[$key]['image']['tiny']);
			}
			
			if (is_array($this->goods_data[$key]['salePrices'])) {				
				foreach ($this->goods_data[$key]['salePrices'] as $k => $price) {
					
					if ($this->params['calculate_vat']) {
						$vat = (int)$this->goods_data[$key]['effectiveVat'];
						$price['value'] = $price['value'] + $price['value']*$vat/100;
					}
					
					$this->goods_data[$key]['salePrices_'.$k] = $price['value']/100;
				}				
				unset($this->goods_data[$key]['salePrices']);				
			}			
			
			return $this->goods_data[ $key ];
		}		
	}
	
	private function fetchPage()
	{
		$this->goods_data = array();
		
		if (!$this->client) $this->client = new \GuzzleHttp\Client();
		
		$clientParams = ['auth' => [$this->params['login'], $this->params['password']]];
		
		$res = $this->client->request('GET', 'https://online.moysklad.ru/api/remap/1.1/entity/assortment?scope=product&limit='.self::PAGE_SIZE.'&offset='.(self::PAGE_SIZE*$this->page), $clientParams);	
		//$res = $this->client->request('GET', 'https://online.moysklad.ru/api/remap/1.1/entity/product?limit='.self::PAGE_SIZE.'&offset='.(self::PAGE_SIZE*$this->page), $clientParams);
		
		$data = json_decode($res->getBody(), true);			
		$this->goods_data = $data['rows'];	
		
		$this->index = 0;
		$this->page++;
	}
	
	public function sleep()
	{
		if ($this->page > 0) $this->page--;
		return serialize(array(
			'page' => $this->page,
			'index' => $this->index,
		));
	}
	
	public function wakeup($data)
	{
		$d = unserialize($data);
		$this->page = (int)$d['page'];
		$this->index = (int)$d['index'];
	}		
}