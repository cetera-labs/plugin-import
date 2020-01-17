<?php
include('common_bo.php');

$od = \Cetera\ObjectDefinition::findById($_REQUEST['materials_type']);
$od->getField($_REQUEST['unique_field']);

$dataSource = \Import\DataSourceIterator::factory( $_REQUEST['data_source'], $_POST );

$res = array(
    'success' => true,
	'fields'  => $dataSource->getFields(),
);

echo json_encode($res);