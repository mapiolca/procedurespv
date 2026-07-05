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
require_once dol_buildpath('/procedurespv/class/relance.class.php', 0);
require_once dol_buildpath('/procedurespv/lib/procedurespv.lib.php', 0);

/**
 * Read Dolibarr date selector value.
 *
 * @param string $prefix Field prefix
 * @return int|null
 */
function procedurespvRelanceReadDateTimeFromPost($prefix)
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
 * Build reminder input payload.
 *
 * @return array<string, string|int|null>
 */
function procedurespvRelanceReadPayload()
{
	return array(
		'type_relance' => GETPOST('type_relance', 'alphanohtml'),
		'target_type' => GETPOST('target_type', 'alphanohtml'),
		'target_id' => GETPOSTINT('target_id'),
		'destinataire_email' => GETPOST('destinataire_email', 'alphanohtml'),
		'date_prevue' => procedurespvRelanceReadDateTimeFromPost('date_prevue'),
		'date_envoi' => procedurespvRelanceReadDateTimeFromPost('date_envoi'),
		'canal' => GETPOST('canal', 'alphanohtml'),
		'status' => GETPOSTINT('status'),
		'modele_utilise' => GETPOST('modele_utilise', 'alphanohtml'),
		'resultat' => GETPOST('resultat', 'restricthtml'),
		'commentaire' => GETPOST('commentaire', 'restricthtml'),
		'fk_actioncomm' => GETPOSTINT('fk_actioncomm'),
	);
}

/**
 * Create a native Agenda event for a sent reminder.
 *
 * @param DoliDB $db Database handler
 * @param User $user User creating the event
 * @param Raccordement $object Raccordement object
 * @param Relance $relance Reminder object
 * @return int Agenda event id, 0 if not created
 */
function procedurespvRelanceCreateAgendaEvent($db, $user, $object, $relance)
{
	global $langs;

	if ((int) $relance->fk_actioncomm > 0) {
		return (int) $relance->fk_actioncomm;
	}

	if (!function_exists('isModEnabled') || !isModEnabled('agenda')) {
		return 0;
	}

	$actionCommFile = DOL_DOCUMENT_ROOT.'/comm/action/class/actioncomm.class.php';
	if (!file_exists($actionCommFile)) {
		return 0;
	}

	require_once $actionCommFile;
	if (!class_exists('ActionComm')) {
		return 0;
	}

	$typeLabels = Relance::getTypeLabels();
	$typeKey = isset($typeLabels[(string) $relance->type_relance]) ? $typeLabels[(string) $relance->type_relance] : 'Relance';

	$actioncomm = new ActionComm($db);
	$actioncomm->type_code = 'AC_OTH_AUTO';
	$actioncomm->label = $langs->trans('RelanceAgendaEventLabel').' - '.$langs->trans($typeKey).' - '.$object->ref;
	$actioncomm->datep = dol_now();
	$actioncomm->datef = dol_now();
	$actioncomm->percentage = 100;
	$actioncomm->elementtype = $object->element.'@procedurespv';
	$actioncomm->fk_element = (int) $object->id;
	$actioncomm->socid = (int) $object->fk_soc;
	$actioncomm->note_private = trim((string) $relance->commentaire."\n".(string) $relance->resultat);
	$result = $actioncomm->create($user);
	if ($result < 0) {
		dol_syslog('procedurespvRelanceCreateAgendaEvent failed: '.$actioncomm->error, LOG_WARNING);
		return 0;
	}

	return (int) $result;
}

$langs->loadLangs(array('procedurespv@procedurespv'));

$id = GETPOSTINT('id');
$lineid = GETPOSTINT('lineid');
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
$permissiontowrite = procedurespvCanDo($user, 'raccordement', 'manage_relance');
if (!$permissiontoread) {
	accessforbidden();
}

$sensitiveActions = array('add_relance', 'update_relance', 'mark_sent', 'mark_canceled');
if (in_array($action, $sensitiveActions, true) && !GETPOST('token', 'alpha')) {
	accessforbidden($langs->trans('ErrorBadToken'));
}

if (in_array($action, $sensitiveActions, true) && !$permissiontowrite) {
	accessforbidden();
}

if ($action === 'add_relance') {
	$relance = new Relance($db);
	$result = $relance->create($object, procedurespvRelanceReadPayload());
	if ($result > 0) {
		setEventMessages($langs->trans('RelanceCreated'), null, 'mesgs');
		header('Location: '.dol_buildpath('/procedurespv/raccordement/relances.php', 1).'?id='.(int) $object->id);
		exit;
	}

	setEventMessages($relance->error, $relance->errors, 'errors');
}

if ($action === 'update_relance') {
	$relance = new Relance($db);
	$result = $relance->fetch($lineid);
	if ($result <= 0 || (int) $relance->fk_raccordement !== (int) $object->id) {
		accessforbidden($langs->trans('ErrorRecordNotFound'));
	}

	$result = $relance->update(procedurespvRelanceReadPayload());
	if ($result > 0) {
		setEventMessages($langs->trans('RecordSaved'), null, 'mesgs');
		header('Location: '.dol_buildpath('/procedurespv/raccordement/relances.php', 1).'?id='.(int) $object->id);
		exit;
	}

	setEventMessages($relance->error, $relance->errors, 'errors');
}

