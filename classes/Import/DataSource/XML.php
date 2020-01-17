<?php
namespace Import\DataSource;

class XML extends DataSourceAbstract {

	protected $xml_source = null;
	protected $xml_data = null;
	protected $fields = null;
	protected $data = array(
		'index' => 0
	);	

	public static function setup()
	{
		$t = \Cetera\Application::getInstance()->getTranslator();
		
		return array(
			array (
				'xtype'      => 'fileselectfield',
				'fieldLabel' => $t->_('Файл'),
				'allowBlank' => false,
				'name'       => 'source_file'
			),
			array (
				'xtype'      => 'textfield',
				'fieldLabel' => $t->_('Элемент с данными материала'),
				'allowBlank' => false,
				'name'       => 'source_element',
			),
		);
	}
	
	public function getData()
	{
		if ($this->xml_data === null)
		{
			$t = \Cetera\Application::getInstance()->getTranslator();
			
			$file = WWWROOT.$this->params['source_file'];
			if (!file_exists($file)) throw new \Exception('File '.$this->params['file'].' is not found.');
			
			libxml_use_internal_errors(true);
			$path_parts = pathinfo($file);
			
			if (strtolower($path_parts['extension']) == 'zip')
			{
				$zip = new \ZipArchive();
				$zip->open($file);
				$idx = 0;
				$xml = null;
				while ($name = $zip->getNameIndex ( $idx ))
				{
					$path_parts = pathinfo($name);
					if (strtolower($path_parts['extension']) == 'xml')
					{
						$xml = simplexml_load_string( $zip->getFromIndex( $idx ) );
						break;
					}
					$idx++;
				}
				if ($xml === null) throw new \Exception( "XML not found\n" );
			}
			else
			{
				$xml = simplexml_load_file( $file );
			}
			
			if ($xml === false)
			{
				$error = "XML error\n";
				foreach(libxml_get_errors() as $error)
				{
					$error .= "\t".$error->message."\n";
				}
				throw new \Exception( $error );
			}	
			
			$this->xml_source = clone($xml);
			
			$path = explode('/', $this->params['source_element']);
			foreach ($path as $p)
			{
				if (!property_exists($xml, $p) )
				{
					throw new \Exception( $t->_('Не найден элемент').' '.$this->params['source_element'] );
				}	
				$xml = $xml->{$p};
			}
			
			$this->xml_data = $xml;
			
		}
	}
	
	public function getFields()
	{
		if ($this->fields === null)
		{
			$this->getData();
			$v = get_object_vars($this->xml_data[0]);
			unset($v['@attributes']);
			$this->fields = array_keys( $v );
		}		
		return $this->fields;
	}
	
	public function fetch()
	{
		$this->getData();
		if (!isset($this->xml_data[ $this->data['index'] ]))
		{
			$this->eof = true;
			return false;
		}
		$f = array();
		
		$fields = $this->getFields();
		$values = get_object_vars($this->xml_data[ $this->data['index'] ]);
		
		foreach($fields as $id => $field)
		{
			if (is_object($values[$field]))
			{
				$f[$id] = null;
			}
			else
			{
				$f[$id] = $values[$field];
				while (is_array($f[$id]))
				{
					$f[$id] = array_shift($f[$id]);
				}
			}			
		}		
		$this->data['index']++;	
		return $f;
	}

}