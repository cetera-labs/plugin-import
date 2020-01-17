<?php
include('common_bo.php');

$conn = \Cetera\Application::getInstance()->getDbConnection();

try
{
	$params = array(
		'name'        => $_GET['name'],
		'data'        => serialize($_POST),
	);
	$conn->insert('import_templates', $params);				
}
catch (\Exception $e)
{
	
	$conn->update('import_templates', 
		$params,
		array(
			'name'        => $_GET['name']
		)			
	);					
}