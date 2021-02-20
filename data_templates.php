<?php
include('common_bo.php');

$conn = \Cetera\Application::getInstance()->getDbConnection();

if (isset($_REQUEST['action']) && $_REQUEST['action'] == 'destroy') {
	$d = json_decode(file_get_contents("php://input"), true);
	
	$conn->delete('import_templates', array(
		'id' => $d['id']
	));
	echo json_encode(array(
		'success' => true,
	));	
	die();
}

$query = $conn->createQueryBuilder();
$query->select('*')->from('import_templates');

$stmt = $query->execute(); 
while ($row = $stmt->fetch())
{    
	$params = unserialize($row['data']);
    if (array_key_exists('material_type',$params)) {
        $params['material_type'] = (int)$params['material_type'];
    }
	$fields = array();
	$setup = array();
	foreach($params as $id => $value) {
		if (substr($id,0,7) == 'fields_' || substr($id,0,7) == 'filter_')
			$fields[$id] = $value;
			else $setup[$id] = $value;
	}
	$data[] = array(
		'id'    => $row['id'],
		'name'  => $row['name'],
		'setup' => $setup,
		'fields'=> $fields
	);
}
	
echo json_encode(array(
    'success' => true,
    'rows'    => $data
));