<?php
namespace Import;

class Import
{
    use \Cetera\DbConnection;

    public static function executeTemplate($id, $time_limit = 0, $counter = 0)
    {
        $data = self::getDbConnection()->fetchAssoc('SELECT * FROM import_templates WHERE `id` = ?', array($id));

        if ($data) {
			$params = unserialize($data['data']);
            $params['counter'] = $counter;
			return self::import($params, $time_limit);
        } 
		else {
            throw new \Exception('Template '.$id.' not found');
        }		
	}		

    public static function import($params, $time_limit = 5)
    {
        $time_start = time();

        if (!$params['counter']) $params['counter'] = 1;
        if (!$params['messages']) $params['messages'] = array();

        $res = array(
            'success' => true,
            'counter' => $params['counter'],
            'messages' => $params['messages']
        );

        $catalog = \Cetera\Catalog::getById($params['catalog']);
        $od = \Cetera\ObjectDefinition::findById($params['materials_type']);

        if ($params['counter'] == 1) {
            // перед импортом, удаляем, деактивируем или пропускаем материалы, в зависимости от настроек
            switch ($params['missing']) {
                case 'unpublish':
                    $conn = \Cetera\Application::getInstance()->getDbConnection();
                    $conn->executeUpdate(
                        'UPDATE ' . $od->table . ' SET type=0 WHERE idcat IN (?)',
                        array($catalog->getSubs()),
                        array(\Doctrine\DBAL\Connection::PARAM_INT_ARRAY)
                    );
                    $res['counter']++;
                    $res['messages'][] = 'Деактивация материалов ... ОК';
                    break;

                case 'delete':

                    $conn = \Cetera\Application::getInstance()->getDbConnection();
                    $conn->executeUpdate(
                        'UPDATE ' . $od->table . ' SET type=50 WHERE idcat IN (?)',
                        array($catalog->getSubs()),
                        array(\Doctrine\DBAL\Connection::PARAM_INT_ARRAY)
                    );
                    $res['messages'][] = 'Удаление материалов ... ОК';
                    $res['counter']++;
                    break;

                default:
                    $res['messages'][] = 'Деактивация материалов не требуется';
                    $res['counter']++;
            }
			
			$params['counter'] = $res['counter'];
        }

        if ($params['counter'] == 2) {
            $dataSource = new $params['data_source']($params);

            $res['_current_line'] = (int)$params['_current_line'];
            $res['_iteration'] = (int)$params['_iteration'];

            if (!$res['_iteration']) {
                $res['messages'][] = 'Обработка исходных данных ';
            }
			
			$filters = array();
			foreach ($params as $id => $value)
			{
				$parts = explode('_',$id);
				if ($parts[0] == 'filter' && $value)
				{
					array_shift($parts);
					$field = implode('_',$parts);
					$filters[$field] = $value;
				}
			}
			
			$noFieldsAccordance = $dataSource->getFields() === false;

            while (!$dataSource->eof) {
                $res['_iteration']++;
                
                ob_start();
                $f = $dataSource->fetch();
                $txt = ob_get_contents();
                ob_end_clean();  
                if ($txt) {
                    $res['messages'][] = $txt;
                }
											
                if ($f) {
					// проверка фильтра
					
					foreach($filters as $id => $filter)
					{
						if (is_array($f[$id])) {
							$value = self::flattenArray($f[$id]);
						} else {
							$value = (string)$f[$id];
						}
						
						try {
							$match = preg_match($filter, $value);
						} catch (\Exception $e) {
							$match = substr_count($value, $filter);
						}
						
						// Если не совпало с фильтром - пропускаем материал.
						//print "Поле: $id Filter: $filter Value: ".$value." Match: ".$match."\n";
						if (!$match) {
							$f = null;						
							break;
						}
					}
					//die();
					
				}
				
				if ($f) {
					
					// импорт материала
					
                    $res['_current_line']++;

                    if ($params['materials_type'] == \Cetera\User::TYPE) {
                        $default_data = array(
                            'name' => 'Импортированный пользователь ' . $res['_current_line'],
                            'login' => 'user_'.time() . '_' . rand(10000, 99999),
                        );
                        $data = array(
                            'disabled' => 0,
                        );                          
                    }
                    else {
                        $default_data = array(
                            'name' => 'Импортированный материал ' . $res['_current_line'],
                            'idcat' => $catalog->id,
                            'autor' => \Cetera\Application::getInstance()->getUser()->id,
                            'alias' => time() . '_' . rand(10000, 99999),
                        );
                        $data = array(
                            'publish' => 1,
                        );                        
                    }

                    $path = array();

                    foreach ($f as $id => $value) {

						if ($noFieldsAccordance) {
							$key = $id;
						}
						else {
							if (!$params['fields_' . $id]) continue;
							$key = $params['fields_' . $id];
						}

                        $dummy = explode('_', $key);
                        $prefix = array_shift($dummy);
                        $field = implode('_', $dummy);

                        switch ($prefix) {
                            case 'alias':
                                $data['alias'] = translit($value);
                                break;

                            case 'publish':
                                $data['publish'] = (boolean)$value;
                                break;

                            case 'field':

                                if ($field == 'idcat') {
                                    if (is_array($value)) {
                                        $path = $value;
                                    } elseif (intval($value)) {
                                        $data[$field] = intval($value);
                                    }
                                    break;
                                }

                                $field_def = $od->getField($field);
								
                                switch ($field_def['type'])
								{
                                    case FIELD_INTEGER:
                                        $value = preg_replace("/[^0-9\-]/", "", $value);
                                        $data[$field] = intval($value);
                                        break;
										
									case FIELD_BOOLEAN:
										if (in_array($value,['1','Y'])) {
											$data[$field] = 1;
										}
										elseif (in_array($value,['0','N'])) {
											$data[$field] = 0;
										}
										else {
											$data[$field] = (int)(bool)$value;
										}
										break;
										
                                    case FIELD_DOUBLE:
                                        $value = preg_replace("/[^0-9\.,\-]/", "", $value);
                                        $value = str_replace(',', '.', $value);
                                        $data[$field] = floatval($value);
                                        break;
										
									case FIELD_LINK:
										$data[$field] = self::getLinkValue($field_def, $value);
										break;
										
									case FIELD_LINKSET:
										$data[$field] = self::getLinkSetValue($field_def, $value);
										break;										
										
									case FIELD_MATSET:
										$data[$field] = $value;
										break;										
										
									case FIELD_FILE:
										if (is_array($value)) {
											if (!isset($value['filename'])) break;
											$data[$field] = USER_UPLOAD_PATH.'import/'.$value['filename'];
											if (!file_exists(WWWROOT.$data[$field]) && $value['data']) {
												if (!file_exists(WWWROOT.USER_UPLOAD_PATH.'import')) mkdir(WWWROOT.USER_UPLOAD_PATH.'import', 0777, true);
												file_put_contents(WWWROOT.$data[$field], $value['data']);
											}
										}
										else {
											$data[$field] = $value;
										}
										break;
									
                                    default:
										if (isset($data[$field])) {
											$data[$field] .= '<br>'.$value;
										} 
										else {
											$data[$field] = $value;
										}
										break;
                                }

                                break;

                            case 'catalog':
                                if (!$value) break;
                                $pid = (int)$field - 1;

                                if (isset($path[$pid])) {
                                    $path[$pid]['name'] = $value;
                                } else {
                                    $path[$pid] = array(
                                        'name' => $value
                                    );
                                }
                                break;

                            case 'catalogalias':
                                if (!$value) break;
                                $pid = (int)$field - 1;

                                if (isset($path[$pid])) {
                                    $path[$pid]['alias'] = $value;
                                } else {
                                    $path[$pid] = array(
                                        'name' => $value,
                                        'alias' => $value
                                    );
                                }
                                break;
                        }
                    }		
					
                    if (count($path)) {
                        $c = $catalog;
                        $i = 0;
                        while ($path[$i]) {

                            if (isset($path[$i]['alias'])) {
                                $children = $c->getChildren()->where('tablename=:alias')->setParameter('alias', $path[$i]['alias']);
                            } else {
                                $children = $c->getChildren()->where('name=:name')->setParameter('name', $path[$i]['name']);
                            }

                            if (count($children)) {
                                $c = $children->current();
                            } else {
                                if (!isset($path[$i]['alias'])) {
                                    //$path[$i]['alias'] = substr(md5($path[$i]['name']),0,5);
                                    $path[$i]['alias'] = translit($path[$i]['name']);
                                }
                                $cid = $c->createChild(array(
                                    'name' => $path[$i]['name'],
                                    'alias' => $path[$i]['alias'],
                                    'typ' => $od->id
                                ));
                                $c = \Cetera\Catalog::getById($cid);
                            }
                            $i++;
                        }
                        $data['idcat'] = $c->id;
                    }
				
					
                    if (!$data['alias']) unset($data['alias']);

                    $m = null;

                    $search_field = $params['unique_field'];
                    if ($data[$search_field]) {
                        
                        if ($params['materials_type'] == \Cetera\User::TYPE) {
                            $list = \Cetera\User::enum()->where($search_field . '=:' . $search_field)->setParameter($search_field, $data[$search_field]);
                        }
                        else {
                            $list = $catalog->getMaterials()->subFolders()->unpublished()->where($search_field . '=:' . $search_field)->setParameter($search_field, $data[$search_field]);
                        }
                        
                        if (count($list)) {
                            $m = $list->current();
                            $data = array_merge($m->fields, $data);
							unset($data['type']);													
                            $m->setFields($data);
                        }
                    }
					
                    if (!$m) {
                        
                        // если установлена опция 'Не создавать новые материалы', то пропускаем
                        if ($params['no_new_materials']) {
                            //$res['messages'][] = 'Материал '.$search_field.'='.$data[$search_field].' не найден';
                            continue;
                        }
                        
						if (isset($data['name']) && !isset($data['alias'])) {
							$data['alias'] = strtolower(translit($data['name']));
						}
                        $data = array_merge($default_data, $data);
                        $m = \Cetera\DynamicFieldsObject::fetch($data, $od);
                    }

                    $m->save(true, true);
					
                    \Cetera\Event::trigger('IMPORT_MATERIAL_AFTER_IMPORT', [
                        'material' => $m,
                        'fields'   => $f,
                        'params'   => $params,
                    ]); 
					
                }

                if (self::check_time($time_limit, $time_start)) {
                    if (strpos($res['messages'][count($res['messages']) - 1], " ОК") === false)
                        $res['messages'][count($res['messages']) - 1] .= '.';
                    $res['datasource_data'] = $dataSource->sleep();
                    break;
                }
            }

            if ($dataSource->eof) {
                // if (strpos($res['messages'][count($res['messages']) - 1], " ОК") === false)
                //    $res['messages'][count($res['messages']) - 1] .= ' OK';
                $res['messages'][] = 'Обработка исходных данных окончена';
                
                $res['counter']++;
                $res['_iteration'] = 0;
				$params['counter'] = $res['counter'];
            }
        }

        if ($params['counter'] == 3) {
            switch ($params['missing']) {
                case 'delete':

                    $res['_iteration'] = (int)$params['_iteration'];
                    if (!$res['_iteration']) {
                        $res['messages'][] = 'Удаление материалов ...';
                    }
                    $res['_iteration']++;

                    $list = $catalog->getMaterials()->unpublished()->subFolders()->where('type=50');
                    if (!$list->getCountAll()) {
                        foreach ($catalog->children as $c) {
                            self::delete_if_empty($c);
                        }
                        $res['counter'] = 0;
                        if (strpos($res['messages'][count($res['messages']) - 1], " ОК") === false)
                            $res['messages'][count($res['messages']) - 1] .= ' ОК';
                    } else {
                        foreach ($list as $m) {
                            $m->delete();
                            if (self::check_time($time_limit, $time_start)) break;
                        }
                        $res['messages'][count($res['messages']) - 1] .= '.';
                    }
                    break;

                default:
                    $res['counter'] = 0;
            }
        }

        if (!$res['counter']) {
            $res['messages'][] = 'Импорт завершен.';
        }

        return $res;
    }
	
