<?php
include('common_bo.php');

$res = array(
    'success' => true,
	'fields'  => $_REQUEST['data_source']::setup(),
);

echo json_encode($res);