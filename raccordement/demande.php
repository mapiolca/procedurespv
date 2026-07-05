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
require_once dol_buildpath('/procedurespv/class/piece.class.php', 0);
require_once dol_buildpath('/procedurespv/class/signature.class.php', 0);
require_once dol_buildpath('/procedurespv/lib/procedurespv.lib.php', 0);

/**
 * Read Dolibarr date selector value.
 *
 * @param string $prefix Field prefix
 * @return int|null
 */
function procedurespvReadDateTimeFromPost($prefix)
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

$langs->loadLangs(array('companies', 'procedurespv@procedurespv'));

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
$permissiontowrite = procedurespvCanDo($user, 'raccordement', 'write');
$permissiontofreeze = procedurespvCanDo($user, 'raccordement', 'freeze_snapshot');
if (!$permissiontoread) {
	accessforbidden();
}

$sensitiveActions = array('save', 'mark_complete', 'freeze_snapshot', 'mark_deposited', 'mark_complement', 'mark_instruction');
if (in_array($action, $sensitiveActions, true) && !GETPOST('token', 'alpha')) {
	accessforbidden($langs->trans('ErrorBadToken'));
}

if (in_array($action, $sensitiveActions, true)) {
	if (!$permissiontowrite) {
		accessforbidden();
	}

	$object->ref_enedis = GETPOST('ref_enedis', 'alphanohtml');
	$object->date_depot_enedis = procedurespvReadDateTimeFromPost('date_depot_enedis');
	$object->portail_utilise = GETPOST('portail_utilise', 'alphanohtml');
	$object->puissance_raccordement_demandee = (float) price2num(GETPOST('puissance_raccordement_demandee', 'alphanohtml'));
	$object->type_reseau = GETPOST('type_reseau', 'alphanohtml');
	$object->mono_tri_confirme = GETPOST('mono_tri_confirme', 'alphanohtml');
	$object->onduleurs = GETPOST('onduleurs', 'restricthtml');
	$object->nombre_onduleurs = GETPOSTINT('nombre_onduleurs');
	$object->references_onduleurs = GETPOST('references_onduleurs', 'restricthtml');
	$object->puissance_onduleurs = (float) price2num(GETPOST('puissance_onduleurs', 'alphanohtml'));
	$object->modules = GETPOST('modules', 'restricthtml');
	$object->nombre_modules = GETPOSTINT('nombre_modules');
	$object->puissance_unitaire_modules = (float) price2num(GETPOST('puissance_unitaire_modules', 'alphanohtml'));
	$object->schema_unifilaire = GETPOST('schema_unifilaire', 'alphanohtml');
	$object->plan_masse = GETPOST('plan_masse', 'alphanohtml');
	$object->plan_cadastral = GETPOST('plan_cadastral', 'alphanohtml');
	$object->bilan_puissance = GETPOST('bilan_puissance', 'alphanohtml');
	$object->consuel_requis = GETPOSTINT('consuel_requis');
	$object->commentaire_technique = GETPOST('commentaire_technique', 'restricthtml');

	if ($action === 'mark_complete') {
		$object->demande_status = 1;
	}
	if ($action === 'freeze_snapshot') {
		if (!$permissiontofreeze) {
			accessforbidden();
		}
		$object->date_snapshot = dol_now();
	}
	if ($action === 'mark_deposited') {
		$object->demande_status = 2;
		$object->status = 8;
		if (empty($object->date_depot_enedis)) {
			$object->date_depot_enedis = dol_now();
		}
		if (getDolGlobalInt('PROCEDURESPV_AUTO_FREEZE_ON_ENEDIS_DEPOSIT', 1) > 0) {
			$object->date_snapshot = dol_now();
		}
	}
	if ($action === 'mark_complement') {
		$object->demande_status = 3;
		$object->status = 10;
	}
	if ($action === 'mark_instruction') {
		$object->demande_status = 4;
		$object->status = 9;
	}

	$object->context['trigger_reason'] = 'enedis_request_update';
	$object->context['changed_fields'] = array('ref_enedis', 'date_depot_enedis', 'demande_status');
	$result = $object->update($user);
	if ($result > 0) {
		setEventMessages($langs->trans('RecordSaved'), null, 'mesgs');
		header('Location: '.dol_buildpath('/procedurespv/raccordement/demande.php', 1).'?id='.(int) $object->id);
		exit;
	}

	setEventMessages($object->error, $object->errors, 'errors');
}