if ($action === 'mark_sent' || $action === 'mark_canceled') {
	$relance = new Relance($db);
	$result = $relance->fetch($lineid);
	if ($result <= 0 || (int) $relance->fk_raccordement !== (int) $object->id) {
		accessforbidden($langs->trans('ErrorRecordNotFound'));
	}

	if ($action === 'mark_sent') {
		$fkActioncomm = procedurespvRelanceCreateAgendaEvent($db, $user, $object, $relance);
		$result = $relance->markSent($fkActioncomm);
		$messageKey = 'RelanceMarkedSent';
	} else {
		$result = $relance->markCanceled();
		$messageKey = 'RelanceCanceled';
	}

	if ($result > 0) {
		setEventMessages($langs->trans($messageKey), null, 'mesgs');
		header('Location: '.dol_buildpath('/procedurespv/raccordement/relances.php', 1).'?id='.(int) $object->id);
		exit;
	}

	setEventMessages($relance->error, $relance->errors, 'errors');
}

$form = new Form($db);
$relanceFetcher = new Relance($db);
$relances = $relanceFetcher->fetchAllByRaccordement((int) $object->id);
$editedRelance = new Relance($db);
if ($action === 'edit' && $lineid > 0) {
	$result = $editedRelance->fetch($lineid);
	if ($result <= 0 || (int) $editedRelance->fk_raccordement !== (int) $object->id) {
		accessforbidden($langs->trans('ErrorRecordNotFound'));
	}
}

llxHeader('', $langs->trans('Relances'), '', '', 0, 0, '', '', '', 'mod-procedurespv page-raccordement-relances');

$head = procedurespvRaccordementPrepareHead($object);
print dol_get_fiche_head($head, 'relances', $langs->trans('Raccordement'), -1, $object->picto);

$linkback = '<a href="'.dol_buildpath('/procedurespv/raccordement/list.php', 1).'">'.$langs->trans('BackToList').'</a>';
dol_banner_tab($object, 'ref', $linkback, 1, 'ref', 'ref');

if ($permissiontowrite) {
	$isEdit = ((int) $editedRelance->id > 0);
	$formAction = $isEdit ? 'update_relance' : 'add_relance';
	$formTitle = $isEdit ? $langs->trans('EditRelance') : $langs->trans('AddRelance');

	print load_fiche_titre($formTitle, '', '');
	print '<form method="POST" action="'.dol_escape_htmltag($_SERVER['PHP_SELF']).'?id='.(int) $object->id.'">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="'.$formAction.'">';
	if ($isEdit) {
		print '<input type="hidden" name="lineid" value="'.((int) $editedRelance->id).'">';
	}
	print '<input type="hidden" name="fk_actioncomm" value="'.((int) $editedRelance->fk_actioncomm).'">';

	print '<table class="border centpercent tableforfield">';
	print '<tr><td class="titlefield fieldrequired">'.$langs->trans('RelanceType').'</td><td><select class="flat minwidth400" name="type_relance" id="type_relance">';
	foreach (Relance::getTypeLabels() as $value => $labelKey) {
		print '<option value="'.dol_escape_htmltag($value).'"'.($editedRelance->type_relance === $value ? ' selected' : '').'>'.$langs->trans($labelKey).'</option>';
	}
	print '</select>'.ajax_combobox('type_relance').'</td></tr>';
	print '<tr><td>'.$langs->trans('TargetType').'</td><td><input type="text" class="flat minwidth200" name="target_type" value="'.dol_escape_htmltag((string) $editedRelance->target_type).'"></td></tr>';
	print '<tr><td>'.$langs->trans('TargetId').'</td><td><input type="number" class="flat width100" name="target_id" value="'.((int) $editedRelance->target_id).'"></td></tr>';
	print '<tr><td>'.$langs->trans('RecipientEmail').'</td><td><input type="text" class="flat minwidth300" name="destinataire_email" value="'.dol_escape_htmltag((string) $editedRelance->destinataire_email).'"></td></tr>';
	print '<tr><td>'.$langs->trans('RelancePlannedDate').'</td><td>';
	$form->selectDate($editedRelance->date_prevue ? (int) $editedRelance->date_prevue : -1, 'date_prevue', 1, 1, 1, '', 1, 1);
	print '</td></tr>';
	print '<tr><td>'.$langs->trans('RelanceSentDate').'</td><td>';
	$form->selectDate($editedRelance->date_envoi ? (int) $editedRelance->date_envoi : -1, 'date_envoi', 1, 1, 1, '', 1, 1);
	print '</td></tr>';
	print '<tr><td>'.$langs->trans('RelanceCanal').'</td><td><select class="flat minwidth200" name="canal" id="canal">';
	foreach (Relance::getCanalLabels() as $value => $labelKey) {
		print '<option value="'.dol_escape_htmltag($value).'"'.($editedRelance->canal === $value ? ' selected' : '').'>'.$langs->trans($labelKey).'</option>';
	}
	print '</select>'.ajax_combobox('canal').'</td></tr>';
	print '<tr><td>'.$langs->trans('Status').'</td><td><select class="flat minwidth200" name="status" id="relance_status">';
	foreach (Relance::getStatusLabels() as $value => $labelKey) {
		print '<option value="'.((int) $value).'"'.((int) $editedRelance->status === (int) $value ? ' selected' : '').'>'.$langs->trans($labelKey).'</option>';
	}
	print '</select>'.ajax_combobox('relance_status').'</td></tr>';
	print '<tr><td>'.$langs->trans('EmailTemplate').'</td><td><input type="text" class="flat minwidth300" name="modele_utilise" value="'.dol_escape_htmltag((string) $editedRelance->modele_utilise).'"></td></tr>';
	print '<tr><td>'.$langs->trans('RelanceResult').'</td><td><textarea class="flat centpercent" name="resultat" rows="2">'.dol_escape_htmltag((string) $editedRelance->resultat).'</textarea></td></tr>';
	print '<tr><td>'.$langs->trans('Comment').'</td><td><textarea class="flat centpercent" name="commentaire" rows="3">'.dol_escape_htmltag((string) $editedRelance->commentaire).'</textarea></td></tr>';
	print '</table>';

	print '<div class="center"><input type="submit" class="button button-save" value="'.$langs->trans('Save').'"></div>';
	print '</form>';
	print '<br>';
}

