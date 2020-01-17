<?php
namespace Import\DataSource;

class CommerceML2 extends DataSourceAbstract
{
    public $groups = null;
    public $props = null;
    protected $fields = null;
    protected $xml_import = null;
    protected $xml_offers = null;
    protected $offers_data = null;
    protected $data = array(
        'index' => 0,
        'file_import' => null,
        'file_offers' => null
    );

    public static function setup()
    {
		$t = \Cetera\Application::getInstance()->getTranslator();
		
        return array(
            array(
                'xtype' => 'fileselectfield',
                'fieldLabel' => $t->_('Файл ZIP'),
                'allowBlank' => true,
                'name' => 'source_file'
            ),
            array(
                'xtype' => 'fileselectfield',
                'fieldLabel' => $t->_('Файл товары'),
                'allowBlank' => true,
                'name' => 'source_import',
                'value' => 'import0_1.xml'
            ),
            array(
                'xtype' => 'fileselectfield',
                'fieldLabel' => $t->_('Файл цены'),
                'allowBlank' => true,
                'name' => 'source_offers',
                'value' => 'offers0_1.xml'
            ),
        );
    }

    public function parseProps()
    {
        if ($this->props === null) {
            $this->props = array();
            $this->getData();
            if (empty($this->params["materials_type"])) return;

			if (!$this->xml_import) return;
            if (!property_exists($this->xml_import, 'Классификатор')) return;
            if (!property_exists($this->xml_import->{'Классификатор'}, 'Свойства')) return;

            foreach ($this->xml_import->{'Классификатор'}->{'Свойства'}->{'Свойство'} as $p)
			{
                $id = (string)$p->{'Ид'};
                $name = trim((string)$p->{'Наименование'});				
				
                if (empty($name)) continue;

                switch ((string)$p->{'ТипЗначений'}) {
                    case 'Строка':
                        $this->props[$id] = Array(
                            "name" => $name,
                            "type" => (string)$p->{'ТипЗначений'},
                        );
                        break;
						
                    case 'Справочник':
					
                        $this->props[$id] = Array(
                            "name" => $name,
                            "type" => (string)$p->{'ТипЗначений'},
                        );

                        $variants = Array();
                        foreach ($p->{'ВариантыЗначений'}->{'Справочник'} as $variant)
						{											
                            if (is_object($variant)) {
                                $val = str_replace("'", "\"", (string)$variant->{'Значение'});
								$variants[(string)$variant->{'ИдЗначения'}] = $val;
                            }
                        }

                        if (!count($variants)) {
                            unset($this->props[$id]);
                            continue;
                        }

                        $this->props[$id]["variants"] = $variants;
                        break;
						
                    case 'Число':
                        $this->props[$id] = Array(
                            "name" => $name,
                            "type" => (string)$p->{'ТипЗначений'},
                        );
                        break;
                    default:
                        continue;
                }
            }
                 
        }
    }

    public function getData()
    {
        if ($this->xml_import === null) {
            $this->xml_import = $this->parseXML($this->getImportFile());
            $this->xml_offers = $this->parseXML($this->getOffersFile());
        }
    }

    private function parseXML($file)
    {
        if (!$file) return null;
        if (!file_exists($file) || !is_file($file)) throw new \Exception('Файл ' . $file . ' не найден.');
        libxml_use_internal_errors(true);
        $xml = simplexml_load_file($file);
        if ($xml === false) {
            $error = "Ошибка загрузки XML\n";
            foreach (libxml_get_errors() as $error) {
                $error .= "\t" . $error->message . "\n";
            }
            throw new \Exception($error);
        }

        return $xml;
    }

    private function getImportFile()
    {
        if ($this->data['file_import'] === null && $this->params['source_import']) {
            if ($this->params['source_file'] && $this->params['source_import']) {
                $this->data['file_import'] = $this->getFileFromArchive($this->params['source_import']);
            } else {
                $this->data['file_import'] = WWWROOT . $this->params['source_import'];
            }
        }

        return $this->data['file_import'];
    }

    private function getFileFromArchive($filename)
    {
        $file = WWWROOT . $this->params['source_file'];
        if (!file_exists($file)) throw new \Exception('File ' . $this->params['file'] . ' is not found.');
        $zip = new \ZipArchive();
        $zip->open($file);

        $idx = 0;
        $result = null;
        while ($name = $zip->getNameIndex($idx)) {
            $path_parts = pathinfo($name);
            if ($path_parts['basename'] == $filename) {
		$tmp_dir = ini_get('upload_tmp_dir') or sys_get_temp_dir();
                $result = tempnam($tmp_dir, "xml");
                file_put_contents($result, $zip->getFromIndex($idx));
                break;
            }
            $idx++;
        }
        if ($result === null) throw new \Exception("File " . $filename . " is not found\n");

        return $result;
    }

