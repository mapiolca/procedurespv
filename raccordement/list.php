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

$langs->loadLangs(array('companies', 'projects', 'users', 'procedurespv@procedurespv'));

if (!isModEnabled('procedurespv')) {
	accessforbidden();
}

$permissiontoread = procedurespvCanDo($user, 'raccordement', 'read');
$permissiontoadd = procedurespvCanDo($user, 'raccordement', 'write');
$permissiontodelete = procedurespvCanDo($user, 'raccordement', 'delete');
if (!$permissiontoread) {
	accessforbidden();
}

$form = new Form($db);
$object = new Raccordement($db);
$hookmanager->initHooks(array('raccordementlist'));

$action = GETPOST('action', 'aZ09');
if ($action === '') {
	$action = 'list';
}
$massaction = GETPOST('massaction', 'alpha');
$confirm = GETPOST('confirm', 'alpha');
$cancel = GETPOST('cancel', 'alpha');
$toselect = GETPOST('toselect', 'array:int');
if (!is_array($toselect)) {
	$toselect = array();
}
$contextpage = GETPOST('contextpage', 'aZ09');
if ($contextpage === '') {
	$contextpage = 'procedurespvraccordementlist';
}
$optioncss = GETPOST('optioncss', 'aZ09');
$searchRef = GETPOST('search_ref', 'alphanohtml');
$searchClient = GETPOST('search_client', 'alphanohtml');
$searchSite = GETPOST('search_site', 'alphanohtml');
$searchStatus = GETPOST('search_status', 'alphanohtml');
$searchTypeExploitation = GETPOST('search_type_exploitation', 'alphanohtml');
$searchResponsible = GETPOSTINT('search_responsible');
$searchPrm = GETPOST('search_prm', 'alphanohtml');
$searchRefEnedis = GETPOST('search_ref_enedis', 'alphanohtml');
$buttonSearch = GETPOST('button_search_x', 'alpha') !== '' || GETPOST('button_search.x', 'alpha') !== '' || GETPOST('button_search', 'alpha') !== '';
$buttonRemoveFilter = GETPOST('button_removefilter_x', 'alpha') !== '' || GETPOST('button_removefilter.x', 'alpha') !== '' || GETPOST('button_removefilter', 'alpha') !== '';
$view = GETPOST('view', 'alphanohtml');
$sortfield = GETPOST('sortfield', 'aZ09comma');
$sortorder = GETPOST('sortorder', 'aZ09comma');
$page = GETPOSTISSET('pageplusone') ? GETPOSTINT('pageplusone') - 1 : GETPOSTINT('page');
$limit = GETPOSTINT('limit');

if ($buttonRemoveFilter) {
	$searchRef = '';
	$searchClient = '';
	$searchSite = '';
	$searchStatus = '';
	$searchTypeExploitation = '';
	$searchResponsible = 0;
	$searchPrm = '';
	$searchRefEnedis = '';
	$view = '';
	$toselect = array();
}

if (!in_array($sortfield, array('t.datec', 't.ref', 's.nom', 't.site_name_snapshot', 't.type_exploitation', 't.puissance_installee_kwc', 't.prm', 't.ref_enedis', 't.date_depot_enedis', 't.status', 'u.login', 't.tms', 'rnext.next_relance'), true)) {
	$sortfield = 't.datec';
}
if (!in_array(strtoupper($sortorder), array('ASC', 'DESC'), true)) {
	$sortorder = 'DESC';
}
if ($page < 0 || $buttonSearch || $buttonRemoveFilter) {
	$page = 0;
}
if ($limit <= 0) {
	$limit = getDolGlobalInt('MAIN_SIZE_LISTE_LIMIT', 25);
}
$offset = $limit * $page;

