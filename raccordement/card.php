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
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formprojet.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/doleditor.class.php';
require_once dol_buildpath('/procedurespv/class/raccordement.class.php', 0);
require_once dol_buildpath('/procedurespv/class/centralepvadapter.class.php', 0);
require_once dol_buildpath('/procedurespv/class/relance.class.php', 0);
require_once dol_buildpath('/procedurespv/lib/procedurespv.lib.php', 0);

$langs->loadLangs(array('companies', 'projects', 'users', 'procedurespv@procedurespv'));

$id = GETPOSTINT('id');
$action = GETPOST('action', 'aZ09');
$tab = GETPOST('tab', 'aZ09');
$cancel = GETPOST('cancel', 'alpha');
$backtopage = GETPOST('backtopage', 'alpha');

if (!isModEnabled('procedurespv')) {
	accessforbidden();
}

$object = new Raccordement($db);
$form = new Form($db);
$formproject = new FormProjets($db);
$centralePVAdapter = new CentralePVAdapter($db);

$permissiontoread = procedurespvCanDo($user, 'raccordement', 'read');
$permissiontoadd = procedurespvCanDo($user, 'raccordement', 'write');
$permissiontodelete = procedurespvCanDo($user, 'raccordement', 'delete');

if (!$permissiontoread) {
	accessforbidden();
}

if ($id > 0) {
	$result = $object->fetch($id);
	if ($result <= 0) {
		accessforbidden($langs->trans('ErrorRecordNotFound'));
	}
}

if ($cancel) {
	$action = '';
}

$statusActions = array(
	'send_collecte' => array('permission' => 'send_collecte', 'status' => 2, 'message' => 'CollecteMarkedSent'),
	'mark_collecte_submitted' => array('permission' => 'write', 'status' => 4, 'message' => 'CollecteMarkedSubmitted'),
	'validate_collecte' => array('permission' => 'validate_collecte', 'status' => 6, 'message' => 'CollecteValidated'),
	'ready_deposit' => array('permission' => 'write', 'status' => 7, 'message' => 'RaccordementReadyForDeposit'),
	'deposit_enedis' => array('permission' => 'write', 'status' => 8, 'message' => 'RaccordementDepositedEnedis'),
	'instruction_enedis' => array('permission' => 'write', 'status' => 9, 'message' => 'RaccordementInstructionEnedis'),
	'convention_received' => array('permission' => 'manage_convention', 'status' => 11, 'message' => 'ConventionMarkedReceived'),
	'convention_signed' => array('permission' => 'manage_convention', 'status' => 12, 'message' => 'ConventionMarkedSigned'),
	'mes_requested' => array('permission' => 'manage_mes', 'status' => 14, 'message' => 'MESMarkedRequested'),
	'mes_done' => array('permission' => 'manage_mes', 'status' => 15, 'message' => 'MESMarkedDone'),
	'close' => array('permission' => 'write', 'status' => 16, 'message' => 'RaccordementClosed'),
	'cancel' => array('permission' => 'write', 'status' => -1, 'message' => 'RaccordementCanceled'),
);
$sensitiveActions = array_merge(array('add', 'update', 'updatefield', 'freeze_snapshot'), array_keys($statusActions));

if (in_array($action, $sensitiveActions, true) && (!GETPOST('token', 'alpha') || (function_exists('checkToken') && !checkToken()))) {
	accessforbidden($langs->trans('ErrorBadToken'));
}

/**
 * Return site source choices.
 *
 * @return array<string, string>
 */
function procedurespvGetSiteSourceOptions()
{
	return array(
		'local' => 'SiteSourceLocal',
		'centralepv' => 'SiteSourceCentralePV',
	);
}

/**
 * Return exploitation type choices.
 *
 * @return array<string, string>
 */
function procedurespvGetExploitationTypeOptions()
{
	return array(
		'autoconsommation_totale' => 'ExploitationAutoconsommationTotale',
		'autoconsommation_surplus' => 'ExploitationAutoconsommationSurplus',
		'injection_totale' => 'ExploitationInjectionTotale',
		'autoconsommation_collective' => 'ExploitationAutoconsommationCollective',
	);
}

/**
 * Return fields editable directly from the draft card.
 *
 * @return array<string, string>
 */
function procedurespvGetDraftEditableFields()
{
	return array(
		'fk_soc' => 'integer',
		'fk_project' => 'integer',
		'fk_centrale_pv' => 'integer',
		'site_source' => 'site_source',
		'site_name_snapshot' => 'text',
		'site_address_snapshot' => 'text',
		'site_zip_snapshot' => 'text',
		'site_town_snapshot' => 'text',
		'prm' => 'text',
		'type_exploitation' => 'type_exploitation',
		'puissance_installee_kwc' => 'number',
		'puissance_injection_kva' => 'number',
		'ref_enedis' => 'text',
		'fk_user_resp' => 'integer',
	);
}

/**
 * Render a select with translated options.
 *
 * @param string $htmlName Input name
 * @param array<string, string> $options Options
 * @param string $selected Selected value
 * @param string $cssClass CSS class
 * @return string
 */
function procedurespvRenderTranslatedSelect($htmlName, array $options, $selected, $cssClass = 'flat minwidth200')
{
	global $langs;

	$htmlId = preg_replace('/[^a-zA-Z0-9_]/', '_', $htmlName);
	$out = '<select class="'.dol_escape_htmltag($cssClass).'" name="'.dol_escape_htmltag($htmlName).'" id="'.dol_escape_htmltag($htmlId).'">';
	$out .= '<option value="">&nbsp;</option>';
	foreach ($options as $value => $labelKey) {
		$out .= '<option value="'.dol_escape_htmltag($value).'"'.($selected === $value ? ' selected' : '').'>'.$langs->trans($labelKey).'</option>';
	}
	$out .= '</select>';
	if (function_exists('ajax_combobox')) {
		$out .= ajax_combobox($htmlId);
	}

	return $out;
}