$form = new Form($db);
$pieceFetcher = new Piece($db);
$pieces = $pieceFetcher->fetchAllByRaccordement((int) $object->id);
$signature = new Signature($db);
$signature->fetchLatestForRaccordement((int) $object->id, Signature::TYPE_MANDAT_ENEDIS);

llxHeader('', $langs->trans('DemandeRaccordement'), '', '', 0, 0, '', '', '', 'mod-procedurespv page-raccordement-demande');

$head = procedurespvRaccordementPrepareHead($object);
print dol_get_fiche_head($head, 'demande', $langs->trans('Raccordement'), -1, $object->picto);

$linkback = '<a href="'.dol_buildpath('/procedurespv/raccordement/list.php', 1).'">'.$langs->trans('BackToList').'</a>';
dol_banner_tab($object, 'ref', $linkback, 1, 'ref', 'ref');

print '<form method="POST" action="'.dol_escape_htmltag($_SERVER['PHP_SELF']).'?id='.(int) $object->id.'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="save">';

print '<table class="border centpercent tableforfield">';
print '<tr><td class="titlefield">'.$langs->trans('EnedisReference').'</td><td><input type="text" class="flat minwidth300" name="ref_enedis" value="'.dol_escape_htmltag((string) $object->ref_enedis).'"></td></tr>';
print '<tr><td>'.$langs->trans('EnedisDepositDate').'</td><td>';
$form->selectDate($object->date_depot_enedis ? (int) $object->date_depot_enedis : -1, 'date_depot_enedis', 1, 1, 1, '', 1, 1);
print '</td></tr>';
print '<tr><td>'.$langs->trans('PortalUsed').'</td><td><input type="text" class="flat minwidth300" name="portail_utilise" value="'.dol_escape_htmltag((string) $object->portail_utilise).'"></td></tr>';
print '<tr><td>'.$langs->trans('RequestedConnectionPower').'</td><td><input type="text" class="flat width100 right" name="puissance_raccordement_demandee" value="'.dol_escape_htmltag((string) $object->puissance_raccordement_demandee).'"> kVA</td></tr>';
print '<tr><td>'.$langs->trans('NetworkType').'</td><td><select class="flat minwidth200" name="type_reseau" id="type_reseau">';
foreach (array('monophase' => 'NetworkMonophase', 'triphase' => 'NetworkTriphase', 'unknown' => 'Unknown') as $value => $labelKey) {
	print '<option value="'.dol_escape_htmltag($value).'"'.($object->type_reseau === $value ? ' selected' : '').'>'.$langs->trans($labelKey).'</option>';
}
print '</select>'.ajax_combobox('type_reseau').'</td></tr>';
print '<tr><td>'.$langs->trans('ConfirmedPhaseType').'</td><td><select class="flat minwidth200" name="mono_tri_confirme" id="mono_tri_confirme">';
foreach (array('monophase' => 'NetworkMonophase', 'triphase' => 'NetworkTriphase', 'unknown' => 'Unknown') as $value => $labelKey) {
	print '<option value="'.dol_escape_htmltag($value).'"'.($object->mono_tri_confirme === $value ? ' selected' : '').'>'.$langs->trans($labelKey).'</option>';
}
print '</select>'.ajax_combobox('mono_tri_confirme').'</td></tr>';
print '<tr><td>'.$langs->trans('Inverters').'</td><td><textarea class="flat centpercent" name="onduleurs" rows="2">'.dol_escape_htmltag((string) $object->onduleurs).'</textarea></td></tr>';
print '<tr><td>'.$langs->trans('InverterCount').'</td><td><input type="number" class="flat width75" name="nombre_onduleurs" value="'.((int) $object->nombre_onduleurs).'"></td></tr>';
print '<tr><td>'.$langs->trans('InverterReferences').'</td><td><textarea class="flat centpercent" name="references_onduleurs" rows="2">'.dol_escape_htmltag((string) $object->references_onduleurs).'</textarea></td></tr>';
print '<tr><td>'.$langs->trans('InverterPower').'</td><td><input type="text" class="flat width100 right" name="puissance_onduleurs" value="'.dol_escape_htmltag((string) $object->puissance_onduleurs).'"> kVA</td></tr>';
print '<tr><td>'.$langs->trans('Modules').'</td><td><textarea class="flat centpercent" name="modules" rows="2">'.dol_escape_htmltag((string) $object->modules).'</textarea></td></tr>';
print '<tr><td>'.$langs->trans('ModuleCount').'</td><td><input type="number" class="flat width75" name="nombre_modules" value="'.((int) $object->nombre_modules).'"></td></tr>';
print '<tr><td>'.$langs->trans('ModuleUnitPower').'</td><td><input type="text" class="flat width100 right" name="puissance_unitaire_modules" value="'.dol_escape_htmltag((string) $object->puissance_unitaire_modules).'"> Wc</td></tr>';
print '<tr><td>'.$langs->trans('SingleLineDiagram').'</td><td><input type="text" class="flat minwidth300" name="schema_unifilaire" value="'.dol_escape_htmltag((string) $object->schema_unifilaire).'"></td></tr>';
print '<tr><td>'.$langs->trans('SitePlan').'</td><td><input type="text" class="flat minwidth300" name="plan_masse" value="'.dol_escape_htmltag((string) $object->plan_masse).'"></td></tr>';
print '<tr><td>'.$langs->trans('CadastralPlan').'</td><td><input type="text" class="flat minwidth300" name="plan_cadastral" value="'.dol_escape_htmltag((string) $object->plan_cadastral).'"></td></tr>';
print '<tr><td>'.$langs->trans('PowerBalance').'</td><td><input type="text" class="flat minwidth300" name="bilan_puissance" value="'.dol_escape_htmltag((string) $object->bilan_puissance).'"></td></tr>';
print '<tr><td>'.$langs->trans('ConsuelRequired').'</td><td>'.$form->selectyesno('consuel_requis', (int) $object->consuel_requis, 1).'</td></tr>';
print '<tr><td>'.$langs->trans('TechnicalComment').'</td><td><textarea class="flat centpercent" name="commentaire_technique" rows="3">'.dol_escape_htmltag((string) $object->commentaire_technique).'</textarea></td></tr>';
print '</table>';

