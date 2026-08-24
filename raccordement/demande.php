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
require_once dol_buildpath('/procedurespv/class/raccordementequipment.class.php', 0);
require_once dol_buildpath('/procedurespv/class/centralepvadapter.class.php', 0);
require_once dol_buildpath('/procedurespv/class/raccordementworkflow.class.php', 0);
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
$workflow = new RaccordementWorkflow($db);

$sensitiveActions = array('save', 'mark_complete', 'freeze_snapshot', 'mark_deposited', 'mark_complement', 'mark_instruction');
if (in_array($action, $sensitiveActions, true) && (!GETPOST('token', 'alpha') || (function_exists('checkToken') && !checkToken()))) {
	accessforbidden($langs->trans('ErrorBadToken'));
}

if (in_array($action, $sensitiveActions, true)) {
	if (!$permissiontowrite) {
		accessforbidden();
	}

	$equipmentService = new RaccordementEquipment($db);
	$uploadFailed = false;
	$localEquipmentValues = array(
		'onduleurs' => (string) $object->onduleurs,
		'nombre_onduleurs' => (int) $object->nombre_onduleurs,
		'puissance_onduleurs' => (string) $object->puissance_onduleurs,
		'modules' => (string) $object->modules,
		'nombre_modules' => (int) $object->nombre_modules,
		'puissance_installee_kwc' => (string) $object->puissance_installee_kwc,
	);
	if ($action === 'save') {
		$object->ref_enedis = GETPOST('ref_enedis', 'alphanohtml');
		$object->date_depot_enedis = procedurespvReadDateTimeFromPost('date_depot_enedis');
		$object->portail_utilise = GETPOST('portail_utilise', 'alphanohtml');
		$object->puissance_raccordement_demandee = (float) price2num(GETPOST('puissance_raccordement_demandee', 'alphanohtml'));
		$object->type_reseau = GETPOST('type_reseau', 'alphanohtml');
		$object->mono_tri_confirme = GETPOST('mono_tri_confirme', 'alphanohtml');
		$object->commentaire_technique = GETPOST('commentaire_technique', 'restricthtml');

		foreach (array('schema_unifilaire' => 'SingleLineDiagram', 'plan_masse' => 'SitePlan', 'plan_cadastral' => 'CadastralPlan', 'bilan_puissance' => 'PowerBalance') as $field => $labelKey) {
			$upload = procedurespvStoreRaccordementUpload($field, $object, $field, $langs->transnoentitiesnoconv($labelKey), 'internal_request');
			if ($upload['result'] < 0) {
				$uploadFailed = true;
				setEventMessages($langs->trans($upload['error']), null, 'errors');
			} elseif ($upload['result'] > 0) {
				$object->{$field} = $upload['filename'];
			}
		}

		if ((int) $object->fk_centrale_pv <= 0) {
			$localEquipmentValues = array(
				'onduleurs' => GETPOST('onduleurs', 'restricthtml'),
				'nombre_onduleurs' => GETPOSTINT('nombre_onduleurs'),
				'puissance_onduleurs' => GETPOST('puissance_onduleurs', 'alphanohtml'),
				'modules' => GETPOST('modules', 'restricthtml'),
				'nombre_modules' => GETPOSTINT('nombre_modules'),
				'puissance_installee_kwc' => GETPOST('puissance_installee_kwc', 'alphanohtml'),
			);
		}
	}

	if ($action === 'mark_complete') {
		if ((int) $object->demande_status !== 0) {
			accessforbidden($langs->trans('InvalidStatusTransition'));
		}
		$object->demande_status = 1;
	}
	if ($action === 'freeze_snapshot') {
		if (!$permissiontofreeze) {
			accessforbidden();
		}
		if (!empty($object->date_snapshot)) {
			accessforbidden($langs->trans('SnapshotAlreadyFrozen'));
		}
		$object->date_snapshot = dol_now();
	}
	if ($action === 'mark_deposited') {
		if ((int) $object->demande_status >= 2) {
			accessforbidden($langs->trans('InvalidStatusTransition'));
		}
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
		if ((int) $object->demande_status < 2 || (int) $object->demande_status === 3) {
			accessforbidden($langs->trans('InvalidStatusTransition'));
		}
		$object->demande_status = 3;
		$object->status = 10;
	}
	if ($action === 'mark_instruction') {
		if ((int) $object->demande_status < 2 || (int) $object->demande_status === 4) {
			accessforbidden($langs->trans('InvalidStatusTransition'));
		}
		$object->demande_status = 4;
		$object->status = 9;
	}
	$object->status = $workflow->getReconciledStatus($object);

	$object->context['trigger_reason'] = 'enedis_request_update';
	$object->context['changed_fields'] = array('ref_enedis', 'date_depot_enedis', 'demande_status', 'status');
	if ($uploadFailed) {
		$result = -1;
	} elseif ($action === 'save' && (int) $object->fk_centrale_pv <= 0) {
		$result = $equipmentService->saveLocalValues($object, $localEquipmentValues, $user);
		if ($result < 0) {
			setEventMessages($langs->trans($equipmentService->error), $equipmentService->errors, 'errors');
		} elseif ($object->triggerUserAction($user, 'enedis_request_update', array_values(array_unique(array_merge(
			array('ref_enedis', 'date_depot_enedis', 'portail_utilise', 'puissance_raccordement_demandee', 'type_reseau', 'mono_tri_confirme', 'commentaire_technique'),
			$equipmentService->changedFields
		)))) < 0) {
			$result = -1;
			setEventMessages($object->error, $object->errors, 'errors');
		}
	} elseif ($action === 'save' && (int) $object->fk_centrale_pv > 0 && !empty($object->date_collecte_soumission)) {
		$result = $equipmentService->prefillFromConfirmedPowerPlant($object, $user, true);
		if ($result < 0) {
			setEventMessages($langs->trans($equipmentService->error), $equipmentService->errors, 'errors');
		} elseif ($result === 0 && $object->update($user, 1) < 0) {
			$result = -1;
		} elseif ($object->triggerUserAction($user, 'enedis_request_update', array_values(array_unique(array_merge(
			array('ref_enedis', 'date_depot_enedis', 'portail_utilise', 'puissance_raccordement_demandee', 'type_reseau', 'mono_tri_confirme', 'commentaire_technique'),
			$equipmentService->changedFields
		)))) < 0) {
			$result = -1;
			setEventMessages($object->error, $object->errors, 'errors');
		}
	} else {
		$result = $object->update($user);
	}
	if ($result > 0) {
		setEventMessages($langs->trans('RecordSaved'), null, 'mesgs');
		header('Location: '.dol_buildpath('/procedurespv/raccordement/demande.php', 1).'?id='.(int) $object->id);
		exit;
	}

	if ($object->error !== '' || !empty($object->errors)) {
		setEventMessages($object->error, $object->errors, 'errors');
	}
}