$param = '';
if ($contextpage !== '') {
	$param .= '&contextpage='.urlencode($contextpage);
}
if ($limit > 0 && $limit !== getDolGlobalInt('MAIN_SIZE_LISTE_LIMIT', 25)) {
	$param .= '&limit='.((int) $limit);
}
if ($optioncss !== '') {
	$param .= '&optioncss='.urlencode($optioncss);
}
if ($searchRef !== '') {
	$param .= '&search_ref='.urlencode($searchRef);
}
if ($searchClient !== '') {
	$param .= '&search_client='.urlencode($searchClient);
}
if ($searchSite !== '') {
	$param .= '&search_site='.urlencode($searchSite);
}
if ($searchStatus !== '') {
	$param .= '&search_status='.urlencode((string) $searchStatus);
}
if ($searchTypeExploitation !== '') {
	$param .= '&search_type_exploitation='.urlencode($searchTypeExploitation);
}
if ($searchResponsible > 0) {
	$param .= '&search_responsible='.urlencode((string) $searchResponsible);
}
if ($searchPrm !== '') {
	$param .= '&search_prm='.urlencode($searchPrm);
}
if ($searchRefEnedis !== '') {
	$param .= '&search_ref_enedis='.urlencode($searchRefEnedis);
}
$entityFilter = getEntity($object->element);
$quickFilters = array(
	'all' => array('label' => 'QuickFilterAll', 'sql' => ''),
	'mine' => array('label' => 'QuickFilterMine', 'sql' => ' AND t.fk_user_resp = '.((int) $user->id)),
	'collectes_sent_not_submitted' => array('label' => 'QuickFilterCollectesSentNotSubmitted', 'sql' => ' AND t.status IN (2, 3)'),
	'mandats_to_control' => array('label' => 'QuickFilterMandatsToControl', 'sql' => ' AND t.date_mandat_signature IS NOT NULL AND t.date_mandat_validation IS NULL'),
	'dossiers_to_complete' => array('label' => 'QuickFilterDossiersToComplete', 'sql' => ' AND t.status = 6'),
	'ready_for_deposit' => array('label' => 'QuickFilterReadyForDeposit', 'sql' => ' AND t.status = 7'),
	'deposited_enedis' => array('label' => 'QuickFilterDepositedEnedis', 'sql' => ' AND t.status = 8'),
	'instruction_enedis' => array('label' => 'QuickFilterInstructionEnedis', 'sql' => ' AND t.status = 9'),
	'complements_requested' => array('label' => 'QuickFilterComplementsRequested', 'sql' => ' AND t.status = 10'),
	'conventions_to_sign' => array('label' => 'QuickFilterConventionsToSign', 'sql' => ' AND t.status = 11'),
	'mes_to_request' => array('label' => 'QuickFilterMESToRequest', 'sql' => ' AND t.status = 13'),
	'overdue_relances' => array('label' => 'QuickFilterOverdueRelances', 'sql' => " AND EXISTS (SELECT 1 FROM ".MAIN_DB_PREFIX."pvproc_relance AS rover WHERE rover.fk_raccordement = t.rowid AND rover.entity IN (".$entityFilter.") AND rover.status = 0 AND rover.date_prevue < '".$db->idate(dol_now())."')"),
	'closed' => array('label' => 'QuickFilterClosed', 'sql' => ' AND t.status = 16'),
);
if ($view !== '' && !isset($quickFilters[$view])) {
	$view = '';
}
if ($view !== '') {
	$param .= '&view='.urlencode($view);
}

$typeOptions = array(
	'autoconsommation_totale' => 'ExploitationAutoconsommationTotale',
	'autoconsommation_surplus' => 'ExploitationAutoconsommationSurplus',
	'injection_totale' => 'ExploitationInjectionTotale',
	'autoconsommation_collective' => 'ExploitationAutoconsommationCollective',
);

