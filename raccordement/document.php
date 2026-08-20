<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * Native attached-files tab for a raccordement.
 */

require '../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/images.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formfile.class.php';
require_once dol_buildpath('/procedurespv/class/raccordement.class.php', 0);
require_once dol_buildpath('/procedurespv/class/raccordementworkflow.class.php', 0);
require_once dol_buildpath('/procedurespv/lib/procedurespv.lib.php', 0);

/**
 * @var Conf $conf
 * @var DoliDB $db
 * @var HookManager $hookmanager
 * @var Translate $langs
 * @var User $user
 */

$langs->loadLangs(array('documents', 'other', 'procedurespv@procedurespv'));

$id = GETPOSTINT('id');
$ref = GETPOST('ref', 'alpha');
$action = GETPOST('action', 'alpha');
$confirm = GETPOST('confirm', 'alpha');
$backtopage = GETPOST('backtopage', 'alpha');

$sortfield = GETPOST('sortfield', 'aZ09comma');
$sortorder = GETPOST('sortorder', 'aZ09comma');
if ($sortfield === '') {
	$sortfield = 'name';
}
if ($sortorder === '') {
	$sortorder = 'ASC';
}

if (!isModEnabled('procedurespv')) {
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
if (!empty($user->socid) && (int) $object->fk_soc !== (int) $user->socid) {
	accessforbidden();
}

$permissiontoadd = procedurespvCanDo($user, 'raccordement', 'write');
$permission = $permissiontoadd;
$permtoedit = $permissiontoadd;
$modulepart = 'procedurespv';
$upload_dir = procedurespvGetRaccordementUploadDir($object);
if ($upload_dir === '') {
	accessforbidden($langs->trans('ErrorFailedToCreateDir'));
}

$hasFileMutation = GETPOST('sendit', 'alpha') !== ''
	|| GETPOST('linkit', 'restricthtml') !== ''
	|| in_array($action, array('deletefile', 'confirm_deletefile', 'deletelink', 'confirm_updateline', 'renamefile'), true);
if ($hasFileMutation && (!GETPOST('token', 'alpha') || (function_exists('checkToken') && !checkToken()))) {
	accessforbidden($langs->trans('ErrorBadToken'));
}

$reconcileWorkflow = GETPOSTINT('reconcile_workflow');
if ($reconcileWorkflow > 0 && (!$permissiontoadd || !GETPOST('token', 'alpha') || (function_exists('checkToken') && !checkToken()))) {
	accessforbidden($langs->trans('ErrorBadToken'));
}

if ($action === 'confirm_deletefile' && $confirm === 'yes' && $backtopage === '') {
	$backtopage = $_SERVER['PHP_SELF'].'?id='.(int) $object->id.'&reconcile_workflow=1&token='.newToken();
}

$hookmanager->initHooks(array('procedurespvraccordementdocument', 'globalcard'));

include DOL_DOCUMENT_ROOT.'/core/actions_linkedfiles.inc.php';

if ($reconcileWorkflow > 0) {
	$workflow = new RaccordementWorkflow($db);
	$targetStatus = $workflow->getReconciledStatus($object);
	if ($targetStatus !== (int) $object->status) {
		$object->context['trigger_reason'] = 'document_change';
		$object->context['changed_fields'] = array('documents', 'status');
		if ($object->setStatus($user, $targetStatus) > 0) {
			procedurespvCreateAgendaEvent($object, $user, 'AgendaRaccordementDocumentUpdated');
		} else {
			setEventMessages($object->error, $object->errors, 'errors');
		}
	}
	header('Location: '.$_SERVER['PHP_SELF'].'?id='.(int) $object->id);
	exit;
}

$form = new Form($db);
$formfile = new FormFile($db);

$title = $langs->trans('AttachedFiles').' - '.(string) $object->ref;
llxHeader('', $title, '', '', 0, 0, '', '', '', 'mod-procedurespv page-raccordement-document');

$head = procedurespvRaccordementPrepareHead($object);
print dol_get_fiche_head($head, 'documents', $langs->trans('Raccordement'), -1, $object->picto);

$filearray = dol_dir_list($upload_dir, 'files', 0, '', '(\.meta|_preview.*\.png)$', $sortfield, strtolower($sortorder) === 'desc' ? SORT_DESC : SORT_ASC, 1);
if (!is_array($filearray)) {
	$filearray = array();
}
$totalsize = 0;
foreach ($filearray as $file) {
	$totalsize += (int) $file['size'];
}

$linkback = '<a href="'.dol_buildpath('/procedurespv/raccordement/list.php', 1).'?restore_lastsearch_values=1">'.$langs->trans('BackToList').'</a>';
dol_banner_tab($object, 'ref', $linkback, 1, 'ref', 'ref');

print '<div class="fichecenter">';
print '<div class="underbanner clearboth"></div>';
print '<table class="border tableforfield centpercent">';
print '<tr><td class="titlefield">'.$langs->trans('NbOfAttachedFiles').'</td><td>'.count($filearray).'</td></tr>';
print '<tr><td>'.$langs->trans('TotalSizeOfAttachedFiles').'</td><td>'.dol_print_size($totalsize, 1, 1).'</td></tr>';
print '</table>';
print '</div>';
print '<div class="clearboth"></div>';
print dol_get_fiche_end();

$param = '&id='.(int) $object->id.'&entity='.(int) $object->entity;
$relativepathwithnofile = dol_sanitizeFileName((string) $object->ref).'/';
$moreparam = '&entity='.(int) $object->entity;
include DOL_DOCUMENT_ROOT.'/core/tpl/document_actions_post_headers.tpl.php';

llxFooter();
$db->close();