$form = new Form($db);
$pieceFetcher = new Piece($db);
$pieces = $pieceFetcher->fetchAllByRaccordement((int) $object->id);
$signature = new Signature($db);
$signature->fetchLatestForRaccordement((int) $object->id, Signature::TYPE_MANDAT_ENEDIS);
$centraleAdapter = new CentralePVAdapter($db);
$collectionIsValidated = (int) $object->status >= 6;
if ($collectionIsValidated) {
	// Compatibility fallback for records validated before persistent prefill was introduced.
	// Once the request contains a value, later internal edits must remain authoritative.
	$centraleAdapter->prefillRaccordementRequestData($object);
}
$equipmentService = new RaccordementEquipment($db);
$usesLinkedPowerPlant = (int) $object->fk_centrale_pv > 0;
$inverterLines = $equipmentService->fetchLines((int) $object->id, RaccordementEquipment::TYPE_INVERTER);
$moduleLines = $equipmentService->fetchLines((int) $object->id, RaccordementEquipment::TYPE_MODULE);
if ($collectionIsValidated && $usesLinkedPowerPlant && isModEnabled('powerplantpv')) {
	$hasHistoricalInverters = trim((string) $object->onduleurs) !== '' || (int) $object->nombre_onduleurs > 0;
	$hasHistoricalModules = trim((string) $object->modules) !== '' || (int) $object->nombre_modules > 0;
	if (empty($inverterLines) && !$hasHistoricalInverters) {
		$inverterLines = $equipmentService->fetchConfirmedPowerPlantLines($object, RaccordementEquipment::TYPE_INVERTER);
	}
	if (empty($moduleLines) && !$hasHistoricalModules) {
		$moduleLines = $equipmentService->fetchConfirmedPowerPlantLines($object, RaccordementEquipment::TYPE_MODULE);
	}
}