/** @var array<string, array<string, int|string>> $arrayfields */
$arrayfields = array(
	't.ref' => array('label' => 'Ref', 'checked' => 1, 'enabled' => 1, 'position' => 10, 'sortfield' => 't.ref'),
	's.nom' => array('label' => 'ThirdParty', 'checked' => 1, 'enabled' => 1, 'position' => 20, 'sortfield' => 's.nom'),
	't.site_name_snapshot' => array('label' => 'Site', 'checked' => 1, 'enabled' => 1, 'position' => 30, 'sortfield' => 't.site_name_snapshot'),
	't.type_exploitation' => array('label' => 'ExploitationType', 'checked' => 1, 'enabled' => 1, 'position' => 40, 'sortfield' => 't.type_exploitation'),
	't.puissance_installee_kwc' => array('label' => 'Power', 'checked' => 1, 'enabled' => 1, 'position' => 50, 'sortfield' => 't.puissance_installee_kwc'),
	't.prm' => array('label' => 'PRM', 'checked' => 1, 'enabled' => 1, 'position' => 60, 'sortfield' => 't.prm'),
	't.ref_enedis' => array('label' => 'EnedisReference', 'checked' => 1, 'enabled' => 1, 'position' => 70, 'sortfield' => 't.ref_enedis'),
	't.date_depot_enedis' => array('label' => 'EnedisDepositDate', 'checked' => 0, 'enabled' => 1, 'position' => 80, 'sortfield' => 't.date_depot_enedis'),
	't.status' => array('label' => 'Status', 'checked' => 1, 'enabled' => 1, 'position' => 90, 'sortfield' => 't.status'),
	'u.login' => array('label' => 'Responsible', 'checked' => 1, 'enabled' => 1, 'position' => 100, 'sortfield' => 'u.login'),
	't.tms' => array('label' => 'LastAction', 'checked' => 1, 'enabled' => 1, 'position' => 110, 'sortfield' => 't.tms'),
	'rnext.next_relance' => array('label' => 'NextRelance', 'checked' => 1, 'enabled' => 1, 'position' => 120, 'sortfield' => 'rnext.next_relance'),
	'blocking_reason' => array('label' => 'BlockingReason', 'checked' => 1, 'enabled' => 1, 'position' => 130, 'sortfield' => 't.status'),
);
$arrayfields = dol_sort_array($arrayfields, 'position');

if ($cancel !== '') {
	$action = 'list';
	$massaction = '';
}
if (GETPOST('confirmmassaction', 'alpha') === '' && !in_array($massaction, array('presend', 'confirm_presend'), true)) {
	$massaction = '';
}

$parameters = array('arrayfields' => &$arrayfields);
$reshook = $hookmanager->executeHooks('doActions', $parameters, $object, $action);
if ($reshook < 0) {
	setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');
}
if (empty($reshook)) {
	include DOL_DOCUMENT_ROOT.'/core/actions_changeselectedfields.inc.php';

	if ($buttonSearch || $buttonRemoveFilter) {
		$massaction = '';
	}

	$objectclass = 'Raccordement';
	$objectlabel = 'Raccordement';
	$uploaddir = procedurespvGetModuleOutputDir((int) $conf->entity);
	if ($uploaddir === '') {
		$uploaddir = DOL_DATA_ROOT.'/procedurespv';
	}
	include DOL_DOCUMENT_ROOT.'/core/actions_massactions.inc.php';
}

$arrayofselected = $toselect;

$sqlFrom = ' FROM '.MAIN_DB_PREFIX.$object->table_element.' as t';
$sqlFrom .= ' LEFT JOIN '.MAIN_DB_PREFIX.'societe as s ON s.rowid = t.fk_soc';
$sqlFrom .= ' LEFT JOIN '.MAIN_DB_PREFIX.'user as u ON u.rowid = t.fk_user_resp';
$sqlFrom .= ' LEFT JOIN (';
$sqlFrom .= ' SELECT fk_raccordement, MIN(date_prevue) as next_relance';
$sqlFrom .= ' FROM '.MAIN_DB_PREFIX.'pvproc_relance';
$sqlFrom .= ' WHERE entity IN ('.$entityFilter.') AND status = 0';
$sqlFrom .= ' GROUP BY fk_raccordement';
$sqlFrom .= ' ) as rnext ON rnext.fk_raccordement = t.rowid';