/**
 * Return the input HTML for a draft editable field.
 *
 * @param Raccordement $object Current object
 * @param string $field Field name
 * @param Form $form Form helper
 * @param FormProjets $formproject Project form helper
 * @param CentralePVAdapter $centralePVAdapter Centrale PV adapter
 * @return string
 */
function procedurespvRenderDraftFieldInput($object, $field, $form, $formproject, $centralePVAdapter)
{
	global $langs;

	switch ($field) {
		case 'fk_soc':
			return $form->select_company((int) $object->fk_soc, 'fieldvalue', '', 1);

		case 'fk_project':
			if (isModEnabled('project')) {
				$socidforproject = (int) $object->fk_soc;
				return $formproject->select_projects(($socidforproject > 0 ? $socidforproject : -1), (int) $object->fk_project, 'fieldvalue', 0, 0, 1, 1, 0, 0, 0, '', 1, 0, 'maxwidth500 widthcentpercentminusxx');
			}
			return '<input type="hidden" name="fieldvalue" value="0"><span class="opacitymedium">'.dol_escape_htmltag($langs->trans('ProjectModuleDisabled')).'</span>';

		case 'fk_centrale_pv':
			if ($centralePVAdapter->isAvailable()) {
				$centraleOptions = $centralePVAdapter->getCentraleOptions((int) $object->fk_centrale_pv);
				if (!empty($centraleOptions)) {
					$out = $form->selectarray('fieldvalue', $centraleOptions, (int) $object->fk_centrale_pv, 1, 0, 0, '', 0, 0, 0, '', 'flat maxwidth500 widthcentpercentminusxx');
					$out .= ajax_combobox('fieldvalue');
					return $out;
				}
			}
			return '<input type="number" class="flat width100" name="fieldvalue" value="'.((int) $object->fk_centrale_pv).'">';

		case 'site_source':
			return procedurespvRenderTranslatedSelect('fieldvalue', procedurespvGetSiteSourceOptions(), (string) $object->site_source);

		case 'type_exploitation':
			return procedurespvRenderTranslatedSelect('fieldvalue', procedurespvGetExploitationTypeOptions(), (string) $object->type_exploitation, 'flat minwidth300');

		case 'puissance_installee_kwc':
			return '<input type="text" class="flat width100 right" name="fieldvalue" value="'.dol_escape_htmltag((string) $object->puissance_installee_kwc).'"> <span class="opacitymedium">kWc</span>';

		case 'puissance_injection_kva':
			return '<input type="text" class="flat width100 right" name="fieldvalue" value="'.dol_escape_htmltag((string) $object->puissance_injection_kva).'"> <span class="opacitymedium">kVA</span>';

		case 'fk_user_resp':
			return $form->select_dolusers(((int) $object->fk_user_resp > 0 ? (int) $object->fk_user_resp : ''), 'fieldvalue', 1, null, 0, '', '', '0', 0, 0, '', 0, '', 'maxwidth300');

		case 'site_address_snapshot':
			return '<input type="text" class="flat minwidth500" name="fieldvalue" value="'.dol_escape_htmltag((string) $object->site_address_snapshot).'">';

		case 'site_name_snapshot':
			return '<input type="text" class="flat minwidth300" name="fieldvalue" value="'.dol_escape_htmltag((string) $object->site_name_snapshot).'">';

		case 'site_zip_snapshot':
			return '<input type="text" class="flat maxwidth100" name="fieldvalue" value="'.dol_escape_htmltag((string) $object->site_zip_snapshot).'">';

		case 'site_town_snapshot':
			return '<input type="text" class="flat minwidth300" name="fieldvalue" value="'.dol_escape_htmltag((string) $object->site_town_snapshot).'">';

		case 'prm':
			return '<input type="text" class="flat minwidth200" name="fieldvalue" value="'.dol_escape_htmltag((string) $object->prm).'">';

		case 'ref_enedis':
			return '<input type="text" class="flat minwidth200" name="fieldvalue" value="'.dol_escape_htmltag((string) $object->ref_enedis).'">';
	}

	return '';
}

/**
 * Render a table row with independent draft edition.
 *
 * @param Raccordement $object Current object
 * @param string $field Field name
 * @param string $label Label
 * @param string $valueHtml Display value
 * @param string $inputHtml Input HTML
 * @param bool $canEditDraft Can edit
 * @param string $fieldToEdit Edited field
 * @return void
 */
