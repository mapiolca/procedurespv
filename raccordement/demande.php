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
	$equipmentSaved = false;
	$technicalDocumentsUploaded = false;
	$uploadFailed = false;
	$selectedInverters = array();
	$selectedModules = array();
	$quantities = array();
	$manualPowers = array();
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
				$technicalDocumentsUploaded = true;
				$object->{$field} = $upload['filename'];
			}
		}

		if (RaccordementEquipment::isAvailable()) {
			$postedInverters = GETPOST('inverter_products', 'array');
			$postedModules = GETPOST('module_products', 'array');
			$postedQuantities = GETPOST('equipment_qty', 'array');
			$postedManualPowers = GETPOST('equipment_power', 'array');
			$selectedInverters = is_array($postedInverters) ? $postedInverters : array();
			$selectedModules = is_array($postedModules) ? $postedModules : array();
			$quantities = is_array($postedQuantities) ? $postedQuantities : array();
			$manualPowers = is_array($postedManualPowers) ? $postedManualPowers : array();
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
	} elseif ($action === 'save' && RaccordementEquipment::isAvailable()) {
		$inverters = array();
		$modules = array();
		foreach (is_array($selectedInverters) ? $selectedInverters : array() as $productId) {
			$productId = (int) $productId;
			$inverters[$productId] = max(1, (int) ($quantities['inverter'][$productId] ?? 1));
		}
		foreach (is_array($selectedModules) ? $selectedModules : array() as $productId) {
			$productId = (int) $productId;
			$modules[$productId] = max(1, (int) ($quantities['module'][$productId] ?? 1));
		}
		$flatPowers = array();
		foreach (is_array($manualPowers) ? $manualPowers : array() as $type => $powers) {
			if (is_array($powers)) {
				foreach ($powers as $productId => $power) {
					$flatPowers[$type.'_'.((int) $productId)] = $power;
				}
			}
		}
		$result = $equipmentService->saveSelections($object, $inverters, $modules, $flatPowers, $user);
		$equipmentSaved = $result > 0;
		if ($result < 0) {
			setEventMessages($langs->trans($equipmentService->error), $equipmentService->errors, 'errors');
		} elseif ($object->triggerUserAction($user, 'equipment_changed', array('equipment')) < 0) {
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

	setEventMessages($object->error, $object->errors, 'errors');
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
$inverterLines = $equipmentService->fetchLines((int) $object->id, RaccordementEquipment::TYPE_INVERTER);
$moduleLines = $equipmentService->fetchLines((int) $object->id, RaccordementEquipment::TYPE_MODULE);
if ($collectionIsValidated && RaccordementEquipment::isAvailable()) {
	$hasHistoricalInverters = trim((string) $object->onduleurs) !== '' || (int) $object->nombre_onduleurs > 0;
	$hasHistoricalModules = trim((string) $object->modules) !== '' || (int) $object->nombre_modules > 0;
	if (empty($inverterLines) && !$hasHistoricalInverters) {
		$inverterLines = $equipmentService->fetchConfirmedPowerPlantLines($object, RaccordementEquipment::TYPE_INVERTER);
	}
	if (empty($moduleLines) && !$hasHistoricalModules) {
		$moduleLines = $equipmentService->fetchConfirmedPowerPlantLines($object, RaccordementEquipment::TYPE_MODULE);
	}
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
if (RaccordementEquipment::isAvailable()) {
	print '<tr><td>'.$langs->trans('Inverters').'</td><td><select class="flat minwidth500" multiple name="inverter_products[]" id="inverter_products">';
	foreach ($inverterLines as $line) {
		print '<option selected value="'.((int) $line['fk_product']).'" data-power="'.dol_escape_htmltag((string) $line['unit_power']).'">'.dol_escape_htmltag(trim($line['ref'].' - '.$line['label'])).'</option>';
	}
	print '</select><div id="inverter_quantity_rows" class="equipment-quantity-rows"></div>';
	if (empty($inverterLines) && trim((string) $object->onduleurs) !== '') {
		print '<div class="opacitymedium">'.$langs->trans('HistoricalEquipmentValues').' : '.nl2br(dol_escape_htmltag((string) $object->onduleurs)).'</div>';
	}
	print '</td></tr>';
	print '<tr><td>'.$langs->trans('InverterCount').'</td><td><span id="inverter_count_total">'.$displayInverterCount.'</span></td></tr>';
	print '<tr><td>'.$langs->trans('InverterPower').'</td><td><span id="inverter_power_total">'.dol_escape_htmltag((string) $displayInverterPower).'</span> kVA</td></tr>';
	print '<tr><td>'.$langs->trans('Modules').'</td><td><select class="flat minwidth500" multiple name="module_products[]" id="module_products">';
	foreach ($moduleLines as $line) {
		print '<option selected value="'.((int) $line['fk_product']).'" data-power="'.dol_escape_htmltag((string) $line['unit_power']).'">'.dol_escape_htmltag(trim($line['ref'].' - '.$line['label'])).'</option>';
	}
	print '</select><div id="module_quantity_rows" class="equipment-quantity-rows"></div>';
	if (empty($moduleLines) && trim((string) $object->modules) !== '') {
		print '<div class="opacitymedium">'.$langs->trans('HistoricalEquipmentValues').' : '.nl2br(dol_escape_htmltag((string) $object->modules)).'</div>';
	}
	print '</td></tr>';
	print '<tr><td>'.$langs->trans('ModuleCount').'</td><td><span id="module_count_total">'.$displayModuleCount.'</span></td></tr>';
	print '<tr><td>'.$langs->trans('InstalledPowerKwc').'</td><td><span id="module_power_total">'.dol_escape_htmltag((string) $displayInstalledPower).'</span> kWc</td></tr>';
} else {
	print '<tr><td>'.$langs->trans('Inverters').'</td><td>'.nl2br(dol_escape_htmltag((string) $object->onduleurs)).' <span class="opacitymedium">'.$langs->trans('EquipmentManagementUnavailable').'</span></td></tr>';
	print '<tr><td>'.$langs->trans('Modules').'</td><td>'.nl2br(dol_escape_htmltag((string) $object->modules)).'</td></tr>';
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

if (RaccordementEquipment::isAvailable()) {
	$initialEquipment = array('inverter' => $inverterLines, 'module' => $moduleLines);
	print '<script>jQuery(function($){var initial='.json_encode($initialEquipment, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).';';
	print 'function setup(type){var s=$("#"+type+"_products"),values={};(initial[type]||[]).forEach(function(l){values[String(l.fk_product)]={quantity:parseInt(l.quantity||1,10),power:parseFloat(l.unit_power||0)};});s.select2({width:"resolve",ajax:{url:'.json_encode(dol_buildpath('/procedurespv/ajax/products.php', 1)).',dataType:"json",delay:250,data:function(p){return {q:p.term||"",type:type};},processResults:function(d){return d;}},minimumInputLength:0});';
	print 'function refreshTotals(){var count=0,power=0;s.find("option:selected").each(function(){var id=String($(this).val()),v=values[id]||{quantity:1,power:0},q=Math.max(1,parseInt(v.quantity||1,10)),p=parseFloat(v.power||0);count+=q;power+=q*p;var unit=type==="inverter"?"kVA":"Wc",display=type==="inverter"?p/1000:p,total=type==="inverter"?(q*p/1000).toFixed(3):(q*p).toFixed(0);$("#"+type+"_quantity_rows .equipment-qty[data-id=\""+id+"\"]").siblings(".equipment-power").text(" — "+display+" "+unit+" / "+total+" "+unit);});$("#"+type+"_count_total").text(count);$("#"+type+"_power_total").text((power/1000).toFixed(3));}function render(){var box=$("#"+type+"_quantity_rows").empty();s.find("option:selected").each(function(){var o=$(this),id=String(o.val()),sd=o.data("data")||{},v=values[id]||{quantity:1,power:parseFloat(sd.power||o.data("power")||0)},q=Math.max(1,parseInt(v.quantity||1,10)),p=parseFloat(v.power||0);values[id]=v;var unit=type==="inverter"?"kVA":"Wc";var row=$("<div class=\"equipment-quantity-row\"></div>");row.append($("<label></label>").text('.json_encode($langs->trans('Quantity')).'+" "+o.text()+" : "));row.append($("<input type=\"number\" min=\"1\" class=\"flat width75 equipment-qty\">").attr("name","equipment_qty["+type+"]["+id+"]").attr("data-id",id).val(q));row.append($("<span class=\"equipment-power\"></span>"));if(!p){row.append($("<input type=\"text\" class=\"flat width100 equipment-manual-power\" required>").attr("name","equipment_power["+type+"]["+id+"]").attr("data-id",id).attr("placeholder",unit));}box.append(row);});refreshTotals();}s.on("change",render);$(document).on("input","#"+type+"_quantity_rows .equipment-qty",function(){values[String($(this).data("id"))].quantity=Math.max(1,parseInt($(this).val()||1,10));refreshTotals();});$(document).on("input","#"+type+"_quantity_rows .equipment-manual-power",function(){var raw=parseFloat(String($(this).val()).replace(",","."))||0;values[String($(this).data("id"))].power=type==="inverter"?raw*1000:raw;refreshTotals();});render();}setup("inverter");setup("module");});</script>';
}

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