$sqlWhere = ' WHERE t.entity IN ('.$entityFilter.')';
if ($searchRef !== '') {
	$sqlWhere .= natural_search('t.ref', $searchRef);
}
if ($searchClient !== '') {
	$sqlWhere .= natural_search('s.nom', $searchClient);
}
if ($searchSite !== '') {
	$sqlWhere .= natural_search('t.site_name_snapshot', $searchSite);
}
if ($searchStatus !== '') {
	$sqlWhere .= ' AND t.status = '.((int) $searchStatus);
}
if ($searchTypeExploitation !== '') {
	$sqlWhere .= " AND t.type_exploitation = '".$db->escape($searchTypeExploitation)."'";
}
if ($searchResponsible > 0) {
	$sqlWhere .= ' AND t.fk_user_resp = '.((int) $searchResponsible);
}
if ($searchPrm !== '') {
	$sqlWhere .= natural_search('t.prm', $searchPrm);
}
if ($searchRefEnedis !== '') {
	$sqlWhere .= natural_search('t.ref_enedis', $searchRefEnedis);
}
if ($view !== '' && $view !== 'all') {
	$sqlWhere .= $quickFilters[$view]['sql'];
}

$nbtotalofrecords = 0;
$sqlForCount = 'SELECT COUNT(DISTINCT t.rowid) as nbtotalofrecords'.$sqlFrom.$sqlWhere;
$resqlCount = $db->query($sqlForCount);
if (!$resqlCount) {
	dol_print_error($db);
	exit;
}
$objForCount = $db->fetch_object($resqlCount);
if (is_object($objForCount) && isset($objForCount->nbtotalofrecords)) {
	$nbtotalofrecords = (int) $objForCount->nbtotalofrecords;
}
$db->free($resqlCount);
if ($offset > 0 && $offset >= $nbtotalofrecords) {
	$page = 0;
	$offset = 0;
}

$sql = 'SELECT t.rowid, t.ref, t.status, t.type_exploitation, t.puissance_installee_kwc, t.puissance_injection_kva,';
$sql .= ' t.prm, t.site_name_snapshot, t.ref_enedis, t.date_depot_enedis, t.fk_user_resp, t.tms,';
$sql .= ' rnext.next_relance,';
$sql .= ' s.nom as thirdparty_name, u.login as responsible_login';
$sql .= $sqlFrom.$sqlWhere;
$sql .= $db->order($sortfield, $sortorder);
$sql .= $db->plimit($limit + 1, $offset);

$resql = $db->query($sql);
if (!$resql) {
	dol_print_error($db);
	exit;
}

$num = $resql ? $db->num_rows($resql) : 0;

llxHeader('', $langs->trans('RaccordementList'), '', '', 0, 0, '', '', '', 'classforhorizontalscrolloftabs mod-procedurespv page-raccordement-list');

$newcardbutton = '';
if ($permissiontoadd) {
	$newcardbutton = dolGetButtonTitle($langs->trans('NewRaccordement'), '', 'fa fa-plus-circle', dol_buildpath('/procedurespv/raccordement/card.php?action=create', 1), '', 1);
}

print '<div class="tabsAction">';
foreach ($quickFilters as $filterKey => $filterDefinition) {
	$filterUrl = dol_buildpath('/procedurespv/raccordement/list.php', 1).($filterKey !== 'all' ? '?view='.urlencode((string) $filterKey) : '');
	$class = ($view === $filterKey || ($view === '' && $filterKey === 'all')) ? 'butAction' : 'butActionRefused';
	print '<a class="'.$class.'" href="'.$filterUrl.'">'.$langs->trans($filterDefinition['label']).'</a>';
}
print '</div>';

$arrayofmassactions = array();
if ($permissiontodelete) {
	$arrayofmassactions['predelete'] = img_picto('', 'delete', 'class="pictofixedwidth"').$langs->trans('Delete');
}
if (GETPOSTINT('nomassaction') || in_array($massaction, array('presend', 'predelete'), true)) {
	$arrayofmassactions = array();
}
$massactionbutton = $form->selectMassAction('', $arrayofmassactions);
$checkboxOnLeft = getDolGlobalInt('MAIN_CHECKBOX_LEFT_COLUMN') > 0;

