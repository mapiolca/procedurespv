<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * Native events/agenda tab for a raccordement.
 */

require '../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/company.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/functions2.lib.php';
require_once dol_buildpath('/procedurespv/class/raccordement.class.php', 0);
require_once dol_buildpath('/procedurespv/lib/procedurespv.lib.php', 0);

/**
 * @var Conf $conf
 * @var DoliDB $db
 * @var HookManager $hookmanager
 * @var Translate $langs
 * @var User $user
 */

$langs->loadLangs(array('agenda', 'procedurespv@procedurespv'));

$id = GETPOSTINT('id');
$ref = GETPOST('ref', 'alpha');
$action = GETPOST('action', 'aZ09');
$contextpage = GETPOST('contextpage', 'aZ09');

$limit = GETPOSTINT('limit') > 0 ? GETPOSTINT('limit') : (int) $conf->liste_limit;
$sortfield = GETPOST('sortfield', 'aZ09comma');
$sortorder = GETPOST('sortorder', 'aZ09comma');
if ($sortfield === '') {
	$sortfield = 'a.datep,a.id';
}
if ($sortorder === '') {
	$sortorder = 'DESC';
}
$page = GETPOSTISSET('pageplusone') ? GETPOSTINT('pageplusone') - 1 : GETPOSTINT('page');
if ($page < 0) {
	$page = 0;
}

if (GETPOSTISARRAY('actioncode')) {
	$actioncode = GETPOST('actioncode', 'array:alpha', 3);
	if (count($actioncode) === 0) {
		$actioncode = '0';
	}
} else {
	$requestedActionCode = GETPOST('actioncode', 'alpha', 3);
	$actioncode = $requestedActionCode !== '' ? $requestedActionCode : (GETPOST('actioncode') === '0' ? '0' : getDolGlobalString('AGENDA_DEFAULT_FILTER_TYPE_FOR_OBJECT'));
}

$searchRowid = GETPOST('search_rowid', 'alphanohtml');
$searchAgendaLabel = GETPOST('search_agenda_label', 'restricthtml');
$searchComplete = GETPOST('search_complete', 'alphanohtml');
$searchFiltert = GETPOSTINT('search_filtert');
$searchDateEventStart = GETPOSTDATE('dateevent_start');
$searchDateEventEnd = GETPOSTDATE('dateevent_end');

if (!isModEnabled('procedurespv') || !isModEnabled('agenda')) {
	accessforbidden();
}

$object = new Raccordement($db);
$result = $object->fetch($id, $ref);
if ($result <= 0) {
	accessforbidden($langs->trans('ErrorRecordNotFound'));
}
if (!procedurespvCanDo($user, 'raccordement', 'read')) {
	accessforbidden();
}
if (empty($user->admin) && !$user->hasRight('agenda', 'myactions', 'read') && !$user->hasRight('agenda', 'allactions', 'read')) {
	accessforbidden();
}
if (!empty($user->socid) && (int) $object->fk_soc !== (int) $user->socid) {
	accessforbidden();
}

$hookmanager->initHooks(array('procedurespvraccordementagenda', 'globalcard'));
$parameters = array('id' => (int) $object->id);
$reshook = $hookmanager->executeHooks('doActions', $parameters, $object, $action);
if ($reshook < 0) {
	setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');
}

if (GETPOST('button_removefilter_x', 'alpha') || GETPOST('button_removefilter.x', 'alpha') || GETPOST('button_removefilter', 'alpha')) {
	$actioncode = '';
	$searchRowid = '';
	$searchAgendaLabel = '';
	$searchComplete = '';
	$searchFiltert = 0;
	$searchDateEventStart = '';
	$searchDateEventEnd = '';
}

$form = new Form($db);
$title = $langs->trans('EventsAgenda').' - '.(string) $object->ref;
llxHeader('', $title, '', '', 0, 0, '', '', '', 'mod-procedurespv page-raccordement-agenda');

$head = procedurespvRaccordementPrepareHead($object);
print dol_get_fiche_head($head, 'agenda', $langs->trans('Raccordement'), -1, $object->picto);

$linkback = '<a href="'.dol_buildpath('/procedurespv/raccordement/list.php', 1).'?restore_lastsearch_values=1">'.$langs->trans('BackToList').'</a>';
dol_banner_tab($object, 'ref', $linkback, 1, 'ref', 'ref');

print '<div class="fichecenter">';
print '<div class="underbanner clearboth"></div>';
dol_print_object_info($object, 1);
print '</div>';
print '<div class="clearboth"></div>';
print dol_get_fiche_end();

$param = '&id='.(int) $object->id;
if ($contextpage !== '' && $contextpage !== $_SERVER['PHP_SELF']) {
	$param .= '&contextpage='.urlencode($contextpage);
}
if ($limit > 0 && $limit !== (int) $conf->liste_limit) {
	$param .= '&limit='.$limit;
}
if ($searchRowid !== '') {
	$param .= '&search_rowid='.urlencode($searchRowid);
}
if ($actioncode !== '' && $actioncode !== '-1') {
	$param .= '&actioncode='.urlencode(is_array($actioncode) ? implode(',', $actioncode) : $actioncode);
}
if ($searchAgendaLabel !== '') {
	$param .= '&search_agenda_label='.urlencode($searchAgendaLabel);
}
if ($searchComplete !== '') {
	$param .= '&search_complete='.urlencode($searchComplete);
}
if ($searchFiltert !== 0) {
	$param .= '&search_filtert='.$searchFiltert;
}
if ($searchDateEventStart !== '') {
	$param .= '&dateevent_startyear='.GETPOSTINT('dateevent_startyear');
	$param .= '&dateevent_startmonth='.GETPOSTINT('dateevent_startmonth');
	$param .= '&dateevent_startday='.GETPOSTINT('dateevent_startday');
}
if ($searchDateEventEnd !== '') {
	$param .= '&dateevent_endyear='.GETPOSTINT('dateevent_endyear');
	$param .= '&dateevent_endmonth='.GETPOSTINT('dateevent_endmonth');
	$param .= '&dateevent_endday='.GETPOSTINT('dateevent_endday');
}

$morehtmlright = '';
$canCreateAgenda = !empty($user->admin) || $user->hasRight('agenda', 'myactions', 'create') || $user->hasRight('agenda', 'allactions', 'create');
$eventElementType = $object->element.'@'.$object->module;
$addActionUrl = DOL_URL_ROOT.'/comm/action/card.php?action=create';
$addActionUrl .= '&origin='.urlencode($eventElementType).'&originid='.(int) $object->id;
$addActionUrl .= '&socid='.(int) $object->fk_soc.'&projectid='.(int) $object->fk_project;
$addActionUrl .= '&backtopage='.urlencode($_SERVER['PHP_SELF'].'?id='.(int) $object->id);
$morehtmlright .= dolGetButtonTitle($langs->trans('AddAction'), '', 'fa fa-plus-circle', $addActionUrl, '', (int) $canCreateAgenda);

print '<br>';
print_barre_liste($langs->trans('EventsAgenda'), $page, $_SERVER['PHP_SELF'], $param, $sortfield, $sortorder, '', 0, -1, '', 0, $morehtmlright, '', 0, 1, 0);

$filters = array(
	'search_agenda_label' => $searchAgendaLabel,
	'search_rowid' => $searchRowid,
	'search_complete' => $searchComplete,
	'search_filtert' => $searchFiltert,
);
show_actions_done($conf, $langs, $db, $object, null, 0, $actioncode, '', $filters, $sortfield, $sortorder, $object->module);

llxFooter();
$db->close();
