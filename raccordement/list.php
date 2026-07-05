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
if (!$permissiontoread) {
	accessforbidden();
}

$form = new Form($db);
$object = new Raccordement($db);

$searchRef = GETPOST('search_ref', 'alphanohtml');
$searchClient = GETPOST('search_client', 'alphanohtml');
$searchStatus = GETPOST('search_status', 'alphanohtml');
$searchTypeExploitation = GETPOST('search_type_exploitation', 'alphanohtml');
$searchResponsible = GETPOSTINT('search_responsible');
$searchRefEnedis = GETPOST('search_ref_enedis', 'alphanohtml');
$buttonRemoveFilter = GETPOST('button_removefilter', 'alpha');
$view = GETPOST('view', 'alphanohtml');
$sortfield = GETPOST('sortfield', 'aZ09comma');
$sortorder = GETPOST('sortorder', 'aZ09comma');
$page = GETPOSTINT('page');
$limit = GETPOSTINT('limit');

if ($buttonRemoveFilter !== '') {
	$searchRef = '';
	$searchClient = '';
	$searchStatus = '';
	$searchTypeExploitation = '';
	$searchResponsible = 0;
	$searchRefEnedis = '';
}

if ($sortfield === '') {
	$sortfield = 't.datec';
}
if ($sortorder === '') {
	$sortorder = 'DESC';
}
if ($page < 0) {
	$page = 0;
}
if ($limit <= 0) {
	$limit = getDolGlobalInt('MAIN_SIZE_LISTE_LIMIT', 25);
}
$offset = $limit * $page;