print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<td>'.$langs->trans('RelanceType').'</td>';
print '<td>'.$langs->trans('RecipientEmail').'</td>';
print '<td class="center">'.$langs->trans('RelancePlannedDate').'</td>';
print '<td class="center">'.$langs->trans('RelanceSentDate').'</td>';
print '<td class="center">'.$langs->trans('RelanceCanal').'</td>';
print '<td class="center">'.$langs->trans('Status').'</td>';
print '<td>'.$langs->trans('RelanceResult').'</td>';
print '<td class="right">'.$langs->trans('Actions').'</td>';
print '</tr>';

if (!empty($relances)) {
	foreach ($relances as $relance) {
		$typeLabels = Relance::getTypeLabels();
		$typeKey = isset($typeLabels[(string) $relance->type_relance]) ? $typeLabels[(string) $relance->type_relance] : 'Relance';
		$canalLabels = Relance::getCanalLabels();
		$canalKey = isset($canalLabels[(string) $relance->canal]) ? $canalLabels[(string) $relance->canal] : 'RelanceCanalManual';
		$baseUrl = dol_buildpath('/procedurespv/raccordement/relances.php', 1).'?id='.(int) $object->id.'&lineid='.(int) $relance->id;
		$token = newToken();
		$isOverdue = (int) $relance->status === Relance::STATUS_PLANNED && !empty($relance->date_prevue) && (int) $relance->date_prevue < dol_now();

		print '<tr class="oddeven">';
		print '<td>'.($isOverdue ? img_warning($langs->trans('RelanceOverdue')).' ' : '').$langs->trans($typeKey).'</td>';
		print '<td>'.dol_escape_htmltag((string) $relance->destinataire_email).'</td>';
		print '<td class="center">'.($relance->date_prevue ? dol_print_date((int) $relance->date_prevue, 'dayhour') : '').'</td>';
		print '<td class="center">'.($relance->date_envoi ? dol_print_date((int) $relance->date_envoi, 'dayhour') : '').'</td>';
		print '<td class="center">'.$langs->trans($canalKey).'</td>';
		print '<td class="center"><span class="badge">'.$langs->trans($relance->getStatusLabelKey()).'</span></td>';
		print '<td>'.dol_escape_htmltag((string) $relance->resultat).'</td>';
		print '<td class="right nowrap">';
		if ($permissiontowrite) {
			print '<a class="button button-edit reposition smallpaddingimp" href="'.$baseUrl.'&action=edit">'.$langs->trans('Modify').'</a> ';
			if ((int) $relance->status === Relance::STATUS_PLANNED) {
				print '<a class="button reposition smallpaddingimp" href="'.$baseUrl.'&action=mark_sent&token='.$token.'">'.$langs->trans('MarkRelanceSent').'</a> ';
				print '<a class="button button-delete reposition smallpaddingimp" href="'.$baseUrl.'&action=mark_canceled&token='.$token.'">'.$langs->trans('Cancel').'</a>';
			}
		}
		print '</td>';
		print '</tr>';
	}
} else {
	print '<tr class="oddeven"><td colspan="8"><span class="opacitymedium">'.$langs->trans('NoRecordFound').'</span></td></tr>';
}
print '</table>';

print dol_get_fiche_end();

llxFooter();
$db->close();
