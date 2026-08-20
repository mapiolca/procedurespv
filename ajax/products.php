<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

if (!defined('NOREQUIREMENU')) {
	define('NOREQUIREMENU', 1);
}
require '../../../main.inc.php';
require_once dol_buildpath('/procedurespv/class/raccordementequipment.class.php', 0);
require_once dol_buildpath('/procedurespv/lib/procedurespv.lib.php', 0);

header('Content-Type: application/json; charset=UTF-8');

$type = GETPOST('type', 'aZ09');
$term = GETPOST('q', 'restricthtml');
if (!isModEnabled('procedurespv') || !procedurespvCanDo($user, 'raccordement', 'read') || (!$user->hasRight('produit', 'lire') && empty($user->admin))) {
	http_response_code(403);
	echo json_encode(array('results' => array(), 'error' => 'Forbidden'));
	exit;
}
if (!in_array($type, array(RaccordementEquipment::TYPE_INVERTER, RaccordementEquipment::TYPE_MODULE), true) || !RaccordementEquipment::isAvailable()) {
	http_response_code(400);
	echo json_encode(array('results' => array(), 'error' => 'EquipmentManagementUnavailable'));
	exit;
}

$service = new RaccordementEquipment($db);
echo json_encode(array('results' => $service->searchProducts($type, $term)), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
