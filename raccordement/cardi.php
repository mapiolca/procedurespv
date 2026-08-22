<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

require '../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once dol_buildpath('/procedurespv/class/raccordement.class.php', 0);
require_once dol_buildpath('/procedurespv/class/raccordementworkflow.class.php', 0);
require_once dol_buildpath('/procedurespv/lib/procedurespv.lib.php', 0);

/**
 * Read Dolibarr date selector value.
 *
 * @param string $prefix Field prefix
 * @return int|null
 */
function procedurespvCardiReadDateTimeFromPost($prefix)
{
	$day = GETPOSTINT($prefix.'day');
	$month = GETPOSTINT($prefix.'month');
	$year = GETPOSTINT($prefix.'year');
	$hour = GETPOSTINT($prefix.'hour');
	$min = GETPOSTINT($prefix.'min');

	if ($day <= 0 || $month <= 0 || $year <= 0) {
		return null;
	}

	return dol_mktime($hour, $min, 0, $month, $day, $year);
}

$langs->loadLangs(array('procedurespv@procedurespv'));

$id = GETPOSTINT('id');
$action = GETPOST('action', 'aZ09');

if (!isModEnabled('procedurespv')) {
	accessforbidden();
}

$object = new Raccordement($db);
$result = $object->fetch($id);
if ($result <= 0) {
	accessforbidden($langs->trans('ErrorRecordNotFound'));
}

$permissiontoread = procedurespvCanDo($user, 'raccordement', 'read');
$permissiontowrite = procedurespvCanDo($user, 'raccordement', 'manage_cardi');
if (!$permissiontoread) {
	accessforbidden();
}
if (!$object->isCardiApplicable()) {
	accessforbidden($langs->trans('CardiNotApplicableForPower'));
}
$workflow = new RaccordementWorkflow($db);

$cardiTransitions = array(
	'set_required' => !((int) $object->cardi_required === 1) && (int) $object->cardi_status !== 6,
	'set_not_required' => !((int) $object->cardi_required === 0) && (int) $object->cardi_status !== 6,
	'mark_sent_client' => (int) $object->cardi_required === 1 && in_array((int) $object->cardi_status, array(1, 2), true),
	'mark_received' => (int) $object->cardi_required === 1 && (int) $object->cardi_status === 3,
	'validate_cardi' => (int) $object->cardi_required === 1 && in_array((int) $object->cardi_status, array(4, 5), true),
	'refuse_cardi' => (int) $object->cardi_required === 1 && in_array((int) $object->cardi_status, array(4, 5, 6), true),
);
$sensitiveActions = array_merge(array('save', 'send_public_cardi'), array_keys($cardiTransitions));
if (in_array($action, $sensitiveActions, true) && (!GETPOST('token', 'alpha') || (function_exists('checkToken') && !checkToken()))) {
	accessforbidden($langs->trans('ErrorBadToken'));
}

if ($action === 'send_public_cardi') {
	if (!$permissiontowrite) {
		accessforbidden();
	}
	setEventMessages($langs->trans('CardiPublicFormNotActive'), null, 'warnings');
	$action = '';
}

if ($action === 'save' || isset($cardiTransitions[$action])) {
	if (!$permissiontowrite) {
		accessforbidden();
	}

	if ($action === 'save') {
		$object->cardi_date_demande = procedurespvCardiReadDateTimeFromPost('cardi_date_demande');
		$object->cardi_date_envoi_client = procedurespvCardiReadDateTimeFromPost('cardi_date_envoi_client');
		$object->cardi_date_retour_client = procedurespvCardiReadDateTimeFromPost('cardi_date_retour_client');
		$object->cardi_date_validation = procedurespvCardiReadDateTimeFromPost('cardi_date_validation');
		$object->cardi_document = GETPOST('cardi_document', 'alphanohtml');
		$object->cardi_commentaire = GETPOST('cardi_commentaire', 'restricthtml');
	} elseif (empty($cardiTransitions[$action])) {
		accessforbidden($langs->trans('InvalidStatusTransition'));
	}

	if ($action === 'set_required') {
		$object->cardi_required = 1;
		$object->cardi_status = 1;
		$object->cardi_date_demande = dol_now();
	}
	if ($action === 'set_not_required') {
		$object->cardi_required = 0;
		$object->cardi_status = 0;
	}
	if ($action === 'mark_sent_client') {
		$object->cardi_status = 3;
		$object->cardi_date_envoi_client = dol_now();
	}
	if ($action === 'mark_received') {
		$object->cardi_status = 5;
		$object->cardi_date_retour_client = dol_now();
	}
	if ($action === 'validate_cardi') {
		$object->cardi_status = 6;
		$object->cardi_date_validation = dol_now();
	}
	if ($action === 'refuse_cardi') {
		$object->cardi_status = 7;
	}
	$object->status = $workflow->getReconciledStatus($object);

	$object->context['trigger_reason'] = 'cardi_update';
	$object->context['changed_fields'] = array('cardi_required', 'cardi_status', 'status');
	$result = $object->update($user);
	if ($result > 0) {
		setEventMessages($langs->trans('RecordSaved'), null, 'mesgs');
		header('Location: '.dol_buildpath('/procedurespv/raccordement/cardi.php', 1).'?id='.(int) $object->id);
		exit;
	}

	setEventMessages($object->error, $object->errors, 'errors');
}