    private function getOffersFile()
    {
        if ($this->data['file_offers'] === null && $this->params['source_offers']) {
            if ($this->params['source_file'] && $this->params['source_offers']) {
                $this->data['file_offers'] = $this->getFileFromArchive($this->params['source_offers']);
            } else {
                $this->data['file_offers'] = WWWROOT . $this->params['source_offers'];
            }
        }

        return $this->data['file_offers'];
    }

    public function cleanup()
    {
        if ($this->data['file_offers']) unlink($this->data['file_offers']);
        if ($this->data['file_import']) unlink($this->data['file_import']);
        $this->data['file_import'] = null;
        $this->data['file_offers'] = null;
    }

    public function fetch()
    {
        $this->getData();
		$this->parseProps();

        if (!$this->xml_import) {
            return $this->fetchOffer();
        }

        $goods = $this->getGoodsData();

        if (!isset($goods[$this->data['index']])) {
            $this->eof = true;

//			$this->cleanup();
            return false;
        }

        $this->parseGroups();
        $values = get_object_vars($goods[$this->data['index']]);

        if ($values['@attributes']['Статус'] == 'Удален') {
            $this->data['index']++;

            return $this->fetch();
        }

        $good_id = (string)$goods[$this->data['index']]->{'Ид'};

        $f = $this->parseValues($good_id, $values);

        $this->data['index']++;
        //$this->eof = true;
        //print_r($f);
        //die();
        return $f;
    }

	
    public function fetchOffer()
    {
        if (!$this->xml_offers) {
            $this->eof = true;
            return false;
        }

		$this->getOffersData();				
        $offers = array_values($this->offers_data);

        if (!isset($offers[$this->data['index']])) {
            $this->eof = true;
            return false;
        }

        $values = get_object_vars($offers[$this->data['index']]);

        list($good_id) = explode('#', (string)$offers[$this->data['index']]->{'Ид'});
        $f = $this->parseValues($good_id, $values);
        $f[0] = $good_id;

        $this->data['index']++;
        //$this->eof = true;
        //print_r($f);
        //die();
        return $f;
    }

    protected function getOffersData()
    {
        if ($this->offers_data === null) {
            $this->getData();
            if ($this->xml_offers) {
                $this->offers_data = array();
                foreach ($this->xml_offers->{'ПакетПредложений'}->{'Предложения'}->{'Предложение'} as $d) {
                    list($goods_id) = explode('#', (string)$d->{'Ид'});
                    $this->offers_data[$goods_id] = $d;
                }
            } else {
                $this->offers_data = false;
            }
        }
    }

    protected function parseValues($good_id, $values)
    {
        $f = array();
					
		if (isset($values['ЗначенияСвойств'])) {			
			foreach ($values['ЗначенияСвойств'] as $prop) {
				$prop = (array)$prop;
				$f['prop_'.$prop['Ид']] = ($prop['Ид'] && $prop['Значение'] && isset($this->props[$prop['Ид']]['variants'])) ? $this->props[$prop['Ид']]['variants'][$prop['Значение']] : $prop['Значение'];
			}						
		}
		
		if (isset($values['ЗначенияРеквизитов'])) {			
			foreach ($values['ЗначенияРеквизитов'] as $r) {
				$r = (array)$r;
				$f['ЗначенияРеквизитов_' . $r['Наименование']] = $r['Значение'];
			}
		}	
		
		if (isset($values['СтавкиНалогов'])) {			
			foreach ($values['СтавкиНалогов'] as $r) {
				$r = (array)$r;
				$f['СтавкиНалогов_' . $r['Наименование']] = $r['Значение'];
			}
		}		

		if (isset($values['ХарактеристикиТовара'])) {		
			foreach ($values['ХарактеристикиТовара'] as $r) {
				$r = (array)$r;
				$f['ХарактеристикиТовара'] .= $r['Наименование'].': '.$r['Значение'].'<br>';
			}
		}	
		
		$gid = (string)$values['Группы'][0]->{'Ид'};
		if ($gid && isset($this->groups[$gid])) {
			$f['Группы'] = array();
			$p = $this->groups[$gid];
			do {
				$f['Группы'][] = array(
					'id' => $p['id'],
					'name' => $p['name'],
				);
				$p = $this->groups[$p['parent']];
			} while ($p);
			$f['Группы'] = array_reverse($f['Группы']);
		}		

		$this->getStock($good_id, $f);	
		$this->getPrice($good_id, $f);
		
        $fields = $this->getFields();

        foreach ($fields as $id => $field) {
            list($prefix, $value) = explode('_', $id);

			if ($prefix == 'prop') {
				continue;
			} 
			elseif ($prefix == 'stock') {
                continue;			
            } 
			elseif ($prefix == 'price') {
                continue;
            } 
			elseif ($field == 'Группы') {
                continue;
            }
			elseif ($prefix == 'ЗначенияРеквизитов') {
				continue;
            }
			elseif ($prefix == 'СтавкиНалогов') {
				continue;
            }			
			elseif ($prefix == 'ХарактеристикиТовара') {
				continue;
            }			
			elseif (is_object($values[$field])) {
                $f[$id] = null;
            }
			else {
                $f[$id] = $values[$field];
                while (is_array($f[$id])) {
                    $f[$id] = array_shift($f[$id]);
                }
            }
        }
		
        return $f;
    }