    public static function getLinkValue($field_def, $value)
    {
        $i = $field_def->getCatalog()->getMaterials()->where('name="'.$value.'"')->subfolders();
		if ($i->getCountAll()>0) return $i->current()->id;
		
		$data = array(
			'name' => $value,
			'idcat' => $field_def->getCatalog()->id,
			'autor' => \Cetera\Application::getInstance()->getUser()->id,
			'alias' => translit($value),
			'publish' => 1,
		);	

		$m = \Cetera\DynamicFieldsObject::fetch($data, $field_def->getCatalog()->getMaterialsObjectDefinition());		
		$m->save(true,true);
		
		return $m->id;
    }
	
    public static function getLinkSetValue($field_def, $value)
    {
		if (!is_array($value)) {
			$value = array($value);
		}
		$data = array();
		
		foreach ($value as $v) {
		
			$i = $field_def->getCatalog()->getMaterials()->where('name="'.$v.'"')->subfolders();
			if ($i->getCountAll()>0) {
				$data[] = $i->current()->id;
				continue;
			}
			
			$data = array(
				'name' => $v,
				'idcat' => $field_def->getCatalog()->id,
				'autor' => \Cetera\Application::getInstance()->getUser()->id,
				'alias' => translit($v),
				'publish' => 1,
			);	

			$m = \Cetera\DynamicFieldsObject::fetch($data, $field_def->getCatalog()->getMaterialsObjectDefinition());		
			$m->save(true,true);
			$data[] = $m->id;
		
		}
		
		return json_encode($data);
    }	

    public static function check_time($limit, $start)
    {
        if (!$limit) return false;
        if (time() - $start > $limit) return true;

        return false;
    }

    public static function delete_if_empty($catalog)
    {
        foreach ($catalog->children as $c) {
            self::delete_if_empty($c);
        }
        if ($catalog->getChildren()->getCountAll() > 0) {
            return;
        }
        if (!$catalog->getMaterials()->unpublished()->getCountAll() > 0) {
            $catalog->delete();
        }
    }
	
    public static function getTemplate($name)
    {
        $data = self::getDbConnection()->fetchAssoc('SELECT * FROM import_templates WHERE `name` = ?', array($name));

        if ($data) {
            return unserialize($data['data']);
        } else {
            return false;
        }
    }
	
	protected static function flattenArray($array)
	{
		if (!is_array($array)) return (string)$array;
			
		$values = array();
		foreach ($array as $key => $item) {
			if ((string)$key == 'id') continue;
			$values[] = self::flattenArray($item);
		}
		return implode('//',$values);
	}	
}