function procedurespvPrintDraftEditableRow($object, $field, $label, $valueHtml, $inputHtml, $canEditDraft, $fieldToEdit)
{
	global $langs;

	$urlcard = dol_buildpath('/procedurespv/raccordement/card.php', 1).'?id='.(int) $object->id;
	$rowId = 'field_'.$field;
	print '<tr class="field_'.$field.'" id="'.dol_escape_htmltag($rowId).'">';
	print '<td class="titlefieldmiddle">'.$label.'</td>';
	if ($canEditDraft && $fieldToEdit === $field) {
		$formId = 'form_'.$field;
		print '<td>';
		print '<form id="'.dol_escape_htmltag($formId).'" method="POST" action="'.dol_escape_htmltag($urlcard).'">';
		print '<input type="hidden" name="token" value="'.newToken().'">';
		print '<input type="hidden" name="action" value="updatefield">';
		print '<input type="hidden" name="id" value="'.((int) $object->id).'">';
		print '<input type="hidden" name="field" value="'.dol_escape_htmltag($field).'">';
		print $inputHtml;
		print '</form>';
		print '</td>';
		print '<td class="right nowraponall">';
		print '<button type="submit" form="'.dol_escape_htmltag($formId).'" class="reposition" title="'.dol_escape_htmltag($langs->trans('Save')).'">'.img_picto($langs->trans('Save'), 'tick').'</button>';
		print ' <a class="reposition" href="'.dol_escape_htmltag($urlcard).'#'.dol_escape_htmltag($rowId).'" title="'.dol_escape_htmltag($langs->trans('Cancel')).'">'.img_picto($langs->trans('Cancel'), 'cancel').'</a>';
		print '</td>';
	} else {
		print '<td>'.$valueHtml.'</td>';
		print '<td class="right nowraponall">';
		if ($canEditDraft) {
			print '<a class="editfielda reposition" href="'.dol_escape_htmltag($urlcard).'&action=editfield&field='.urlencode($field).'&token='.newToken().'#'.dol_escape_htmltag($rowId).'">'.img_edit($langs->transnoentitiesnoconv('Modify'), 0).'</a>';
		} else {
			print '&nbsp;';
		}
		print '</td>';
	}
	print '</tr>';
}

if ($action === 'add' && $permissiontoadd) {
	$object->fk_soc = GETPOSTINT('fk_soc') > 0 ? GETPOSTINT('fk_soc') : null;
	$object->fk_project = GETPOSTINT('fk_project') > 0 ? GETPOSTINT('fk_project') : null;
	$object->fk_centrale_pv = GETPOSTINT('fk_centrale_pv') > 0 ? GETPOSTINT('fk_centrale_pv') : null;
	$object->site_source = GETPOST('site_source', 'aZ09');
	$object->type_exploitation = GETPOST('type_exploitation', 'alphanohtml');
	$object->puissance_installee_kwc = (float) price2num(GETPOST('puissance_installee_kwc', 'alphanohtml'));
	$object->puissance_injection_kva = (float) price2num(GETPOST('puissance_injection_kva', 'alphanohtml'));
	$object->prm = GETPOST('prm', 'alphanohtml');
	$object->site_name_snapshot = GETPOST('site_name_snapshot', 'alphanohtml');
	$object->site_address_snapshot = GETPOST('site_address_snapshot', 'restricthtml');
	$object->site_zip_snapshot = GETPOST('site_zip_snapshot', 'alphanohtml');
	$object->site_town_snapshot = GETPOST('site_town_snapshot', 'alphanohtml');
	$object->fk_user_resp = GETPOSTINT('fk_user_resp') > 0 ? GETPOSTINT('fk_user_resp') : null;
	$object->note_public = GETPOST('note_public', 'restricthtml');
	$object->note_private = GETPOST('note_private', 'restricthtml');

	$result = $object->create($user);
	if ($result > 0) {
		setEventMessages($langs->trans('RaccordementCreated'), null, 'mesgs');
		header('Location: '.dol_buildpath('/procedurespv/raccordement/card.php', 1).'?id='.(int) $object->id);
		exit;
	}

	setEventMessages($object->error, $object->errors, 'errors');
	$action = 'create';
}

if ($action === 'update' && $permissiontoadd && $object->id > 0) {
	$object->fk_soc = GETPOSTINT('fk_soc') > 0 ? GETPOSTINT('fk_soc') : null;
	$object->fk_project = GETPOSTINT('fk_project') > 0 ? GETPOSTINT('fk_project') : null;
	$object->fk_centrale_pv = GETPOSTINT('fk_centrale_pv') > 0 ? GETPOSTINT('fk_centrale_pv') : null;
	$object->site_source = GETPOST('site_source', 'aZ09');
	$object->type_exploitation = GETPOST('type_exploitation', 'alphanohtml');
	$object->puissance_installee_kwc = (float) price2num(GETPOST('puissance_installee_kwc', 'alphanohtml'));
	$object->puissance_injection_kva = (float) price2num(GETPOST('puissance_injection_kva', 'alphanohtml'));
	$object->prm = GETPOST('prm', 'alphanohtml');
	$object->site_name_snapshot = GETPOST('site_name_snapshot', 'alphanohtml');
	$object->site_address_snapshot = GETPOST('site_address_snapshot', 'restricthtml');
	$object->site_zip_snapshot = GETPOST('site_zip_snapshot', 'alphanohtml');
	$object->site_town_snapshot = GETPOST('site_town_snapshot', 'alphanohtml');
	$object->fk_user_resp = GETPOSTINT('fk_user_resp') > 0 ? GETPOSTINT('fk_user_resp') : null;
	$object->note_public = GETPOST('note_public', 'restricthtml');
	$object->note_private = GETPOST('note_private', 'restricthtml');

	$result = $object->update($user);
	if ($result > 0) {
		setEventMessages($langs->trans('RecordSaved'), null, 'mesgs');
		header('Location: '.dol_buildpath('/procedurespv/raccordement/card.php', 1).'?id='.(int) $object->id);
		exit;
	}

	setEventMessages($object->error, $object->errors, 'errors');
	$action = 'edit';
}

