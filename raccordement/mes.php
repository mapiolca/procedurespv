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
function procedurespvMesReadDateTimeFromPost($prefix)
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

/**
 * Create a native agenda event when Agenda is available.
 *
 * @param DoliDB $db Database handler
 * @param User $user User creating the event
 * @param Raccordement $object Raccordement object
 * @return void
 */
function procedurespvMesCreateAgendaEvent($db, $user, $object)
{
	global $langs;

	if (!function_exists('isModEnabled') || !isModEnabled('agenda')) {
		return;
	}

	$actionCommFile = DOL_DOCUMENT_ROOT.'/comm/action/class/actioncomm.class.php';
	if (!file_exists($actionCommFile)) {
		return;
	}

	require_once $actionCommFile;
	if (!class_exists('ActionComm')) {
		return;
	}

	$actioncomm = new ActionComm($db);
	$actioncomm->type_code = 'AC_OTH_AUTO';
	$actioncomm->label = $langs->trans('MESAgendaEventLabel').' '.$object->ref;
	$actioncomm->datep = dol_now();
	$actioncomm->datef = dol_now();
	$actioncomm->percentage = 100;
	$actioncomm->elementtype = $object->element.'@procedurespv';
	$actioncomm->fk_element = (int) $object->id;
	$actioncomm->note_private = $langs->trans('MESAgendaEventNote');
	$result = $actioncomm->create($user);
	if ($result < 0) {
		dol_syslog('procedurespvMesCreateAgendaEvent failed: '.$actioncomm->error, LOG_WARNING);
	}
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
$permissiontowrite = procedurespvCanDo($user, 'raccordement', 'manage_mes');
if (!$permissiontoread) {
	accessforbidden();
}

$sensitiveActions = array('save', 'set_required', 'set_not_required', 'mark_to_request', 'mark_requested', 'mark_planned', 'mark_done', 'mark_blocked', 'mark_canceled');
if (in_array($action, $sensitiveActions, true) && !GETPOST('token', 'alpha')) {
	accessforbidden($langs->trans('ErrorBadToken'));
}

if (in_array($action, $sensitiveActions, true)) {
	if (!$permissiontowrite) {
		accessforbidden();
	}

	$object->mes_required = GETPOSTINT('mes_required');
	$object->mes_status = GETPOSTINT('mes_status');
	$object->date_demande_mes = procedurespvMesReadDateTimeFromPost('date_demande_mes');
	$object->date_previsionnelle_mes = procedurespvMesReadDateTimeFromPost('date_previsionnelle_mes');
	$object->date_reelle_mes = procedurespvMesReadDateTimeFromPost('date_reelle_mes');
	$object->consuel_recu = GETPOSTINT('consuel_recu');
	$object->date_consuel = procedurespvMesReadDateTimeFromPost('date_consuel');
	$object->ref_consuel = GETPOST('ref_consuel', 'alphanohtml');
	$object->injection_autorisee = GETPOSTINT('injection_autorisee');
	$object->date_autorisation_injection = procedurespvMesReadDateTimeFromPost('date_autorisation_injection');
	$object->ref_intervention_enedis = GETPOST('ref_intervention_enedis', 'alphanohtml');
	$object->commentaire_mes = GETPOST('commentaire_mes', 'restricthtml');

	if ($action === 'set_required') {
		$object->mes_required = 1;
		$object->mes_status = 1;
		$object->status = 13;
	}
	if ($action === 'set_not_required') {
		$object->mes_required = 0;
		$object->mes_status = 0;
	}
	if ($action === 'mark_to_request') {
		$object->mes_required = 1;
		$object->mes_status = 1;
		$object->status = 13;
	}
	if ($action === 'mark_requested') {
		$object->mes_required = 1;
		$object->mes_status = 2;
		$object->status = 14;
		if (empty($object->date_demande_mes)) {
			$object->date_demande_mes = dol_now();
		}
	}
	if ($action === 'mark_planned') {
		$object->mes_required = 1;
		$object->mes_status = 3;
	}
	if ($action === 'mark_done') {
		$object->mes_required = 1;
		$object->mes_status = 4;
		$object->status = 15;
		if (empty($object->date_reelle_mes)) {
			$object->date_reelle_mes = dol_now();
		}
		$object->date_mes = $object->date_reelle_mes;
	}
	if ($action === 'mark_blocked') {
		$object->mes_status = 5;
	}
	if ($action === 'mark_canceled') {
		$object->mes_status = 6;
	}

	$object->context['trigger_reason'] = 'mes_update';
	$object->context['changed_fields'] = array('mes_required', 'mes_status', 'date_mes');
	$result = $object->update($user);
	if ($result > 0) {
		if ($action === 'mark_done') {
			procedurespvMesCreateAgendaEvent($db, $user, $object);
		}
		setEventMessages($langs->trans('RecordSaved'), null, 'mesgs');
		header('Location: '.dol_buildpath('/procedurespv/raccordement/mes.php', 1).'?id='.(int) $object->id);
		exit;
	}

	setEventMessages($object->error, $object->errors, 'errors');
}

$form = new Form($db);

llxHeader('', $langs->trans('MiseEnService'), '', '', 0, 0, '', '', '', 'mod-procedurespv page-raccordement-mes');

$head = procedurespvRaccordementPrepareHead($object);
print dol_get_fiche_head($head, 'mes', $langs->trans('Raccordement'), -1, $object->picto);

$linkback = '<a href="'.dol_buildpath('/procedurespv/raccordement/list.php', 1).'">'.$langs->trans('BackToList').'</a>';
dol_banner_tab($object, 'ref', $linkback, 1, 'ref', 'ref');

print '<form method="POST" action="'.dol_escape_htmltag($_SERVER['PHP_SELF']).'?id='.(int) $object->id.'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="save">';

print '<table class="border centpercent tableforfield">';
print '<tr><td class="titlefield">'.$langs->trans('MESRequired').'</td><td><select class="flat minwidth200" name="mes_required" id="mes_required">';
foreach (array(0 => 'No', 1 => 'Yes') as $value => $labelKey) {
	print '<option value="'.((int) $value).'"'.((int) $object->mes_required === (int) $value ? ' selected' : '').'>'.$langs->trans($labelKey).'</option>';
}
print '</select>'.ajax_combobox('mes_required').'</td></tr>';
print '<tr><td>'.$langs->trans('MESStatus').'</td><td><select class="flat minwidth250" name="mes_status" id="mes_status">';
$statuses = array(0 => 'MESStatusNotRequested', 1 => 'MESStatusToRequest', 2 => 'MESStatusRequested', 3 => 'MESStatusPlanned', 4 => 'MESStatusDone', 5 => 'MESStatusBlocked', 6 => 'MESStatusCanceled');
foreach ($statuses as $value => $labelKey) {
	print '<option value="'.((int) $value).'"'.((int) $object->mes_status === (int) $value ? ' selected' : '').'>'.$langs->trans($labelKey).'</option>';
}
print '</select>'.ajax_combobox('mes_status').'</td></tr>';
print '<tr><td>'.$langs->trans('MESRequestDate').'</td><td>';
$form->selectDate($object->date_demande_mes ? (int) $object->date_demande_mes : -1, 'date_demande_mes', 1, 1, 1, '', 1, 1);
print '</td></tr>';
print '<tr><td>'.$langs->trans('MESPlannedDate').'</td><td>';
$form->selectDate($object->date_previsionnelle_mes ? (int) $object->date_previsionnelle_mes : -1, 'date_previsionnelle_mes', 1, 1, 1, '', 1, 1);
print '</td></tr>';
print '<tr><td>'.$langs->trans('MESRealDate').'</td><td>';
$form->selectDate($object->date_reelle_mes ? (int) $object->date_reelle_mes : -1, 'date_reelle_mes', 1, 1, 1, '', 1, 1);
print '</td></tr>';
print '<tr><td>'.$langs->trans('ConsuelReceived').'</td><td>'.$form->selectyesno('consuel_recu', (int) $object->consuel_recu, 1).'</td></tr>';
print '<tr><td>'.$langs->trans('ConsuelDate').'</td><td>';
$form->selectDate($object->date_consuel ? (int) $object->date_consuel : -1, 'date_consuel', 1, 1, 1, '', 1, 1);
print '</td></tr>';
print '<tr><td>'.$langs->trans('ConsuelReference').'</td><td><input type="text" class="flat minwidth300" name="ref_consuel" value="'.dol_escape_htmltag((string) $object->ref_consuel).'"></td></tr>';
print '<tr><td>'.$langs->trans('InjectionAuthorized').'</td><td>'.$form->selectyesno('injection_autorisee', (int) $object->injection_autorisee, 1).'</td></tr>';
print '<tr><td>'.$langs->trans('InjectionAuthorizationDate').'</td><td>';
$form->selectDate($object->date_autorisation_injection ? (int) $object->date_autorisation_injection : -1, 'date_autorisation_injection', 1, 1, 1, '', 1, 1);
print '</td></tr>';
print '<tr><td>'.$langs->trans('EnedisInterventionReference').'</td><td><input type="text" class="flat minwidth300" name="ref_intervention_enedis" value="'.dol_escape_htmltag((string) $object->ref_intervention_enedis).'"></td></tr>';
print '<tr><td>'.$langs->trans('MESComment').'</td><td><textarea class="flat centpercent" name="commentaire_mes" rows="3">'.dol_escape_htmltag((string) $object->commentaire_mes).'</textarea></td></tr>';
print '</table>';

if ($permissiontowrite) {
	print '<div class="center"><input type="submit" class="button button-save" value="'.$langs->trans('Save').'"></div>';
}
print '</form>';

print '<div class="tabsAction">';
if ($permissiontowrite) {
	$baseUrl = dol_buildpath('/procedurespv/raccordement/mes.php', 1).'?id='.(int) $object->id;
	$token = newToken();
	print '<a class="butAction" href="'.$baseUrl.'&action=set_required&token='.$token.'">'.$langs->trans('SetMESRequired').'</a>';
	print '<a class="butAction" href="'.$baseUrl.'&action=set_not_required&token='.$token.'">'.$langs->trans('SetMESNotRequired').'</a>';
	print '<a class="butAction" href="'.$baseUrl.'&action=mark_to_request&token='.$token.'">'.$langs->trans('MarkMESToRequest').'</a>';
	print '<a class="butAction" href="'.$baseUrl.'&action=mark_requested&token='.$token.'">'.$langs->trans('MarkMESRequested').'</a>';
	print '<a class="butAction" href="'.$baseUrl.'&action=mark_planned&token='.$token.'">'.$langs->trans('MarkMESPlanned').'</a>';
	print '<a class="butAction" href="'.$baseUrl.'&action=mark_done&token='.$token.'">'.$langs->trans('MarkMESDone').'</a>';
	print '<a class="butAction" href="'.$baseUrl.'&action=mark_blocked&token='.$token.'">'.$langs->trans('MarkMESBlocked').'</a>';
	print '<a class="butActionDelete" href="'.$baseUrl.'&action=mark_canceled&token='.$token.'">'.$langs->trans('MarkMESCanceled').'</a>';
}
print '</div>';

print dol_get_fiche_end();

llxFooter();
$db->close();
