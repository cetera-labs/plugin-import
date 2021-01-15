<?php
namespace Import\DataSource;

class CSV extends  DataSourceAbstract {
	
	protected $fh = null;
	protected $data = array(
		'offset' => 0
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
				'fieldLabel' => $t->_('Кодировка'),
				'allowBlank' => false,
				'name'       => 'source_charset',
				'value'      => 'windows-1251',
			),
			array (
				'xtype'      => 'radio',
				'checked'    => true,
				'fieldLabel' => $t->_('Разделитель полей'),
				'boxLabel'   => 'точка с запятой',
				'name'       => 'source_delimeter',
				'inputValue' => ';',
			),
			array (
				'xtype'      => 'radio',
				'hideEmptyLabel' => false,
				'boxLabel'   => $t->_('запятая'),
				'name'       => 'source_delimeter',
				'inputValue' => ',',
			),
			array (
				'xtype'      => 'radio',
				'hideEmptyLabel' => false,
				'boxLabel'   => $t->_('табуляция'),
				'name'       => 'source_delimeter',
				'inputValue' => '\t',
			),
			array (
				'xtype'      => 'checkbox',
				'fieldLabel' => $t->_('Первая строка содержит имена полей'),
				'checked'    => true,
				'name'       => 'source_skip_first',
				'inputValue' => 1,
                'uncheckedValue' => 0,
			),
		);
	}	
	
	public function getFields()
	{
		$d = $this->params['source_delimeter'];
		$charset = $this->params['source_charset'];

		$this->openFile();		

		$fields = array();
		if ($this->params['source_skip_first'])
		{
			$f = fgetcsv ( $this->fh, 0, $d );
			if (is_array($f)) foreach ($f as $i => $name)
			{
				$fields[] = 'Поле '.($i+1).' ('.iconv($charset,'utf-8',$name).')';
			}
		}
		else
		{
			$max = 0;
			while ( $f = fgetcsv ( $this->fh, 0, $d ) )
			{
				if (count($f) > $max) $max = count($f);
			}
			for ($i = 1; $i <= $max; $i++)
			{
				$fields[] = 'Поле '.$i;
			}
		}		
		$this->closeFile();
		return $fields;
	}
	
	protected function openFile()
	{
		if (!$this->fh)
		{
			$file = WWWROOT.$this->params['source_file'];
			if (!file_exists($file)) throw new \Exception('File '.$this->params['file'].' is not found.');
			$this->fh = fopen($file, 'r');
		}
	}
	
	protected function closeFile()
	{
		if ($this->fh)
		{
			fclose($this->fh);
            $this->fh = null;
		}
	}	
	
	public function fetch()
	{
		$this->openFile();

		if ($this->data['offset'] > ftell ( $this->fh ))
		{
			fseek($this->fh, $this->data['offset']);
		}
		
		$f = fgetcsv ( $this->fh, 0, $this->params['source_delimeter'] );
		
		if (!$this->data['offset'] && $this->params['source_skip_first'])
		{
			$f = fgetcsv ( $this->fh, 0, $this->params['source_delimeter'] );
		}
		
		if ($f)
		{
			foreach ($f as $i => $value)
			{
				$f[$i] = iconv($this->params['source_charset'],'utf-8',$value);
			}
		}
		else
		{
			$this->eof = true;
		}
		
		$this->data['offset'] = ftell ( $this->fh );
		
		return $f;
	}	
	
}