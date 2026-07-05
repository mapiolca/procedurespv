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
$sensitiveActions = array_merge(array('add', 'update', 'freeze_snapshot'), array_keys($statusActions));

if (in_array($action, $sensitiveActions, true) && !GETPOST('token', 'alpha')) {
	accessforbidden($langs->trans('ErrorBadToken'));
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

	print '<tr><td>'.$langs->trans('Project').'</td><td><input type="number" class="flat width100" name="fk_project" value="'.((int) $object->fk_project).'"></td></tr>';
	print '<tr><td>'.$langs->trans('CentralePV').'</td><td>';
	if ($centralePVAdapter->isAvailable()) {
		print '<input type="number" class="flat width100" name="fk_centrale_pv" value="'.((int) $object->fk_centrale_pv).'"> ';
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

	print '<tr><td>'.$langs->trans('InstalledPowerKwc').'</td><td><input type="text" class="flat width100 right" name="puissance_installee_kwc" value="'.dol_escape_htmltag((string) $object->puissance_installee_kwc).'"></td></tr>';
	print '<tr><td>'.$langs->trans('InjectionPowerKva').'</td><td><input type="text" class="flat width100 right" name="puissance_injection_kva" value="'.dol_escape_htmltag((string) $object->puissance_injection_kva).'"></td></tr>';
	print '<tr><td>'.$langs->trans('Responsible').'</td><td><input type="number" class="flat width100" name="fk_user_resp" value="'.((int) $object->fk_user_resp).'"></td></tr>';
	print '<tr><td>'.$langs->trans('NotePublic').'</td><td><textarea class="flat centpercent" name="note_public" rows="3">'.dol_escape_htmltag((string) $object->note_public).'</textarea></td></tr>';
	print '<tr><td>'.$langs->trans('NotePrivate').'</td><td><textarea class="flat centpercent" name="note_private" rows="3">'.dol_escape_htmltag((string) $object->note_private).'</textarea></td></tr>';
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

	print '<div class="fichecenter">';
	print '<div class="fichehalfleft">';
	print '<table class="border centpercent tableforfield">';
	print '<tr><td class="titlefield">'.$langs->trans('ThirdParty').'</td><td>'.((int) $object->fk_soc > 0 ? '<a href="'.DOL_URL_ROOT.'/societe/card.php?socid='.((int) $object->fk_soc).'">'.((int) $object->fk_soc).'</a>' : '').'</td></tr>';
	print '<tr><td>'.$langs->trans('Project').'</td><td>'.((int) $object->fk_project > 0 ? '<a href="'.DOL_URL_ROOT.'/projet/card.php?id='.((int) $object->fk_project).'">'.((int) $object->fk_project).'</a>' : '').'</td></tr>';
	print '<tr><td>'.$langs->trans('CentralePV').'</td><td>';
	if ((int) $object->fk_centrale_pv > 0) {
		$centraleLabel = $centralePVAdapter->getCentraleLabel((int) $object->fk_centrale_pv);
		$centraleUrl = $centralePVAdapter->getCentraleUrl((int) $object->fk_centrale_pv);
		if ($centraleUrl !== '') {
			print '<a href="'.$centraleUrl.'">'.dol_escape_htmltag($centraleLabel !== '' ? $centraleLabel : (string) $object->fk_centrale_pv).'</a>';
		} else {
			print dol_escape_htmltag($centraleLabel !== '' ? $centraleLabel : (string) $object->fk_centrale_pv);
		}
	}
	print '</td></tr>';
	print '<tr><td>'.$langs->trans('SiteName').'</td><td>'.dol_escape_htmltag((string) $object->site_name_snapshot).'</td></tr>';
	print '<tr><td>'.$langs->trans('Address').'</td><td>'.dol_escape_htmltag((string) $object->site_address_snapshot).'</td></tr>';
	print '<tr><td>'.$langs->trans('Zip').'</td><td>'.dol_escape_htmltag((string) $object->site_zip_snapshot).'</td></tr>';
	print '<tr><td>'.$langs->trans('Town').'</td><td>'.dol_escape_htmltag((string) $object->site_town_snapshot).'</td></tr>';
	print '<tr><td>'.$langs->trans('PRM').'</td><td>'.dol_escape_htmltag((string) $object->prm).'</td></tr>';
	print '</table>';
	print '</div>';

	print '<div class="fichehalfright">';
	print '<table class="border centpercent tableforfield">';
	print '<tr><td class="titlefield">'.$langs->trans('Status').'</td><td>'.$object->getLibStatut(5).'</td></tr>';
	print '<tr><td>'.$langs->trans('ExploitationType').'</td><td>'.dol_escape_htmltag((string) $object->type_exploitation).'</td></tr>';
	print '<tr><td>'.$langs->trans('InstalledPowerKwc').'</td><td>'.price((float) $object->puissance_installee_kwc).' kWc</td></tr>';
	print '<tr><td>'.$langs->trans('InjectionPowerKva').'</td><td>'.price((float) $object->puissance_injection_kva).' kVA</td></tr>';
	print '<tr><td>'.$langs->trans('EnedisReference').'</td><td>'.dol_escape_htmltag((string) $object->ref_enedis).'</td></tr>';
	print '<tr><td>'.$langs->trans('NextAction').'</td><td>'.$langs->trans($object->getNextAction()).'</td></tr>';
	print '<tr><td>'.$langs->trans('LatestRelance').'</td><td>'.($relanceSummary['last_sent'] ? dol_print_date((int) $relanceSummary['last_sent'], 'dayhour') : '').'</td></tr>';
	print '<tr><td>'.$langs->trans('NextRelance').'</td><td>'.($relanceSummary['next_due'] ? dol_print_date((int) $relanceSummary['next_due'], 'dayhour') : '').'</td></tr>';
	print '<tr><td>'.$langs->trans('ActiveRelances').'</td><td>'.((int) $relanceSummary['active_count']).'</td></tr>';
	if ((int) $relanceSummary['overdue_count'] > 0) {
		print '<tr><td>'.$langs->trans('OverdueRelances').'</td><td><span class="badge badge-status4">'.((int) $relanceSummary['overdue_count']).'</span></td></tr>';
	}
	print '<tr><td>'.$langs->trans('BlockingReason').'</td><td>'.$langs->trans($object->getBlockingReason()).'</td></tr>';
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
	if ($permissiontoadd) {
		print '<a class="butAction" href="'.dol_buildpath('/procedurespv/raccordement/card.php', 1).'?id='.(int) $object->id.'&action=edit">'.$langs->trans('Modify').'</a>';
	}
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
