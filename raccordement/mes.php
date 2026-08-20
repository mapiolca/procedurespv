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

$mesTransitions = array(
	'set_required' => (int) $object->mes_required !== 1 && (int) $object->mes_status !== 4,
	'set_not_required' => (int) $object->mes_required !== 0 && (int) $object->mes_status !== 4,
	'mark_to_request' => (int) $object->mes_required === 1 && in_array((int) $object->mes_status, array(0, 5, 6), true),
	'mark_requested' => (int) $object->mes_required === 1 && (int) $object->mes_status === 1,
	'mark_planned' => (int) $object->mes_required === 1 && (int) $object->mes_status === 2,
	'mark_done' => (int) $object->mes_required === 1 && in_array((int) $object->mes_status, array(2, 3), true),
	'mark_blocked' => (int) $object->mes_required === 1 && in_array((int) $object->mes_status, array(1, 2, 3), true),
	'mark_canceled' => (int) $object->mes_required === 1 && in_array((int) $object->mes_status, array(1, 2, 3, 5), true),
);
$sensitiveActions = array_merge(array('save'), array_keys($mesTransitions));
if (in_array($action, $sensitiveActions, true) && !GETPOST('token', 'alpha')) {
	accessforbidden($langs->trans('ErrorBadToken'));
}

if (in_array($action, $sensitiveActions, true)) {
	if (!$permissiontowrite) {
		accessforbidden();
	}

	if ($action === 'save') {
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
	} elseif (empty($mesTransitions[$action])) {
		accessforbidden($langs->trans('InvalidStatusTransition'));
	}

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
		procedurespvCreateAgendaEvent($object, $user, 'AgendaMesUpdated');
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
print '<tr><td class="titlefield">'.$langs->trans('MESRequired').'</td><td>'.$langs->trans((int) $object->mes_required === 1 ? 'Yes' : 'No').'</td></tr>';
$statuses = array(0 => 'MESStatusNotRequested', 1 => 'MESStatusToRequest', 2 => 'MESStatusRequested', 3 => 'MESStatusPlanned', 4 => 'MESStatusDone', 5 => 'MESStatusBlocked', 6 => 'MESStatusCanceled');
$mesStatusTypes = array(0 => 'status0', 1 => 'status1', 2 => 'status4', 3 => 'status4', 4 => 'status5', 5 => 'status8', 6 => 'status8');
print '<tr><td>'.$langs->trans('MESStatus').'</td><td>'.dolGetStatus($langs->trans($statuses[(int) $object->mes_status] ?? 'MESStatusNotRequested'), '', '', $mesStatusTypes[(int) $object->mes_status] ?? 'status0', 6).'</td></tr>';
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
	$mesActionLabels = array(
		'set_required' => 'SetMESRequired',
		'set_not_required' => 'SetMESNotRequired',
		'mark_to_request' => 'MarkMESToRequest',
		'mark_requested' => 'MarkMESRequested',
		'mark_planned' => 'MarkMESPlanned',
		'mark_done' => 'MarkMESDone',
		'mark_blocked' => 'MarkMESBlocked',
		'mark_canceled' => 'MarkMESCanceled',
	);
	foreach ($mesActionLabels as $mesAction => $labelKey) {
		if (!empty($mesTransitions[$mesAction])) {
			$buttonClass = $mesAction === 'mark_canceled' ? 'butActionDelete' : 'butAction';
			print '<a class="'.$buttonClass.'" href="'.$baseUrl.'&action='.$mesAction.'&token='.$token.'">'.$langs->trans($labelKey).'</a>';
		}
	}
}
print '</div>';

print dol_get_fiche_end();

llxFooter();
$db->close();