if ($action === 'updatefield' && $permissiontoadd && $object->id > 0) {
	if ((int) $object->status !== 0) {
		accessforbidden($langs->trans('ErrorForbidden'));
	}

	$field = preg_replace('/[^a-zA-Z0-9_]/', '', GETPOST('field', 'nohtml'));
	$editableFields = procedurespvGetDraftEditableFields();
	if (!array_key_exists($field, $editableFields)) {
		accessforbidden($langs->trans('ErrorForbidden'));
	}

	$object->oldcopy = clone $object;
	$object->context['trigger_reason'] = 'draft_field_update';
	$object->context['changed_fields'] = array($field);

	switch ($field) {
		case 'fk_soc':
			$object->fk_soc = GETPOSTINT('fieldvalue') > 0 ? GETPOSTINT('fieldvalue') : null;
			break;

		case 'fk_project':
			$object->fk_project = GETPOSTINT('fieldvalue') > 0 ? GETPOSTINT('fieldvalue') : null;
			break;

		case 'fk_centrale_pv':
			$object->fk_centrale_pv = GETPOSTINT('fieldvalue') > 0 ? GETPOSTINT('fieldvalue') : null;
			break;

		case 'site_source':
			$fieldValue = GETPOST('fieldvalue', 'aZ09');
			$siteSources = procedurespvGetSiteSourceOptions();
			$object->site_source = array_key_exists($fieldValue, $siteSources) ? $fieldValue : 'local';
			break;

		case 'type_exploitation':
			$fieldValue = GETPOST('fieldvalue', 'alphanohtml');
			$types = procedurespvGetExploitationTypeOptions();
			$object->type_exploitation = ($fieldValue === '' || array_key_exists($fieldValue, $types)) ? $fieldValue : '';
			break;

		case 'puissance_installee_kwc':
			$object->puissance_installee_kwc = (float) price2num(GETPOST('fieldvalue', 'alphanohtml'));
			break;

		case 'puissance_injection_kva':
			$object->puissance_injection_kva = (float) price2num(GETPOST('fieldvalue', 'alphanohtml'));
			break;

		case 'site_address_snapshot':
			$object->site_address_snapshot = GETPOST('fieldvalue', 'restricthtml');
			break;

		case 'site_name_snapshot':
			$object->site_name_snapshot = GETPOST('fieldvalue', 'alphanohtml');
			break;

		case 'site_zip_snapshot':
			$object->site_zip_snapshot = GETPOST('fieldvalue', 'alphanohtml');
			break;

		case 'site_town_snapshot':
			$object->site_town_snapshot = GETPOST('fieldvalue', 'alphanohtml');
			break;

		case 'prm':
			$object->prm = GETPOST('fieldvalue', 'alphanohtml');
			break;

		case 'ref_enedis':
			$object->ref_enedis = GETPOST('fieldvalue', 'alphanohtml');
			break;

		case 'fk_user_resp':
			$object->fk_user_resp = GETPOSTINT('fieldvalue') > 0 ? GETPOSTINT('fieldvalue') : null;
			break;
	}

	$result = $object->update($user);
	if ($result > 0) {
		setEventMessages($langs->trans('RecordSaved'), null, 'mesgs');
		header('Location: '.dol_buildpath('/procedurespv/raccordement/card.php', 1).'?id='.(int) $object->id.'#field_'.$field);
		exit;
	}

	setEventMessages($object->error, $object->errors, 'errors');
	$action = 'editfield';
}

if (isset($statusActions[$action]) && $object->id > 0) {
	$permissionName = $statusActions[$action]['permission'];
	if (!procedurespvCanDo($user, 'raccordement', $permissionName)) {
		accessforbidden();
	}

	if ($action === 'deposit_enedis' && empty($object->date_depot_enedis)) {
		$object->date_depot_enedis = dol_now();
	}
	if ($action === 'mes_done') {
		$object->date_mes = dol_now();
	}

	$result = $object->setStatus($user, (int) $statusActions[$action]['status']);
	if ($result > 0) {
		setEventMessages($langs->trans($statusActions[$action]['message']), null, 'mesgs');
		header('Location: '.dol_buildpath('/procedurespv/raccordement/card.php', 1).'?id='.(int) $object->id);
		exit;
	}

	setEventMessages($object->error, $object->errors, 'errors');
}

if ($action === 'freeze_snapshot' && $object->id > 0) {
	if (!procedurespvCanDo($user, 'raccordement', 'freeze_snapshot')) {
		accessforbidden();
	}

	$result = $object->freezeSnapshot($user);
	if ($result > 0) {
		setEventMessages($langs->trans('SnapshotFrozen'), null, 'mesgs');
		header('Location: '.dol_buildpath('/procedurespv/raccordement/card.php', 1).'?id='.(int) $object->id);
		exit;
	}

	setEventMessages($object->error, $object->errors, 'errors');
}

$title = $langs->trans('Raccordement');
llxHeader('', $title, '', '', 0, 0, '', '', '', 'mod-procedurespv page-raccordement-card');