$displayInverterNames = trim((string) $object->onduleurs);
if ($displayInverterNames === '' && !empty($inverterLines)) {
	$displayInverterLabels = array();
	foreach ($inverterLines as $inverterLine) {
		$displayInverterLabels[] = trim((string) $inverterLine['ref'].' - '.(string) $inverterLine['label']).' × '.((int) $inverterLine['quantity']);
	}
	$displayInverterNames = implode("\n", $displayInverterLabels);
}

$displayInverterCount = (int) $object->nombre_onduleurs;
$displayInverterPower = price2num((string) $object->puissance_onduleurs, 'MU');
if (!empty($inverterLines)) {
	$displayInverterCount = 0;
	$displayInverterPowerVa = 0.0;
	foreach ($inverterLines as $inverterLine) {
		$displayInverterCount += (int) $inverterLine['quantity'];
		$displayInverterPowerVa += (int) $inverterLine['quantity'] * (float) $inverterLine['unit_power'];
	}
	$displayInverterPower = price2num($displayInverterPowerVa / 1000, 'MU');
}

$displayModuleCount = (int) $object->nombre_modules;
$displayInstalledPower = price2num((string) $object->puissance_installee_kwc, 'MU');
if (!empty($moduleLines)) {
	$displayModuleCount = 0;
	$displayModulePowerWc = 0.0;
	foreach ($moduleLines as $moduleLine) {
		$displayModuleCount += (int) $moduleLine['quantity'];
		$displayModulePowerWc += (int) $moduleLine['quantity'] * (float) $moduleLine['unit_power'];
	}
	$displayInstalledPower = price2num($displayModulePowerWc / 1000, 'MU');
}
$displayModuleNames = trim((string) $object->modules);
if ($displayModuleNames === '' && !empty($moduleLines)) {
	$displayModuleLabels = array();
	foreach ($moduleLines as $moduleLine) {
		$displayModuleLabels[] = trim((string) $moduleLine['ref'].' - '.(string) $moduleLine['label']).' × '.((int) $moduleLine['quantity']);
	}
	$displayModuleNames = implode("\n", $displayModuleLabels);
}

llxHeader('', $langs->trans('DemandeRaccordement'), '', '', 0, 0, '', '', '', 'mod-procedurespv page-raccordement-demande');

$head = procedurespvRaccordementPrepareHead($object);
print dol_get_fiche_head($head, 'demande', $langs->trans('Raccordement'), -1, $object->picto);

$linkback = '<a href="'.dol_buildpath('/procedurespv/raccordement/list.php', 1).'">'.$langs->trans('BackToList').'</a>';
dol_banner_tab($object, 'ref', $linkback, 1, 'ref', 'ref');

print '<form method="POST" enctype="multipart/form-data" action="'.dol_escape_htmltag($_SERVER['PHP_SELF']).'?id='.(int) $object->id.'">';
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
if ($usesLinkedPowerPlant) {
	print '<tr><td>'.$langs->trans('Inverters').'</td><td>'.($displayInverterNames !== '' ? nl2br(dol_escape_htmltag($displayInverterNames)) : '<span class="opacitymedium">'.$langs->trans('NoRecordFound').'</span>').'<br><span class="opacitymedium">'.$langs->trans('EquipmentDerivedFromLinkedPowerPlant').'</span></td></tr>';
	print '<tr><td>'.$langs->trans('InverterCount').'</td><td>'.$displayInverterCount.'</td></tr>';
	print '<tr><td>'.$langs->trans('InverterPower').'</td><td>'.dol_escape_htmltag((string) $displayInverterPower).' kVA</td></tr>';
	print '<tr><td>'.$langs->trans('Modules').'</td><td>'.($displayModuleNames !== '' ? nl2br(dol_escape_htmltag($displayModuleNames)) : '<span class="opacitymedium">'.$langs->trans('NoRecordFound').'</span>').'</td></tr>';
	print '<tr><td>'.$langs->trans('ModuleCount').'</td><td>'.$displayModuleCount.'</td></tr>';
	print '<tr><td>'.$langs->trans('InstalledPowerKwc').'</td><td>'.dol_escape_htmltag((string) $displayInstalledPower).' kWc</td></tr>';
} else {
	print '<tr><td>'.$langs->trans('Inverters').'</td><td><input type="text" class="flat minwidth500" name="onduleurs" value="'.dol_escape_htmltag((string) $object->onduleurs).'"><br><span class="opacitymedium">'.$langs->trans('LocalEquipmentManualEntry').'</span></td></tr>';
	print '<tr><td>'.$langs->trans('InverterCount').'</td><td><input type="number" min="0" class="flat width100" name="nombre_onduleurs" value="'.((int) $object->nombre_onduleurs).'"></td></tr>';
	print '<tr><td>'.$langs->trans('InverterPower').'</td><td><input type="text" class="flat width100 right" name="puissance_onduleurs" value="'.dol_escape_htmltag((string) $object->puissance_onduleurs).'"> kVA</td></tr>';
	print '<tr><td>'.$langs->trans('Modules').'</td><td><input type="text" class="flat minwidth500" name="modules" value="'.dol_escape_htmltag((string) $object->modules).'"></td></tr>';
	print '<tr><td>'.$langs->trans('ModuleCount').'</td><td><input type="number" min="0" class="flat width100" name="nombre_modules" value="'.((int) $object->nombre_modules).'"></td></tr>';
	print '<tr><td>'.$langs->trans('InstalledPowerKwc').'</td><td><input type="text" class="flat width100 right" name="puissance_installee_kwc" value="'.dol_escape_htmltag((string) $object->puissance_installee_kwc).'"> kWc</td></tr>';
}
foreach (array('schema_unifilaire' => 'SingleLineDiagram', 'plan_masse' => 'SitePlan', 'plan_cadastral' => 'CadastralPlan', 'bilan_puissance' => 'PowerBalance') as $field => $labelKey) {
	print '<tr><td>'.$langs->trans($labelKey).'</td><td><input type="file" class="flat" name="'.$field.'">';
	if (!empty($object->{$field})) {
		print ' '.procedurespvRenderRaccordementDocumentLink($object, (string) $object->{$field});
	}
	print '</td></tr>';
}
print '<tr><td>'.$langs->trans('TechnicalComment').'</td><td><textarea class="flat centpercent" name="commentaire_technique" rows="3">'.dol_escape_htmltag((string) $object->commentaire_technique).'</textarea></td></tr>';
print '</table>';