print '<form method="POST" id="searchFormList" action="'.dol_escape_htmltag($_SERVER['PHP_SELF']).'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="formfilteraction" id="formfilteraction" value="list">';
print '<input type="hidden" name="action" value="list">';
print '<input type="hidden" name="sortfield" value="'.dol_escape_htmltag($sortfield).'">';
print '<input type="hidden" name="sortorder" value="'.dol_escape_htmltag($sortorder).'">';
print '<input type="hidden" name="page" value="'.((int) $page).'">';
print '<input type="hidden" name="contextpage" value="'.dol_escape_htmltag($contextpage).'">';
print '<input type="hidden" name="view" value="'.dol_escape_htmltag($view).'">';
print '<input type="hidden" name="page_y" value="">';
if ($optioncss !== '') {
	print '<input type="hidden" name="optioncss" value="'.dol_escape_htmltag($optioncss).'">';
}

print_barre_liste($langs->trans('RaccordementList'), $page, $_SERVER['PHP_SELF'], $param, $sortfield, $sortorder, $massactionbutton, $num, $nbtotalofrecords, $object->picto, 0, $newcardbutton, '', $limit, 0, 0, 1);

$objecttmp = new Raccordement($db);
$topicmail = '';
$modelmail = '';
$trackid = 'pvproc'.$object->id;
include DOL_DOCUMENT_ROOT.'/core/tpl/massactions_pre.tpl.php';

$htmlofselectarray = $form->multiSelectArrayWithCheckbox('selectedfields', $arrayfields, $contextpage, $checkboxOnLeft ? 1 : 0);
$selectedfields = $htmlofselectarray;
if (count($arrayofmassactions) > 0) {
	$selectedfields .= $form->showCheckAddButtons('checkforselect', 1);
}
$rowSelectable = !empty($massactionbutton) || $massaction !== '';

print '<div class="div-table-responsive">';
print '<table class="tagtable nobottomiftotal liste" id="raccordement-list-table">';
print '<thead>';
print '<tr class="liste_titre_filter">';
if ($checkboxOnLeft) {
	print '<td class="liste_titre center maxwidthsearch">'.$form->showFilterButtons('left').'</td>';
}
foreach ($arrayfields as $fieldKey => $fieldDefinition) {
	if (empty($fieldDefinition['checked'])) {
		continue;
	}

	$cellClass = 'liste_titre';
	if (in_array($fieldKey, array('t.puissance_installee_kwc'), true)) {
		$cellClass .= ' right';
	} elseif (in_array($fieldKey, array('t.date_depot_enedis', 't.status', 't.tms', 'rnext.next_relance'), true)) {
		$cellClass .= ' center';
	}
	print '<td class="'.$cellClass.'">';
	switch ($fieldKey) {
		case 't.ref':
			print '<input class="flat maxwidth100" type="text" name="search_ref" value="'.dol_escape_htmltag($searchRef).'">';
			break;
		case 's.nom':
			print '<input class="flat maxwidth150" type="text" name="search_client" value="'.dol_escape_htmltag($searchClient).'">';
			break;
		case 't.site_name_snapshot':
			print '<input class="flat maxwidth150" type="text" name="search_site" value="'.dol_escape_htmltag($searchSite).'">';
			break;
		case 't.type_exploitation':
			print '<select class="flat maxwidth200" name="search_type_exploitation" id="search_type_exploitation">';
			print '<option value="">&nbsp;</option>';
			foreach ($typeOptions as $typeValue => $typeLabelKey) {
				print '<option value="'.dol_escape_htmltag($typeValue).'"'.($searchTypeExploitation === $typeValue ? ' selected' : '').'>'.$langs->trans($typeLabelKey).'</option>';
			}
			print '</select>'.ajax_combobox('search_type_exploitation');
			break;
		case 't.prm':
			print '<input class="flat maxwidth100" type="text" name="search_prm" value="'.dol_escape_htmltag($searchPrm).'">';
			break;
		case 't.ref_enedis':
			print '<input class="flat maxwidth100" type="text" name="search_ref_enedis" value="'.dol_escape_htmltag($searchRefEnedis).'">';
			break;
		case 't.status':
			print '<select class="flat maxwidth150" name="search_status" id="search_status">';
			print '<option value="">&nbsp;</option>';
			foreach (Raccordement::getStatusLabels() as $status => $labelKey) {
				$selected = ((string) $searchStatus === (string) $status) ? ' selected' : '';
				print '<option value="'.((int) $status).'"'.$selected.'>'.$langs->trans($labelKey).'</option>';
			}
			print '</select>'.ajax_combobox('search_status');
			break;
		case 'u.login':
			print $form->select_dolusers($searchResponsible > 0 ? $searchResponsible : '', 'search_responsible', 1, null, 0, '', '', '', 0, 0, '', 0, '', 'maxwidth150');
			break;
		default:
			print '&nbsp;';
			break;
	}
	print '</td>';
}
$parameters = array('arrayfields' => $arrayfields);
$reshook = $hookmanager->executeHooks('printFieldListOption', $parameters, $object, $action);
print $hookmanager->resPrint;
if (!$checkboxOnLeft) {
	print '<td class="liste_titre center maxwidthsearch">'.$form->showFilterButtons().'</td>';
}
print '</tr>';

