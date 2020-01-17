<?php
include('common_bo.php');
$t = $application->getTranslator();

$data = array(
	array('id' => '', 'name' => '['.$t->_('пропустить').']'),
	array('id' => 'publish', 'name' => $t->_('Активность') ),
	array('id' => 'alias', 'name' => 'Alias'),
	array('id' => 'catalog_1', 'name' => $t->_('Название подраздела - Уровень 1') ),
	array('id' => 'catalog_2', 'name' => $t->_('Название подраздела - Уровень 2') ),
	array('id' => 'catalog_3', 'name' => $t->_('Название подраздела - Уровень 3') ),
	array('id' => 'catalogalias_1', 'name' => $t->_('Alias подраздела - Уровень 1') ),
	array('id' => 'catalogalias_2', 'name' => $t->_('Alias подраздела - Уровень 2') ),
	array('id' => 'catalogalias_3', 'name' => $t->_('Alias подраздела - Уровень 3') ),	
);

$r = $application->getConn()->query('SELECT field_id as id, name, describ, type, pseudo_type from types_fields where id='.(int)$_REQUEST['type_id'].' order by tag');   
while ($f = $r->fetch()) {	
	
        //if ($f['type']==FIELD_MATSET && $f['pseudo_type']!=PSEUDO_FIELD_TAGS && $f['pseudo_type']!=PSEUDO_FIELD_GALLERY) continue;
		
        if (in_array($f['name'], array('alias','tag','dat_update','type'))) continue;
			
        $data[] = array(
			'id'   => 'field_'.$f['name'],
			'name' => 'Поле "'.$application->decodeLocaleString($f['describ']).'" ('.$f['name'].')'
		);
}	
	
echo json_encode(array(
    'success' => true,
    'rows'    => $data
));