print '<div class="center"><input type="submit" class="button button-save" value="'.$langs->trans('Save').'"></div>';
print '</form>';

print '<div class="tabsAction">';
if ($permissiontowrite) {
	$baseUrl = dol_buildpath('/procedurespv/raccordement/demande.php', 1).'?id='.(int) $object->id;
	$token = newToken();
	if ((int) $object->demande_status === 0) {
		print '<a class="butAction" href="'.$baseUrl.'&action=mark_complete&token='.$token.'">'.$langs->trans('MarkRequestComplete').'</a>';
	}
	if ($permissiontofreeze && empty($object->date_snapshot)) {
		print '<a class="butAction" href="'.$baseUrl.'&action=freeze_snapshot&token='.$token.'">'.$langs->trans('FreezeSnapshot').'</a>';
	}
	if ((int) $object->demande_status < 2) {
		print '<a class="butAction" href="'.$baseUrl.'&action=mark_deposited&token='.$token.'">'.$langs->trans('MarkDepositedEnedis').'</a>';
	}
	if ((int) $object->demande_status >= 2 && (int) $object->demande_status !== 3) {
		print '<a class="butAction" href="'.$baseUrl.'&action=mark_complement&token='.$token.'">'.$langs->trans('MarkComplementRequested').'</a>';
	}
	if ((int) $object->demande_status >= 2 && (int) $object->demande_status !== 4) {
		print '<a class="butAction" href="'.$baseUrl.'&action=mark_instruction&token='.$token.'">'.$langs->trans('MarkInstructionEnedis').'</a>';
	}
}
print '</div>';

print '<br>';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><td>'.$langs->trans('UsefulPieces').'</td><td class="center">'.$langs->trans('Status').'</td><td>'.$langs->trans('File').'</td></tr>';
if (!empty($pieces)) {
	foreach ($pieces as $piece) {
		print '<tr class="oddeven"><td>'.dol_escape_htmltag((string) $piece->label).'</td><td class="center">'.$piece->getLibStatut(5).'</td><td>'.procedurespvRenderRaccordementDocumentLink($object, (string) $piece->filename).'</td></tr>';
	}
} else {
	print '<tr class="oddeven"><td colspan="3"><span class="opacitymedium">'.$langs->trans('NoRecordFound').'</span></td></tr>';
}
if ((int) $signature->id > 0) {
	print '<tr class="oddeven"><td>'.$langs->trans('MandatEnedis').'</td><td class="center">'.$signature->getLibStatut(5).'</td><td>'.procedurespvRenderRaccordementDocumentLink($object, (string) $signature->filename).'</td></tr>';
}
print '</table>';

print dol_get_fiche_end();

llxFooter();
$db->close();