$visibleFieldCount = 0;
print '<tr class="liste_titre">';
if ($checkboxOnLeft) {
	print getTitleFieldOfList($selectedfields, 0, $_SERVER['PHP_SELF'], '', '', '', '', $sortfield, $sortorder, 'center maxwidthsearch ');
}
foreach ($arrayfields as $fieldKey => $fieldDefinition) {
	if (empty($fieldDefinition['checked'])) {
		continue;
	}

	$cssClass = '';
	if ($fieldKey === 't.puissance_installee_kwc') {
		$cssClass = 'right';
	} elseif (in_array($fieldKey, array('t.date_depot_enedis', 't.status', 't.tms', 'rnext.next_relance'), true)) {
		$cssClass = 'center';
	}
	print_liste_field_titre($langs->trans((string) $fieldDefinition['label']), $_SERVER['PHP_SELF'], (string) $fieldDefinition['sortfield'], '', $param, $cssClass !== '' ? 'class="'.$cssClass.'"' : '', $sortfield, $sortorder);
	$visibleFieldCount++;
}
$totalarray = array('nbfield' => $visibleFieldCount);
$parameters = array('arrayfields' => $arrayfields, 'param' => $param, 'sortfield' => $sortfield, 'sortorder' => $sortorder, 'totalarray' => &$totalarray);
$reshook = $hookmanager->executeHooks('printFieldListTitle', $parameters, $object, $action);
print $hookmanager->resPrint;
if (!$checkboxOnLeft) {
	print getTitleFieldOfList($selectedfields, 0, $_SERVER['PHP_SELF'], '', '', '', '', $sortfield, $sortorder, 'center maxwidthsearch ');
}
print '</tr>';
print '</thead>';
print '<tbody>';