$form = new Form($db);

llxHeader('', $langs->trans('CARDi'), '', '', 0, 0, '', '', '', 'mod-procedurespv page-raccordement-cardi');

$head = procedurespvRaccordementPrepareHead($object);
print dol_get_fiche_head($head, 'cardi', $langs->trans('Raccordement'), -1, $object->picto);

$linkback = '<a href="'.dol_buildpath('/procedurespv/raccordement/list.php', 1).'">'.$langs->trans('BackToList').'</a>';
dol_banner_tab($object, 'ref', $linkback, 1, 'ref', 'ref');

print '<form method="POST" action="'.dol_escape_htmltag($_SERVER['PHP_SELF']).'?id='.(int) $object->id.'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="save">';

print '<table class="border centpercent tableforfield">';
print '<tr><td class="titlefield">'.$langs->trans('CardiRequired').'</td><td>'.$langs->trans(array(0 => 'No', 1 => 'Yes', 2 => 'ToDetermine')[(int) $object->cardi_required] ?? 'ToDetermine').'</td></tr>';
$statuses = array(0 => 'CardiStatusNotRequired', 1 => 'CardiStatusToPrepare', 2 => 'CardiStatusToSendClient', 3 => 'CardiStatusWaitingClient', 4 => 'CardiStatusReceived', 5 => 'CardiStatusToControl', 6 => 'CardiStatusValidated', 7 => 'CardiStatusNonCompliant');
$cardiStatusTypes = array(0 => 'status0', 1 => 'status3', 2 => 'status3', 3 => 'status0', 4 => 'status1', 5 => 'status3', 6 => 'status4', 7 => 'status8');
print '<tr><td>'.$langs->trans('CardiStatus').'</td><td>'.dolGetStatus($langs->trans($statuses[(int) $object->cardi_status] ?? 'CardiStatusToDetermine'), '', '', $cardiStatusTypes[(int) $object->cardi_status] ?? 'status0', 6).'</td></tr>';
print '<tr><td>'.$langs->trans('CardiRequestDate').'</td><td>';
$form->selectDate($object->cardi_date_demande ? (int) $object->cardi_date_demande : -1, 'cardi_date_demande', 1, 1, 1, '', 1, 1);
print '</td></tr>';
print '<tr><td>'.$langs->trans('CardiClientSentDate').'</td><td>';
$form->selectDate($object->cardi_date_envoi_client ? (int) $object->cardi_date_envoi_client : -1, 'cardi_date_envoi_client', 1, 1, 1, '', 1, 1);
print '</td></tr>';
print '<tr><td>'.$langs->trans('CardiClientReturnDate').'</td><td>';
$form->selectDate($object->cardi_date_retour_client ? (int) $object->cardi_date_retour_client : -1, 'cardi_date_retour_client', 1, 1, 1, '', 1, 1);
print '</td></tr>';
print '<tr><td>'.$langs->trans('CardiValidationDate').'</td><td>';
$form->selectDate($object->cardi_date_validation ? (int) $object->cardi_date_validation : -1, 'cardi_date_validation', 1, 1, 1, '', 1, 1);
print '</td></tr>';
print '<tr><td>'.$langs->trans('CardiDocument').'</td><td><input type="text" class="flat minwidth300" name="cardi_document" value="'.dol_escape_htmltag((string) $object->cardi_document).'"></td></tr>';
print '<tr><td>'.$langs->trans('CardiComment').'</td><td><textarea class="flat centpercent" name="cardi_commentaire" rows="3">'.dol_escape_htmltag((string) $object->cardi_commentaire).'</textarea></td></tr>';
print '</table>';

if ($permissiontowrite) {
	print '<div class="center"><input type="submit" class="button button-save" value="'.$langs->trans('Save').'"></div>';
}
print '</form>';

print '<div class="tabsAction">';
if ($permissiontowrite) {
	$baseUrl = dol_buildpath('/procedurespv/raccordement/cardi.php', 1).'?id='.(int) $object->id;
	$token = newToken();
	$cardiActionLabels = array(
		'set_required' => 'SetCardiRequired',
		'set_not_required' => 'SetCardiNotRequired',
		'mark_sent_client' => 'MarkSentClient',
		'mark_received' => 'MarkReceived',
		'validate_cardi' => 'ValidateCARDi',
		'refuse_cardi' => 'RefuseCARDi',
	);
	foreach ($cardiActionLabels as $cardiAction => $labelKey) {
		if (!empty($cardiTransitions[$cardiAction])) {
			$buttonClass = $cardiAction === 'refuse_cardi' ? 'butActionDelete' : 'butAction';
			print '<a class="'.$buttonClass.'" href="'.$baseUrl.'&action='.$cardiAction.'&token='.$token.'">'.$langs->trans($labelKey).'</a>';
		}
	}
	print '<span class="butActionRefused classfortooltip" title="'.$langs->trans('CardiPublicFormNotActive').'">'.$langs->trans('SendPublicCardiForm').'</span>';
}
print '</div>';

print dol_get_fiche_end();

llxFooter();
$db->close();