    public function getFields()
    {
        if ($this->fields === null) {
            $this->getData();

            $this->fields = array(
                0 => 'Ид',
            );
            if ($this->xml_import) {
                $goods = $this->getGoodsData();
                $v = get_object_vars($goods[0]);
							
				$this->fields = array();
				
				if (isset($v['ЗначенияРеквизитов']))
				{
					foreach ($v['ЗначенияРеквизитов'] as $r) {
						$this->fields['ЗначенияРеквизитов_' . $r->{'Наименование'}] = $r->{'Наименование'}.' (ЗначенияРеквизитов)';
					}
					unset($v['ЗначенияРеквизитов']);
				}
				
				if (isset($v['СтавкиНалогов']))
				{
					foreach ($v['СтавкиНалогов'] as $r) {
						$this->fields['СтавкиНалогов_' . $r->{'Наименование'}] = $r->{'Наименование'}.' (СтавкиНалогов)';
					}
					unset($v['ЗначенияРеквизитов']);
				}				
				
                unset($v['@attributes']);
				unset($v['ЗначенияСвойств']);
				unset($v['СтавкиНалогов']);
			
				$fields = array();
				foreach (array_keys($v) as $k) $fields[$k] = $k;
			
                $this->fields = array_merge($fields,$this->fields);
				
				$this->parseProps();
				foreach ($this->props as $id => $value)
				{
					$this->fields['prop_' . $id] = $value['name'].' ('.$value['type'].')';
				}
            }

            if ($this->xml_offers) {
				
				$this->getOffersData();				
				$offer = (array)current($this->offers_data);
				unset($offer['Ид'],$offer['Цены'],$offer['Склад']);
				foreach (array_keys($offer) as $k) if (!isset($this->fields[$k])) $this->fields[$k] = $k;				
				
				if (isset($this->xml_offers->{'ПакетПредложений'}->{'ТипыЦен'}->{'ТипЦены'}))
					foreach ($this->xml_offers->{'ПакетПредложений'}->{'ТипыЦен'}->{'ТипЦены'} as $p) {
						$this->fields['price_' . (string)$p->{'Ид'}] = 'Цена "' . (string)$p->{'Наименование'} . '"';
					}
					
				if (isset($this->xml_offers->{'ПакетПредложений'}->{'Склады'}->{'Склад'}))	
					foreach ($this->xml_offers->{'ПакетПредложений'}->{'Склады'}->{'Склад'} as $p) {
						$this->fields['stock_' . (string)$p->{'Ид'}] = (string)$p->{'Наименование'} . ' (Склад)';
					}				
            }
        }
		
        return $this->fields;
    }

    protected function getGoodsData()
    {
        $this->getData();

        return $this->xml_import->{'Каталог'}->{'Товары'}->{'Товар'};
    }

	// заполняет массив $f ценами товара $good_id
    protected function getPrice($good_id, &$f)
    {
        $this->getOffersData();
        if (!isset($this->offers_data[$good_id])) return;
		
        foreach ($this->offers_data[$good_id]->{'Цены'}->{'Цена'} as $p) {
			$f['price_'.(string)$p->{'ИдТипаЦены'}] = (float)$p->{'ЦенаЗаЕдиницу'};
        }
    }
	
	// заполняет массив $f складскими остатками товара $good_id
    protected function getStock($good_id, &$f)
    {
        $this->getOffersData();
        if (!isset($this->offers_data[$good_id])) return;
		
		foreach ($this->offers_data[$good_id]->{'Склад'} as $s) {
			$s = (array)$s;
			$f['stock_'.$s['@attributes']['ИдСклада']] = (int)$s['@attributes']['КоличествоНаСкладе'];
		}		
    }	

    public function parseGroups()
    {
        if ($this->groups === null) {
            $this->groups = array();
            $this->getData();
            if (!property_exists($this->xml_import, 'Классификатор')) return;
            $this->parseGroup($this->xml_import->{'Классификатор'});
        }
    }

    private function parseGroup($g, $parent = null)
    {
        if (!property_exists($g, 'Группы')) return;
        foreach ($g->{'Группы'}->{'Группа'} as $group) {
            $id = (string)$group->{'Ид'};
            $this->groups[$id] = array(
                'id' => $id,
                'name' => (string)$group->{'Наименование'},
                'parent' => $parent
            );
            $this->parseGroup($group, $id);
        }
    }

}