if ($action === 'create' || $action === 'edit') {
	if (!$permissiontoadd) {
		accessforbidden();
	}

	$formAction = $action === 'create' ? 'add' : 'update';
	print load_fiche_titre($action === 'create' ? $langs->trans('NewRaccordement') : $langs->trans('EditRaccordement'), '', $object->picto);

	print '<form method="POST" action="'.dol_escape_htmltag($_SERVER['PHP_SELF']).'">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="'.$formAction.'">';
	if ($object->id > 0) {
		print '<input type="hidden" name="id" value="'.((int) $object->id).'">';
	}

	print '<table class="border centpercent tableforfield">';
	print '<tr><td class="titlefieldcreate fieldrequired">'.$langs->trans('ThirdParty').'</td><td>';
	print $form->select_company($object->fk_soc, 'fk_soc', '', 1);
	print '</td></tr>';

	print '<tr><td>'.$langs->trans('Project').'</td><td>';
	if (isModEnabled('project')) {
		$socidforproject = (int) $object->fk_soc;
		print $formproject->select_projects(($socidforproject > 0 ? $socidforproject : -1), (int) $object->fk_project, 'fk_project', 0, 0, 1, 1, 0, 0, 0, '', 1, 0, 'maxwidth500 widthcentpercentminusxx');
	} else {
		print '<input type="hidden" name="fk_project" value="0">';
		print '<span class="opacitymedium">'.$langs->trans('ProjectModuleDisabled').'</span>';
	}
	print '</td></tr>';
	print '<tr><td>'.$langs->trans('CentralePV').'</td><td>';
	if ($centralePVAdapter->isAvailable()) {
		$centraleOptions = $centralePVAdapter->getCentraleOptions((int) $object->fk_centrale_pv);
		if (!empty($centraleOptions)) {
			print $form->selectarray('fk_centrale_pv', $centraleOptions, (int) $object->fk_centrale_pv, 1, 0, 0, '', 0, 0, 0, '', 'flat maxwidth500 widthcentpercentminusxx');
			print ajax_combobox('fk_centrale_pv');
			print ' ';
		} else {
			print '<input type="number" class="flat width100" name="fk_centrale_pv" value="'.((int) $object->fk_centrale_pv).'"> ';
		}
		print '<span class="opacitymedium">'.$langs->trans('CentralePVAdapterAvailable').'</span>';
	} else {
		print '<input type="hidden" name="fk_centrale_pv" value="0">';
		print '<span class="opacitymedium">'.$langs->trans('CentralePVAdapterUnavailable').'</span>';
	}
	print '</td></tr>';

	print '<tr><td>'.$langs->trans('SiteSource').'</td><td>';
	print '<select class="flat minwidth200" name="site_source" id="site_source">';
	$siteSources = array('local' => 'SiteSourceLocal', 'centralepv' => 'SiteSourceCentralePV');
	foreach ($siteSources as $value => $labelKey) {
		print '<option value="'.dol_escape_htmltag($value).'"'.($object->site_source === $value ? ' selected' : '').'>'.$langs->trans($labelKey).'</option>';
	}
	print '</select>';
	print ajax_combobox('site_source');
	print '</td></tr>';

	print '<tr><td>'.$langs->trans('SiteName').'</td><td><input type="text" class="flat minwidth300" name="site_name_snapshot" value="'.dol_escape_htmltag((string) $object->site_name_snapshot).'"></td></tr>';
	print '<tr><td>'.$langs->trans('Address').'</td><td><input type="text" class="flat minwidth500" name="site_address_snapshot" value="'.dol_escape_htmltag((string) $object->site_address_snapshot).'"></td></tr>';
	print '<tr><td>'.$langs->trans('Zip').'</td><td><input type="text" class="flat maxwidth100" name="site_zip_snapshot" value="'.dol_escape_htmltag((string) $object->site_zip_snapshot).'"></td></tr>';
	print '<tr><td>'.$langs->trans('Town').'</td><td><input type="text" class="flat minwidth300" name="site_town_snapshot" value="'.dol_escape_htmltag((string) $object->site_town_snapshot).'"></td></tr>';
	print '<tr><td>'.$langs->trans('PRM').'</td><td><input type="text" class="flat minwidth200" name="prm" value="'.dol_escape_htmltag((string) $object->prm).'"></td></tr>';

	print '<tr><td>'.$langs->trans('ExploitationType').'</td><td>';
	print '<select class="flat minwidth300" name="type_exploitation" id="type_exploitation">';
	$types = array(
		'autoconsommation_totale' => 'ExploitationAutoconsommationTotale',
		'autoconsommation_surplus' => 'ExploitationAutoconsommationSurplus',
		'injection_totale' => 'ExploitationInjectionTotale',
		'autoconsommation_collective' => 'ExploitationAutoconsommationCollective',
	);
	print '<option value="">&nbsp;</option>';
	foreach ($types as $value => $labelKey) {
		print '<option value="'.dol_escape_htmltag($value).'"'.($object->type_exploitation === $value ? ' selected' : '').'>'.$langs->trans($labelKey).'</option>';
	}
	print '</select>';
	print ajax_combobox('type_exploitation');
	print '</td></tr>';

	print '<tr><td>'.$langs->trans('InstalledPowerKwc').'</td><td><input type="text" class="flat width100 right" name="puissance_installee_kwc" value="'.dol_escape_htmltag((string) $object->puissance_installee_kwc).'"> <span class="opacitymedium">kWc</span></td></tr>';
	print '<tr><td>'.$langs->trans('InjectionPowerKva').'</td><td><input type="text" class="flat width100 right" name="puissance_injection_kva" value="'.dol_escape_htmltag((string) $object->puissance_injection_kva).'"> <span class="opacitymedium">kVA</span></td></tr>';
	print '<tr><td>'.$langs->trans('Responsible').'</td><td><input type="number" class="flat width100" name="fk_user_resp" value="'.((int) $object->fk_user_resp).'"></td></tr>';
	$useWysiwygPublic = isModEnabled('fckeditor') && getDolGlobalInt('FCKEDITOR_ENABLE_NOTE_PUBLIC') > 0;
	$useWysiwygPrivate = isModEnabled('fckeditor') && getDolGlobalInt('FCKEDITOR_ENABLE_NOTE_PRIVATE') > 0;
	print '<tr><td>'.$langs->trans('NotePublic').'</td><td>';
	$doleditor = new DolEditor('note_public', (string) $object->note_public, '', 160, 'dolibarr_notes', '', false, true, $useWysiwygPublic, 4, '90%');
	print $doleditor->Create(1);
	print '</td></tr>';
	print '<tr><td>'.$langs->trans('NotePrivate').'</td><td>';
	$doleditor = new DolEditor('note_private', (string) $object->note_private, '', 160, 'dolibarr_notes', '', false, true, $useWysiwygPrivate, 4, '90%');
	print $doleditor->Create(1);
	print '</td></tr>';
	print '</table>';

	print '<div class="center">';
	print '<input type="submit" class="button button-save" value="'.$langs->trans('Save').'">';
	print '<input type="submit" class="button button-cancel" name="cancel" value="'.$langs->trans('Cancel').'">';
	print '</div>';
	print '</form>';
} elseif ($object->id > 0) {
	if ($tab === '') {
		$tab = 'card';
	}

	$head = procedurespvRaccordementPrepareHead($object);
	print dol_get_fiche_head($head, $tab, $langs->trans('Raccordement'), -1, $object->picto);

	$linkback = '<a href="'.dol_buildpath('/procedurespv/raccordement/list.php', 1).'">'.$langs->trans('BackToList').'</a>';
	dol_banner_tab($object, 'ref', $linkback, 1, 'ref', 'ref');

	if ($tab !== 'card') {
		print '<div class="opacitymedium">'.$langs->trans('TabPlannedInNextBatch').'</div>';
		print dol_get_fiche_end();
		llxFooter();
		$db->close();
		exit;
	}

	$relanceFetcher = new Relance($db);
	$relanceSummary = $relanceFetcher->getSummaryByRaccordement((int) $object->id);
	$canEditDraftFields = $permissiontoadd && (int) $object->status === 0;
	$fieldToEdit = ($action === 'editfield') ? preg_replace('/[^a-zA-Z0-9_]/', '', GETPOST('field', 'nohtml')) : '';
	if (!$canEditDraftFields || !array_key_exists($fieldToEdit, procedurespvGetDraftEditableFields())) {
		$fieldToEdit = '';
	}
	$siteSourceOptions = procedurespvGetSiteSourceOptions();
	$exploitationTypeOptions = procedurespvGetExploitationTypeOptions();

	print '<div class="fichecenter">';
	print '<div class="fichehalfleft">';
	print '<table class="border centpercent tableforfield">';
	$thirdpartyValue = ((int) $object->fk_soc > 0 ? '<a href="'.DOL_URL_ROOT.'/societe/card.php?socid='.((int) $object->fk_soc).'">'.((int) $object->fk_soc).'</a>' : '');
	procedurespvPrintDraftEditableRow($object, 'fk_soc', $langs->trans('ThirdParty'), $thirdpartyValue, procedurespvRenderDraftFieldInput($object, 'fk_soc', $form, $formproject, $centralePVAdapter), $canEditDraftFields, $fieldToEdit);

	$projectValue = ((int) $object->fk_project > 0 ? '<a href="'.DOL_URL_ROOT.'/projet/card.php?id='.((int) $object->fk_project).'">'.((int) $object->fk_project).'</a>' : '');
	procedurespvPrintDraftEditableRow($object, 'fk_project', $langs->trans('Project'), $projectValue, procedurespvRenderDraftFieldInput($object, 'fk_project', $form, $formproject, $centralePVAdapter), $canEditDraftFields, $fieldToEdit);

	$centraleValue = '';
	if ((int) $object->fk_centrale_pv > 0) {
		$centraleLabel = $centralePVAdapter->getCentraleLabel((int) $object->fk_centrale_pv);
		$centraleUrl = $centralePVAdapter->getCentraleUrl((int) $object->fk_centrale_pv);
		if ($centraleUrl !== '') {
			$centraleValue = '<a href="'.$centraleUrl.'">'.dol_escape_htmltag($centraleLabel !== '' ? $centraleLabel : (string) $object->fk_centrale_pv).'</a>';
		} else {
			$centraleValue = dol_escape_htmltag($centraleLabel !== '' ? $centraleLabel : (string) $object->fk_centrale_pv);
		}
	}
	procedurespvPrintDraftEditableRow($object, 'fk_centrale_pv', $langs->trans('CentralePV'), $centraleValue, procedurespvRenderDraftFieldInput($object, 'fk_centrale_pv', $form, $formproject, $centralePVAdapter), $canEditDraftFields, $fieldToEdit);

	$siteSourceLabelKey = isset($siteSourceOptions[(string) $object->site_source]) ? $siteSourceOptions[(string) $object->site_source] : '';
	procedurespvPrintDraftEditableRow($object, 'site_source', $langs->trans('SiteSource'), ($siteSourceLabelKey !== '' ? $langs->trans($siteSourceLabelKey) : ''), procedurespvRenderDraftFieldInput($object, 'site_source', $form, $formproject, $centralePVAdapter), $canEditDraftFields, $fieldToEdit);
	procedurespvPrintDraftEditableRow($object, 'site_name_snapshot', $langs->trans('SiteName'), dol_escape_htmltag((string) $object->site_name_snapshot), procedurespvRenderDraftFieldInput($object, 'site_name_snapshot', $form, $formproject, $centralePVAdapter), $canEditDraftFields, $fieldToEdit);
	procedurespvPrintDraftEditableRow($object, 'site_address_snapshot', $langs->trans('Address'), dol_escape_htmltag((string) $object->site_address_snapshot), procedurespvRenderDraftFieldInput($object, 'site_address_snapshot', $form, $formproject, $centralePVAdapter), $canEditDraftFields, $fieldToEdit);
	procedurespvPrintDraftEditableRow($object, 'site_zip_snapshot', $langs->trans('Zip'), dol_escape_htmltag((string) $object->site_zip_snapshot), procedurespvRenderDraftFieldInput($object, 'site_zip_snapshot', $form, $formproject, $centralePVAdapter), $canEditDraftFields, $fieldToEdit);
	procedurespvPrintDraftEditableRow($object, 'site_town_snapshot', $langs->trans('Town'), dol_escape_htmltag((string) $object->site_town_snapshot), procedurespvRenderDraftFieldInput($object, 'site_town_snapshot', $form, $formproject, $centralePVAdapter), $canEditDraftFields, $fieldToEdit);
	procedurespvPrintDraftEditableRow($object, 'prm', $langs->trans('PRM'), dol_escape_htmltag((string) $object->prm), procedurespvRenderDraftFieldInput($object, 'prm', $form, $formproject, $centralePVAdapter), $canEditDraftFields, $fieldToEdit);
	print '</table>';
	print '</div>';

	print '<div class="fichehalfright">';
	print '<table class="border centpercent tableforfield">';
	print '<tr><td class="titlefield">'.$langs->trans('Status').'</td><td colspan="2">'.$object->getLibStatut(5).'</td></tr>';
	$exploitationLabelKey = isset($exploitationTypeOptions[(string) $object->type_exploitation]) ? $exploitationTypeOptions[(string) $object->type_exploitation] : '';
	procedurespvPrintDraftEditableRow($object, 'type_exploitation', $langs->trans('ExploitationType'), ($exploitationLabelKey !== '' ? $langs->trans($exploitationLabelKey) : dol_escape_htmltag((string) $object->type_exploitation)), procedurespvRenderDraftFieldInput($object, 'type_exploitation', $form, $formproject, $centralePVAdapter), $canEditDraftFields, $fieldToEdit);
	procedurespvPrintDraftEditableRow($object, 'puissance_installee_kwc', $langs->trans('InstalledPowerKwc'), price((float) $object->puissance_installee_kwc).' kWc', procedurespvRenderDraftFieldInput($object, 'puissance_installee_kwc', $form, $formproject, $centralePVAdapter), $canEditDraftFields, $fieldToEdit);
	procedurespvPrintDraftEditableRow($object, 'puissance_injection_kva', $langs->trans('InjectionPowerKva'), price((float) $object->puissance_injection_kva).' kVA', procedurespvRenderDraftFieldInput($object, 'puissance_injection_kva', $form, $formproject, $centralePVAdapter), $canEditDraftFields, $fieldToEdit);
	procedurespvPrintDraftEditableRow($object, 'ref_enedis', $langs->trans('EnedisReference'), dol_escape_htmltag((string) $object->ref_enedis), procedurespvRenderDraftFieldInput($object, 'ref_enedis', $form, $formproject, $centralePVAdapter), $canEditDraftFields, $fieldToEdit);
	$responsibleValue = ((int) $object->fk_user_resp > 0 ? '<a href="'.DOL_URL_ROOT.'/user/card.php?id='.((int) $object->fk_user_resp).'">'.((int) $object->fk_user_resp).'</a>' : '');
	procedurespvPrintDraftEditableRow($object, 'fk_user_resp', $langs->trans('Responsible'), $responsibleValue, procedurespvRenderDraftFieldInput($object, 'fk_user_resp', $form, $formproject, $centralePVAdapter), $canEditDraftFields, $fieldToEdit);
	print '<tr><td>'.$langs->trans('NextAction').'</td><td colspan="2">'.$langs->trans($object->getNextAction()).'</td></tr>';
	print '<tr><td>'.$langs->trans('LatestRelance').'</td><td colspan="2">'.($relanceSummary['last_sent'] ? dol_print_date((int) $relanceSummary['last_sent'], 'dayhour') : '').'</td></tr>';
	print '<tr><td>'.$langs->trans('NextRelance').'</td><td colspan="2">'.($relanceSummary['next_due'] ? dol_print_date((int) $relanceSummary['next_due'], 'dayhour') : '').'</td></tr>';
	print '<tr><td>'.$langs->trans('ActiveRelances').'</td><td colspan="2">'.((int) $relanceSummary['active_count']).'</td></tr>';
	if ((int) $relanceSummary['overdue_count'] > 0) {
		print '<tr><td>'.$langs->trans('OverdueRelances').'</td><td colspan="2"><span class="badge badge-status4">'.((int) $relanceSummary['overdue_count']).'</span></td></tr>';
	}
	print '<tr><td>'.$langs->trans('BlockingReason').'</td><td colspan="2">'.$langs->trans($object->getBlockingReason()).'</td></tr>';
	print '</table>';
	print '</div>';
	print '</div>';

	print '<br>';
	print '<table class="noborder centpercent">';
	print '<tr class="liste_titre">';
	print '<td>'.$langs->trans('Step').'</td>';
	print '<td class="center">'.$langs->trans('Status').'</td>';
	print '<td>'.$langs->trans('LastAction').'</td>';
	print '<td>'.$langs->trans('NextAction').'</td>';
	print '<td>'.$langs->trans('Responsible').'</td>';
	print '</tr>';

	$trackingRows = array(
		array('CollecteClient', $object->status >= 4 ? 'RaccordementStatusCollecteSubmitted' : ($object->status >= 2 ? 'RaccordementStatusCollecteSent' : 'RaccordementStatusCollecteToSend'), 'CollecteSentDate', $object->getNextAction(), 'ResponsibleInternal'),
		array('MandatEnedis', $object->date_mandat_validation ? 'SignatureStatusValidated' : ($object->date_mandat_signature ? 'SignatureStatusToControl' : 'SignatureStatusWaiting'), 'MandatSignatureDate', 'NextActionInternalControl', 'ResponsibleInternal'),
		array('DemandeRaccordement', $object->status >= 8 ? 'RaccordementStatusDepositedEnedis' : 'RequestStatusToComplete', 'EnedisDepositDate', 'NextActionPrepareEnedisDeposit', 'ResponsibleInternal'),
		array('CARDi', 'CardiStatusToDetermine', 'Dash', 'Dash', 'Dash'),
		array('ConventionContrat', $object->status >= 12 ? 'RaccordementStatusConventionSigned' : 'ConventionStatusNotReceived', 'Dash', 'NextActionSendConvention', 'Enedis'),
		array('MiseEnService', $object->status >= 15 ? 'RaccordementStatusMesDone' : 'MESStatusNotRequested', 'Dash', 'NextActionPrepareMES', 'ResponsibleInternal'),
	);

	foreach ($trackingRows as $trackingRow) {
		print '<tr class="oddeven">';
		print '<td>'.$langs->trans($trackingRow[0]).'</td>';
		print '<td class="center"><span class="badge">'.$langs->trans($trackingRow[1]).'</span></td>';
		print '<td>'.$langs->trans($trackingRow[2]).'</td>';
		print '<td>'.$langs->trans($trackingRow[3]).'</td>';
		print '<td>'.$langs->trans($trackingRow[4]).'</td>';
		print '</tr>';
	}
	print '</table>';

	print '<div class="tabsAction">';
	$token = newToken();
	if (procedurespvCanDo($user, 'raccordement', 'send_collecte')) {
		print '<a class="butAction" href="'.dol_buildpath('/procedurespv/raccordement/card.php', 1).'?id='.(int) $object->id.'&action=send_collecte&token='.$token.'">'.$langs->trans('SendCollecteClientAction').'</a>';
	}
	if ($permissiontoadd) {
		print '<a class="butAction" href="'.dol_buildpath('/procedurespv/raccordement/card.php', 1).'?id='.(int) $object->id.'&action=mark_collecte_submitted&token='.$token.'">'.$langs->trans('MarkCollecteSubmitted').'</a>';
		print '<a class="butAction" href="'.dol_buildpath('/procedurespv/raccordement/card.php', 1).'?id='.(int) $object->id.'&action=ready_deposit&token='.$token.'">'.$langs->trans('MarkReadyForDeposit').'</a>';
		print '<a class="butAction" href="'.dol_buildpath('/procedurespv/raccordement/card.php', 1).'?id='.(int) $object->id.'&action=deposit_enedis&token='.$token.'">'.$langs->trans('MarkDepositedEnedis').'</a>';
		print '<a class="butAction" href="'.dol_buildpath('/procedurespv/raccordement/card.php', 1).'?id='.(int) $object->id.'&action=instruction_enedis&token='.$token.'">'.$langs->trans('MarkInstructionEnedis').'</a>';
		print '<a class="butAction" href="'.dol_buildpath('/procedurespv/raccordement/card.php', 1).'?id='.(int) $object->id.'&action=close&token='.$token.'">'.$langs->trans('Close').'</a>';
		print '<a class="butActionDelete" href="'.dol_buildpath('/procedurespv/raccordement/card.php', 1).'?id='.(int) $object->id.'&action=cancel&token='.$token.'">'.$langs->trans('CancelObject').'</a>';
	}
	if (procedurespvCanDo($user, 'raccordement', 'validate_collecte')) {
		print '<a class="butAction" href="'.dol_buildpath('/procedurespv/raccordement/card.php', 1).'?id='.(int) $object->id.'&action=validate_collecte&token='.$token.'">'.$langs->trans('ValidateCollecte').'</a>';
	}
	if (procedurespvCanDo($user, 'raccordement', 'freeze_snapshot')) {
		print '<a class="butAction" href="'.dol_buildpath('/procedurespv/raccordement/card.php', 1).'?id='.(int) $object->id.'&action=freeze_snapshot&token='.$token.'">'.$langs->trans('FreezeSnapshot').'</a>';
	}
	if (procedurespvCanDo($user, 'raccordement', 'manage_convention')) {
		print '<a class="butAction" href="'.dol_buildpath('/procedurespv/raccordement/card.php', 1).'?id='.(int) $object->id.'&action=convention_received&token='.$token.'">'.$langs->trans('MarkConventionReceived').'</a>';
		print '<a class="butAction" href="'.dol_buildpath('/procedurespv/raccordement/card.php', 1).'?id='.(int) $object->id.'&action=convention_signed&token='.$token.'">'.$langs->trans('MarkConventionSigned').'</a>';
	}
	if (procedurespvCanDo($user, 'raccordement', 'manage_mes')) {
		print '<a class="butAction" href="'.dol_buildpath('/procedurespv/raccordement/card.php', 1).'?id='.(int) $object->id.'&action=mes_requested&token='.$token.'">'.$langs->trans('MarkMESRequested').'</a>';
		print '<a class="butAction" href="'.dol_buildpath('/procedurespv/raccordement/card.php', 1).'?id='.(int) $object->id.'&action=mes_done&token='.$token.'">'.$langs->trans('MarkMESDone').'</a>';
	}
	if ($permissiontodelete) {
		print '<span class="butActionRefused classfortooltip" title="'.$langs->trans('FeatureNotYetActive').'">'.$langs->trans('Delete').'</span>';
	}
	print '</div>';

	print dol_get_fiche_end();
} else {
	header('Location: '.dol_buildpath('/procedurespv/raccordement/list.php', 1));
	exit;
}

llxFooter();
$db->close();
