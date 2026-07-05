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

$sensitiveActions = array('save', 'set_required', 'set_not_required', 'mark_sent_client', 'mark_received', 'validate_cardi', 'refuse_cardi', 'send_public_cardi');
if (in_array($action, $sensitiveActions, true) && !GETPOST('token', 'alpha')) {
	accessforbidden($langs->trans('ErrorBadToken'));
}

if (in_array($action, $sensitiveActions, true)) {
	if (!$permissiontowrite) {
		accessforbidden();
	}

	$object->cardi_required = GETPOSTINT('cardi_required');
	$object->cardi_status = GETPOSTINT('cardi_status');
	$object->cardi_date_demande = procedurespvCardiReadDateTimeFromPost('cardi_date_demande');
	$object->cardi_date_envoi_client = procedurespvCardiReadDateTimeFromPost('cardi_date_envoi_client');
	$object->cardi_date_retour_client = procedurespvCardiReadDateTimeFromPost('cardi_date_retour_client');
	$object->cardi_date_validation = procedurespvCardiReadDateTimeFromPost('cardi_date_validation');
	$object->cardi_document = GETPOST('cardi_document', 'alphanohtml');
	$object->cardi_commentaire = GETPOST('cardi_commentaire', 'restricthtml');

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

	$object->context['trigger_reason'] = 'cardi_update';
	$object->context['changed_fields'] = array('cardi_required', 'cardi_status');
	$result = $object->update($user);
	if ($result > 0) {
		setEventMessages($langs->trans('RecordSaved'), null, 'mesgs');
		header('Location: '.dol_buildpath('/procedurespv/raccordement/cardi.php', 1).'?id='.(int) $object->id);
		exit;
	}

	setEventMessages($object->error, $object->errors, 'errors');
}

if ($action === 'send_public_cardi') {
	setEventMessages($langs->trans('CardiPublicFormNotActive'), null, 'warnings');
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
print '<tr><td class="titlefield">'.$langs->trans('CardiRequired').'</td><td><select class="flat minwidth200" name="cardi_required" id="cardi_required">';
foreach (array(0 => 'No', 1 => 'Yes', 2 => 'ToDetermine') as $value => $labelKey) {
	print '<option value="'.((int) $value).'"'.((int) $object->cardi_required === (int) $value ? ' selected' : '').'>'.$langs->trans($labelKey).'</option>';
}
print '</select>'.ajax_combobox('cardi_required').'</td></tr>';
print '<tr><td>'.$langs->trans('CardiStatus').'</td><td><select class="flat minwidth250" name="cardi_status" id="cardi_status">';
$statuses = array(0 => 'CardiStatusNotRequired', 1 => 'CardiStatusToPrepare', 2 => 'CardiStatusToSendClient', 3 => 'CardiStatusWaitingClient', 4 => 'CardiStatusReceived', 5 => 'CardiStatusToControl', 6 => 'CardiStatusValidated', 7 => 'CardiStatusNonCompliant');
foreach ($statuses as $value => $labelKey) {
	print '<option value="'.((int) $value).'"'.((int) $object->cardi_status === (int) $value ? ' selected' : '').'>'.$langs->trans($labelKey).'</option>';
}
print '</select>'.ajax_combobox('cardi_status').'</td></tr>';
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
	print '<a class="butAction" href="'.$baseUrl.'&action=set_required&token='.$token.'">'.$langs->trans('SetCardiRequired').'</a>';
	print '<a class="butAction" href="'.$baseUrl.'&action=set_not_required&token='.$token.'">'.$langs->trans('SetCardiNotRequired').'</a>';
	print '<a class="butAction" href="'.$baseUrl.'&action=mark_sent_client&token='.$token.'">'.$langs->trans('MarkSentClient').'</a>';
	print '<a class="butAction" href="'.$baseUrl.'&action=mark_received&token='.$token.'">'.$langs->trans('MarkReceived').'</a>';
	print '<a class="butAction" href="'.$baseUrl.'&action=validate_cardi&token='.$token.'">'.$langs->trans('ValidateCARDi').'</a>';
	print '<a class="butAction" href="'.$baseUrl.'&action=refuse_cardi&token='.$token.'">'.$langs->trans('RefuseCARDi').'</a>';
	print '<a class="butActionRefused classfortooltip" title="'.$langs->trans('CardiPublicFormNotActive').'" href="'.$baseUrl.'&action=send_public_cardi&token='.$token.'">'.$langs->trans('SendPublicCardiForm').'</a>';
}
print '</div>';

print dol_get_fiche_end();

llxFooter();
$db->close();