$i = 0;
while ($i < min($num, $limit) && is_object($obj = $db->fetch_object($resql))) {
	$objectstatic = new Raccordement($db);
	$objectstatic->id = (int) $obj->rowid;
	$objectstatic->ref = (string) $obj->ref;
	$objectstatic->status = (int) $obj->status;
	$selected = in_array((int) $obj->rowid, $arrayofselected, true);

	print '<tr data-rowid="'.((int) $obj->rowid).'" class="oddeven'.($rowSelectable ? ' row-with-select' : '').'">';
	if ($checkboxOnLeft) {
		print '<td class="nowrap center">';
		if ($rowSelectable) {
			print '<input id="cb'.((int) $obj->rowid).'" class="flat checkforselect" type="checkbox" name="toselect[]" value="'.((int) $obj->rowid).'"'.($selected ? ' checked="checked"' : '').'>';
		}
		print '</td>';
	}

	foreach ($arrayfields as $fieldKey => $fieldDefinition) {
		if (empty($fieldDefinition['checked'])) {
			continue;
		}

		switch ($fieldKey) {
			case 't.ref':
				print '<td class="nowraponall">'.$objectstatic->getNomUrl(1).'</td>';
				break;
			case 's.nom':
				print '<td>'.dol_escape_htmltag((string) $obj->thirdparty_name).'</td>';
				break;
			case 't.site_name_snapshot':
				print '<td>'.dol_escape_htmltag((string) $obj->site_name_snapshot).'</td>';
				break;
			case 't.type_exploitation':
				$typeValue = (string) $obj->type_exploitation;
				$typeLabel = isset($typeOptions[$typeValue]) ? $langs->trans($typeOptions[$typeValue]) : $typeValue;
				print '<td>'.dol_escape_htmltag($typeLabel).'</td>';
				break;
			case 't.puissance_installee_kwc':
				print '<td class="right nowraponall">'.price((float) $obj->puissance_installee_kwc).' kWc<br><span class="opacitymedium">'.price((float) $obj->puissance_injection_kva).' kVA</span></td>';
				break;
			case 't.prm':
				print '<td>'.dol_escape_htmltag((string) $obj->prm).'</td>';
				break;
			case 't.ref_enedis':
				print '<td>'.dol_escape_htmltag((string) $obj->ref_enedis).'</td>';
				break;
			case 't.date_depot_enedis':
				$depositDate = !empty($obj->date_depot_enedis) ? $db->jdate($obj->date_depot_enedis) : 0;
				print '<td class="center">'.($depositDate ? dol_print_date($depositDate, 'day') : '').'</td>';
				break;
			case 't.status':
				print '<td class="center">'.$objectstatic->getLibStatut(5).'</td>';
				break;
			case 'u.login':
				print '<td>'.dol_escape_htmltag((string) $obj->responsible_login).'</td>';
				break;
			case 't.tms':
				print '<td class="center nowraponall">'.(!empty($obj->tms) ? dol_print_date($db->jdate($obj->tms), 'dayhour') : '').'</td>';
				break;
			case 'rnext.next_relance':
				$nextRelanceDate = !empty($obj->next_relance) ? $db->jdate($obj->next_relance) : 0;
				print '<td class="center nowraponall">'.($nextRelanceDate ? ($nextRelanceDate < dol_now() ? img_warning($langs->trans('RelanceOverdue')).' ' : '').dol_print_date($nextRelanceDate, 'day') : '').'</td>';
				break;
			case 'blocking_reason':
				print '<td>'.$langs->trans($objectstatic->getBlockingReason()).'</td>';
				break;
		}
	}

	$parameters = array('arrayfields' => $arrayfields, 'object' => $objectstatic, 'obj' => $obj, 'i' => $i, 'totalarray' => &$totalarray);
	$reshook = $hookmanager->executeHooks('printFieldListValue', $parameters, $objectstatic, $action);
	print $hookmanager->resPrint;

	if (!$checkboxOnLeft) {
		print '<td class="nowrap center">';
		if ($rowSelectable) {
			print '<input id="cb'.((int) $obj->rowid).'" class="flat checkforselect" type="checkbox" name="toselect[]" value="'.((int) $obj->rowid).'"'.($selected ? ' checked="checked"' : '').'>';
		}
		print '</td>';
	}
	print '</tr>';
	$i++;
}

if ($num === 0) {
	$colspan = ((int) $totalarray['nbfield']) + 1;
	print '<tr class="oddeven"><td colspan="'.$colspan.'"><span class="opacitymedium">'.$langs->trans('NoRecordFound').'</span></td></tr>';
}
$parameters = array('arrayfields' => $arrayfields, 'sql' => $sql);
$reshook = $hookmanager->executeHooks('printFieldListFooter', $parameters, $object, $action);
print $hookmanager->resPrint;
print '</tbody>';

$db->free($resql);
print '</table>';
print '</div>';
print '</form>';

llxFooter();
$db->close();