print '<div class="center"><input type="submit" class="button button-save" value="'.$langs->trans('Save').'"></div>';
print '</form>';

print '<div class="tabsAction">';
if ($permissiontowrite) {
	$baseUrl = dol_buildpath('/procedurespv/raccordement/demande.php', 1).'?id='.(int) $object->id;
	$token = newToken();
	print '<a class="butAction" href="'.$baseUrl.'&action=mark_complete&token='.$token.'">'.$langs->trans('MarkRequestComplete').'</a>';
	if ($permissiontofreeze) {
		print '<a class="butAction" href="'.$baseUrl.'&action=freeze_snapshot&token='.$token.'">'.$langs->trans('FreezeSnapshot').'</a>';
	}
	print '<a class="butAction" href="'.$baseUrl.'&action=mark_deposited&token='.$token.'">'.$langs->trans('MarkDepositedEnedis').'</a>';
	print '<a class="butAction" href="'.$baseUrl.'&action=mark_complement&token='.$token.'">'.$langs->trans('MarkComplementRequested').'</a>';
	print '<a class="butAction" href="'.$baseUrl.'&action=mark_instruction&token='.$token.'">'.$langs->trans('MarkInstructionEnedis').'</a>';
}
print '</div>';

print '<br>';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><td>'.$langs->trans('UsefulPieces').'</td><td class="center">'.$langs->trans('Status').'</td><td>'.$langs->trans('File').'</td></tr>';
if (!empty($pieces)) {
	foreach ($pieces as $piece) {
		print '<tr class="oddeven"><td>'.dol_escape_htmltag((string) $piece->label).'</td><td class="center"><span class="badge">'.$langs->trans($piece->getStatusLabelKey()).'</span></td><td>'.dol_escape_htmltag((string) $piece->filename).'</td></tr>';
	}
} else {
	print '<tr class="oddeven"><td colspan="3"><span class="opacitymedium">'.$langs->trans('NoRecordFound').'</span></td></tr>';
}
if ((int) $signature->id > 0) {
	print '<tr class="oddeven"><td>'.$langs->trans('MandatEnedis').'</td><td class="center"><span class="badge">'.$langs->trans($signature->getStatusLabelKey()).'</span></td><td>'.dol_escape_htmltag((string) $signature->filename).'</td></tr>';
}
print '</table>';

print dol_get_fiche_end();

llxFooter();
$db->close();

