<?php
include('common_bo.php');

if ($_POST['message']) $_POST['messages'] = explode('<br>',$_POST['message']);

$res = \Import\Import::import($_POST, 15);

$res['message'] = implode('<br>',$res['messages']);
unset($res['messages']);

echo json_encode($res);