$param = '';
if ($searchRef !== '') {
	$param .= '&search_ref='.urlencode($searchRef);
}
if ($searchClient !== '') {
	$param .= '&search_client='.urlencode($searchClient);
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
if ($searchRefEnedis !== '') {
	$param .= '&search_ref_enedis='.urlencode($searchRefEnedis);
}
if ($view !== '') {
	$param .= '&view='.urlencode($view);
}

$entityFilter = function_exists('getEntity') ? getEntity($object->element) : (string) ((int) $conf->entity);
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

$sql = 'SELECT t.rowid, t.ref, t.status, t.type_exploitation, t.puissance_installee_kwc, t.puissance_injection_kva,';
$sql .= ' t.prm, t.site_name_snapshot, t.ref_enedis, t.date_depot_enedis, t.fk_user_resp, t.tms,';
$sql .= ' rnext.next_relance,';
$sql .= ' s.nom as thirdparty_name, u.login as responsible_login';
$sql .= ' FROM '.MAIN_DB_PREFIX.$object->table_element.' as t';
$sql .= ' LEFT JOIN '.MAIN_DB_PREFIX.'societe as s ON s.rowid = t.fk_soc';
$sql .= ' LEFT JOIN '.MAIN_DB_PREFIX.'user as u ON u.rowid = t.fk_user_resp';
$sql .= ' LEFT JOIN (';
$sql .= ' SELECT fk_raccordement, MIN(date_prevue) as next_relance';
$sql .= ' FROM '.MAIN_DB_PREFIX.'pvproc_relance';
$sql .= ' WHERE entity IN ('.$entityFilter.') AND status = 0';
$sql .= ' GROUP BY fk_raccordement';
$sql .= ' ) as rnext ON rnext.fk_raccordement = t.rowid';
$sql .= ' WHERE t.entity IN ('.$entityFilter.')';
if ($searchRef !== '') {
	$sql .= natural_search('t.ref', $searchRef);
}
if ($searchClient !== '') {
	$sql .= natural_search('s.nom', $searchClient);
}
if ($searchStatus !== '') {
	$sql .= ' AND t.status = '.((int) $searchStatus);
}
if ($searchTypeExploitation !== '') {
	$sql .= " AND t.type_exploitation = '".$db->escape($searchTypeExploitation)."'";
}
if ($searchResponsible > 0) {
	$sql .= ' AND t.fk_user_resp = '.((int) $searchResponsible);
}
if ($searchRefEnedis !== '') {
	$sql .= natural_search('t.ref_enedis', $searchRefEnedis);
}
if ($view !== '' && $view !== 'all') {
	$sql .= $quickFilters[$view]['sql'];
}
$sql .= $db->order($sortfield, $sortorder);
$sql .= $db->plimit($limit + 1, $offset);

$resql = $db->query($sql);
if (!$resql) {
	dol_print_error($db);
}

$num = $resql ? $db->num_rows($resql) : 0;
$arrayofselected = array();

llxHeader('', $langs->trans('RaccordementList'), '', '', 0, 0, '', '', '', 'mod-procedurespv page-raccordement-list');

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

print '<form method="GET" action="'.dol_escape_htmltag($_SERVER['PHP_SELF']).'">';
print '<input type="hidden" name="view" value="'.dol_escape_htmltag($view).'">';
print_barre_liste($langs->trans('RaccordementList'), $page, $_SERVER['PHP_SELF'], $param, $sortfield, $sortorder, '', $num, $num, $object->picto, 0, $newcardbutton, '', $limit);

print '<div class="div-table-responsive">';
print '<table class="tagtable liste centpercent">';
print '<tr class="liste_titre_filter">';
print '<td><input class="flat maxwidth100" type="text" name="search_ref" value="'.dol_escape_htmltag($searchRef).'"></td>';
print '<td><input class="flat maxwidth150" type="text" name="search_client" value="'.dol_escape_htmltag($searchClient).'"></td>';
print '<td></td>';
print '<td>';
print '<select class="flat maxwidth200" name="search_type_exploitation" id="search_type_exploitation">';
print '<option value="">&nbsp;</option>';
$typeOptions = array(
	'autoconsommation_totale' => 'ExploitationAutoconsommationTotale',
	'autoconsommation_surplus' => 'ExploitationAutoconsommationSurplus',
	'injection_totale' => 'ExploitationInjectionTotale',
	'autoconsommation_collective' => 'ExploitationAutoconsommationCollective',
);
foreach ($typeOptions as $typeValue => $typeLabelKey) {
	print '<option value="'.dol_escape_htmltag($typeValue).'"'.($searchTypeExploitation === $typeValue ? ' selected' : '').'>'.$langs->trans($typeLabelKey).'</option>';
}
print '</select>';
print ajax_combobox('search_type_exploitation');
print '</td>';
print '<td></td>';
print '<td></td>';
print '<td><input class="flat maxwidth100" type="text" name="search_ref_enedis" value="'.dol_escape_htmltag($searchRefEnedis).'"></td>';
print '<td></td>';
print '<td>';
print '<select class="flat maxwidth150" name="search_status" id="search_status">';
print '<option value="">&nbsp;</option>';
foreach (Raccordement::getStatusLabels() as $status => $labelKey) {
	$selected = ((string) $searchStatus === (string) $status) ? ' selected' : '';
	print '<option value="'.((int) $status).'"'.$selected.'>'.$langs->trans($labelKey).'</option>';
}
print '</select>';
print ajax_combobox('search_status');
print '</td>';
print '<td><input class="flat width75" type="number" name="search_responsible" value="'.($searchResponsible > 0 ? (int) $searchResponsible : '').'"></td>';
print '<td></td>';
print '<td></td>';
print '<td></td>';
print '<td class="center">';
print '<button type="submit" class="liste_titre button_search" name="button_search" value="x">';
print img_picto($langs->trans('Search'), 'search');
print '</button>';
print '<button type="submit" class="liste_titre button_removefilter" name="button_removefilter" value="x">';
print img_picto($langs->trans('RemoveFilter'), 'searchclear');
print '</button>';
print '</td>';
print '</tr>';

print '<tr class="liste_titre">';
print_liste_field_titre($langs->trans('Ref'), $_SERVER['PHP_SELF'], 't.ref', '', $param, '', $sortfield, $sortorder);
print_liste_field_titre($langs->trans('ThirdParty'), $_SERVER['PHP_SELF'], 's.nom', '', $param, '', $sortfield, $sortorder);
print_liste_field_titre($langs->trans('Site'), $_SERVER['PHP_SELF'], 't.site_name_snapshot', '', $param, '', $sortfield, $sortorder);
print_liste_field_titre($langs->trans('ExploitationType'), $_SERVER['PHP_SELF'], 't.type_exploitation', '', $param, '', $sortfield, $sortorder);
print_liste_field_titre($langs->trans('Power'), $_SERVER['PHP_SELF'], 't.puissance_installee_kwc', '', $param, 'class="right"', $sortfield, $sortorder);
print_liste_field_titre($langs->trans('PRM'), $_SERVER['PHP_SELF'], 't.prm', '', $param, '', $sortfield, $sortorder);
print_liste_field_titre($langs->trans('EnedisReference'), $_SERVER['PHP_SELF'], 't.ref_enedis', '', $param, '', $sortfield, $sortorder);
print_liste_field_titre($langs->trans('Status'), $_SERVER['PHP_SELF'], 't.status', '', $param, 'class="center"', $sortfield, $sortorder);
print_liste_field_titre($langs->trans('Responsible'), $_SERVER['PHP_SELF'], 'u.login', '', $param, '', $sortfield, $sortorder);
print_liste_field_titre($langs->trans('LastAction'), $_SERVER['PHP_SELF'], 't.tms', '', $param, '', $sortfield, $sortorder);
print_liste_field_titre($langs->trans('NextRelance'), $_SERVER['PHP_SELF'], 'rnext.next_relance', '', $param, '', $sortfield, $sortorder);
print_liste_field_titre($langs->trans('BlockingReason'), $_SERVER['PHP_SELF'], 't.status', '', $param, '', $sortfield, $sortorder);
print '<th class="center">'.$langs->trans('Action').'</th>';
print '</tr>';

$i = 0;
if ($resql) {
	while ($i < min($num, $limit) && is_object($obj = $db->fetch_object($resql))) {
		$objectstatic = new Raccordement($db);
		$objectstatic->id = (int) $obj->rowid;
		$objectstatic->ref = (string) $obj->ref;
		$objectstatic->status = (int) $obj->status;

		print '<tr class="oddeven">';
		print '<td>'.$objectstatic->getNomUrl(1).'</td>';
		print '<td>'.dol_escape_htmltag((string) $obj->thirdparty_name).'</td>';
		print '<td>'.dol_escape_htmltag((string) $obj->site_name_snapshot).'</td>';
		print '<td>'.dol_escape_htmltag((string) $obj->type_exploitation).'</td>';
		print '<td class="right">'.price((float) $obj->puissance_installee_kwc).' kWc<br><span class="opacitymedium">'.price((float) $obj->puissance_injection_kva).' kVA</span></td>';
		print '<td>'.dol_escape_htmltag((string) $obj->prm).'</td>';
		print '<td>'.dol_escape_htmltag((string) $obj->ref_enedis).'</td>';
		print '<td class="center">'.$objectstatic->getLibStatut(5).'</td>';
		print '<td>'.dol_escape_htmltag((string) $obj->responsible_login).'</td>';
		print '<td>'.(!empty($obj->tms) ? dol_print_date($db->jdate($obj->tms), 'dayhour') : '').'</td>';
		$nextRelanceDate = !empty($obj->next_relance) ? $db->jdate($obj->next_relance) : 0;
		print '<td>'.($nextRelanceDate ? ($nextRelanceDate < dol_now() ? img_warning($langs->trans('RelanceOverdue')).' ' : '').dol_print_date($nextRelanceDate, 'day') : '').'</td>';
		print '<td>'.$langs->trans($objectstatic->getBlockingReason()).'</td>';
		print '<td class="center"><a class="button small" href="'.dol_buildpath('/procedurespv/raccordement/card.php', 1).'?id='.(int) $obj->rowid.'">'.$langs->trans('Open').'</a></td>';
		print '</tr>';

		$i++;
	}
}

if ($num === 0) {
	print '<tr class="oddeven"><td colspan="13"><span class="opacitymedium">'.$langs->trans('NoRecordFound').'</span></td></tr>';
}

print '</table>';
print '</div>';
print '</form>';

llxFooter();
$db->close();
