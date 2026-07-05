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
require_once dol_buildpath('/procedurespv/class/convention.class.php', 0);
require_once dol_buildpath('/procedurespv/lib/procedurespv.lib.php', 0);

/**
 * Read Dolibarr date selector value.
 *
 * @param string $prefix Field prefix
 * @return int|null
 */
function procedurespvConventionReadDateTimeFromPost($prefix)
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
 * Build convention input payload.
 *
 * @return array<string, string|int|null>
 */
function procedurespvConventionReadPayload()
{
	return array(
		'type_convention' => GETPOST('type_convention', 'alphanohtml'),
		'ref_convention' => GETPOST('ref_convention', 'alphanohtml'),
		'status' => GETPOSTINT('status'),
		'date_reception' => procedurespvConventionReadDateTimeFromPost('date_reception'),
		'date_envoi_client' => procedurespvConventionReadDateTimeFromPost('date_envoi_client'),
		'date_signature_client' => procedurespvConventionReadDateTimeFromPost('date_signature_client'),
		'date_retour_enedis' => procedurespvConventionReadDateTimeFromPost('date_retour_enedis'),
		'date_validation' => procedurespvConventionReadDateTimeFromPost('date_validation'),
		'document_recu' => GETPOST('document_recu', 'alphanohtml'),
		'document_signe' => GETPOST('document_signe', 'alphanohtml'),
		'commentaire' => GETPOST('commentaire', 'restricthtml'),
	);
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
$permissiontowrite = procedurespvCanDo($user, 'raccordement', 'manage_convention');
if (!$permissiontoread) {
	accessforbidden();
}

$sensitiveActions = array('add_convention', 'update_convention', 'mark_received', 'mark_sent_signature', 'mark_signed', 'mark_returned_enedis', 'mark_validated', 'mark_obsolete');
if (in_array($action, $sensitiveActions, true) && !GETPOST('token', 'alpha')) {
	accessforbidden($langs->trans('ErrorBadToken'));
}

if (in_array($action, $sensitiveActions, true) && !$permissiontowrite) {
	accessforbidden();
}

if ($action === 'add_convention') {
	$convention = new Convention($db);
	$result = $convention->create($object, procedurespvConventionReadPayload());
	if ($result > 0) {
		setEventMessages($langs->trans('ConventionCreated'), null, 'mesgs');
		header('Location: '.dol_buildpath('/procedurespv/raccordement/convention.php', 1).'?id='.(int) $object->id);
		exit;
	}

	setEventMessages($convention->error, $convention->errors, 'errors');
}

if ($action === 'update_convention') {
	$convention = new Convention($db);
	$result = $convention->fetch($lineid);
	if ($result <= 0 || (int) $convention->fk_raccordement !== (int) $object->id) {
		accessforbidden($langs->trans('ErrorRecordNotFound'));
	}

	$result = $convention->update(procedurespvConventionReadPayload());
	if ($result > 0) {
		setEventMessages($langs->trans('RecordSaved'), null, 'mesgs');
		header('Location: '.dol_buildpath('/procedurespv/raccordement/convention.php', 1).'?id='.(int) $object->id);
		exit;
	}

	setEventMessages($convention->error, $convention->errors, 'errors');
}

$statusActions = array(
	'mark_received' => Convention::STATUS_RECEIVED,
	'mark_sent_signature' => Convention::STATUS_SENT_FOR_SIGNATURE,
	'mark_signed' => Convention::STATUS_SIGNED,
	'mark_returned_enedis' => Convention::STATUS_RETURNED_ENEDIS,
	'mark_validated' => Convention::STATUS_VALIDATED,
	'mark_obsolete' => Convention::STATUS_OBSOLETE,
);
if (isset($statusActions[$action])) {
	$convention = new Convention($db);
	$result = $convention->fetch($lineid);
	if ($result <= 0 || (int) $convention->fk_raccordement !== (int) $object->id) {
		accessforbidden($langs->trans('ErrorRecordNotFound'));
	}

	$result = $convention->setStatus((int) $statusActions[$action]);
	if ($result > 0) {
		if ($action === 'mark_received' && (int) $object->status < 11) {
			$object->setStatus($user, 11);
		}
		if ($action === 'mark_signed' && (int) $object->status < 12) {
			$object->setStatus($user, 12);
		}
		setEventMessages($langs->trans('ConventionStatusUpdated'), null, 'mesgs');
		header('Location: '.dol_buildpath('/procedurespv/raccordement/convention.php', 1).'?id='.(int) $object->id);
		exit;
	}

	setEventMessages($convention->error, $convention->errors, 'errors');
}

$form = new Form($db);
$conventionFetcher = new Convention($db);
$conventions = $conventionFetcher->fetchAllByRaccordement((int) $object->id);
$editedConvention = new Convention($db);
if ($action === 'edit' && $lineid > 0) {
	$result = $editedConvention->fetch($lineid);
	if ($result <= 0 || (int) $editedConvention->fk_raccordement !== (int) $object->id) {
		accessforbidden($langs->trans('ErrorRecordNotFound'));
	}
}

llxHeader('', $langs->trans('ConventionContrat'), '', '', 0, 0, '', '', '', 'mod-procedurespv page-raccordement-convention');

$head = procedurespvRaccordementPrepareHead($object);
print dol_get_fiche_head($head, 'convention', $langs->trans('Raccordement'), -1, $object->picto);

$linkback = '<a href="'.dol_buildpath('/procedurespv/raccordement/list.php', 1).'">'.$langs->trans('BackToList').'</a>';
dol_banner_tab($object, 'ref', $linkback, 1, 'ref', 'ref');

if ($permissiontowrite) {
	$isEdit = ((int) $editedConvention->id > 0);
	$formAction = $isEdit ? 'update_convention' : 'add_convention';
	$formTitle = $isEdit ? $langs->trans('EditConvention') : $langs->trans('AddConvention');

	print load_fiche_titre($formTitle, '', '');
	print '<form method="POST" action="'.dol_escape_htmltag($_SERVER['PHP_SELF']).'?id='.(int) $object->id.'">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="'.$formAction.'">';
	if ($isEdit) {
		print '<input type="hidden" name="lineid" value="'.((int) $editedConvention->id).'">';
	}

	print '<table class="border centpercent tableforfield">';
	print '<tr><td class="titlefield fieldrequired">'.$langs->trans('ConventionType').'</td><td><select class="flat minwidth300" name="type_convention" id="type_convention">';
	foreach (Convention::getTypeLabels() as $value => $labelKey) {
		print '<option value="'.dol_escape_htmltag($value).'"'.($editedConvention->type_convention === $value ? ' selected' : '').'>'.$langs->trans($labelKey).'</option>';
	}
	print '</select>'.ajax_combobox('type_convention').'</td></tr>';
	print '<tr><td>'.$langs->trans('ConventionReference').'</td><td><input type="text" class="flat minwidth300" name="ref_convention" value="'.dol_escape_htmltag((string) $editedConvention->ref_convention).'"></td></tr>';
	print '<tr><td>'.$langs->trans('ConventionStatus').'</td><td><select class="flat minwidth250" name="status" id="convention_status">';
	foreach (Convention::getStatusLabels() as $value => $labelKey) {
		print '<option value="'.((int) $value).'"'.((int) $editedConvention->status === (int) $value ? ' selected' : '').'>'.$langs->trans($labelKey).'</option>';
	}
	print '</select>'.ajax_combobox('convention_status').'</td></tr>';
	print '<tr><td>'.$langs->trans('ConventionReceptionDate').'</td><td>';
	$form->selectDate($editedConvention->date_reception ? (int) $editedConvention->date_reception : -1, 'date_reception', 1, 1, 1, '', 1, 1);
	print '</td></tr>';
	print '<tr><td>'.$langs->trans('ConventionClientSentDate').'</td><td>';
	$form->selectDate($editedConvention->date_envoi_client ? (int) $editedConvention->date_envoi_client : -1, 'date_envoi_client', 1, 1, 1, '', 1, 1);
	print '</td></tr>';
	print '<tr><td>'.$langs->trans('ConventionClientSignatureDate').'</td><td>';
	$form->selectDate($editedConvention->date_signature_client ? (int) $editedConvention->date_signature_client : -1, 'date_signature_client', 1, 1, 1, '', 1, 1);
	print '</td></tr>';
	print '<tr><td>'.$langs->trans('ConventionReturnEnedisDate').'</td><td>';
	$form->selectDate($editedConvention->date_retour_enedis ? (int) $editedConvention->date_retour_enedis : -1, 'date_retour_enedis', 1, 1, 1, '', 1, 1);
	print '</td></tr>';
	print '<tr><td>'.$langs->trans('ConventionValidationDate').'</td><td>';
	$form->selectDate($editedConvention->date_validation ? (int) $editedConvention->date_validation : -1, 'date_validation', 1, 1, 1, '', 1, 1);
	print '</td></tr>';
	print '<tr><td>'.$langs->trans('ConventionReceivedDocument').'</td><td><input type="text" class="flat minwidth400" name="document_recu" value="'.dol_escape_htmltag((string) $editedConvention->document_recu).'"></td></tr>';
	print '<tr><td>'.$langs->trans('ConventionSignedDocument').'</td><td><input type="text" class="flat minwidth400" name="document_signe" value="'.dol_escape_htmltag((string) $editedConvention->document_signe).'"></td></tr>';
	print '<tr><td>'.$langs->trans('Comment').'</td><td><textarea class="flat centpercent" name="commentaire" rows="3">'.dol_escape_htmltag((string) $editedConvention->commentaire).'</textarea></td></tr>';
	print '</table>';

	print '<div class="center"><input type="submit" class="button button-save" value="'.$langs->trans('Save').'"></div>';
	print '</form>';
	print '<br>';
}

print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<td>'.$langs->trans('ConventionType').'</td>';
print '<td>'.$langs->trans('ConventionReference').'</td>';
print '<td class="center">'.$langs->trans('Status').'</td>';
print '<td class="center">'.$langs->trans('ConventionReceptionDate').'</td>';
print '<td class="center">'.$langs->trans('ConventionClientSignatureDate').'</td>';
print '<td>'.$langs->trans('Files').'</td>';
print '<td class="right">'.$langs->trans('Actions').'</td>';
print '</tr>';

if (!empty($conventions)) {
	foreach ($conventions as $convention) {
		$typeLabels = Convention::getTypeLabels();
		$typeKey = isset($typeLabels[(string) $convention->type_convention]) ? $typeLabels[(string) $convention->type_convention] : 'ConventionTypeOtherEnedis';
		$baseUrl = dol_buildpath('/procedurespv/raccordement/convention.php', 1).'?id='.(int) $object->id.'&lineid='.(int) $convention->id;
		$token = newToken();

		print '<tr class="oddeven">';
		print '<td>'.$langs->trans($typeKey).'</td>';
		print '<td>'.dol_escape_htmltag((string) $convention->ref_convention).'</td>';
		print '<td class="center"><span class="badge">'.$langs->trans($convention->getStatusLabelKey()).'</span></td>';
		print '<td class="center">'.($convention->date_reception ? dol_print_date((int) $convention->date_reception, 'dayhour') : '').'</td>';
		print '<td class="center">'.($convention->date_signature_client ? dol_print_date((int) $convention->date_signature_client, 'dayhour') : '').'</td>';
		print '<td>';
		if ($convention->document_recu !== '') {
			print '<div>'.dol_escape_htmltag((string) $convention->document_recu).'</div>';
		}
		if ($convention->document_signe !== '') {
			print '<div>'.dol_escape_htmltag((string) $convention->document_signe).'</div>';
		}
		print '</td>';
		print '<td class="right nowrap">';
		if ($permissiontowrite) {
			print '<a class="button button-edit reposition smallpaddingimp" href="'.$baseUrl.'&action=edit">'.$langs->trans('Modify').'</a> ';
			print '<a class="button reposition smallpaddingimp" href="'.$baseUrl.'&action=mark_received&token='.$token.'">'.$langs->trans('MarkReceived').'</a> ';
			print '<a class="button reposition smallpaddingimp" href="'.$baseUrl.'&action=mark_sent_signature&token='.$token.'">'.$langs->trans('MarkSentSignature').'</a> ';
			print '<a class="button reposition smallpaddingimp" href="'.$baseUrl.'&action=mark_signed&token='.$token.'">'.$langs->trans('MarkSigned').'</a> ';
			print '<a class="button reposition smallpaddingimp" href="'.$baseUrl.'&action=mark_returned_enedis&token='.$token.'">'.$langs->trans('MarkReturnedEnedis').'</a> ';
			print '<a class="button reposition smallpaddingimp" href="'.$baseUrl.'&action=mark_validated&token='.$token.'">'.$langs->trans('MarkValidated').'</a> ';
			print '<a class="button reposition smallpaddingimp" href="'.$baseUrl.'&action=mark_obsolete&token='.$token.'">'.$langs->trans('MarkObsolete').'</a>';
		}
		print '</td>';
		print '</tr>';
	}
} else {
	print '<tr class="oddeven"><td colspan="7"><span class="opacitymedium">'.$langs->trans('NoRecordFound').'</span></td></tr>';
}
print '</table>';

print dol_get_fiche_end();

llxFooter();
$db->close();
