<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * Native contacts/addresses tab for a raccordement.
 */

require '../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/contact/class/contact.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formcompany.class.php';
require_once dol_buildpath('/procedurespv/class/raccordement.class.php', 0);
require_once dol_buildpath('/procedurespv/lib/procedurespv.lib.php', 0);

/**
 * @var Conf $conf
 * @var DoliDB $db
 * @var HookManager $hookmanager
 * @var Translate $langs
 * @var User $user
 */

$langs->loadLangs(array('companies', 'users', 'procedurespv@procedurespv'));

$id = GETPOSTINT('id');
$ref = GETPOST('ref', 'alpha');
$action = GETPOST('action', 'aZ09');

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
$hookmanager->initHooks(array('procedurespvraccordementcontactcard', 'globalcard'));

if (in_array($action, array('addcontact', 'swapstatut', 'deletecontact'), true)
	&& (!GETPOST('token', 'alpha') || (function_exists('checkToken') && !checkToken()))) {
	accessforbidden($langs->trans('ErrorBadToken'));
}

$parameters = array('id' => (int) $object->id);
$reshook = $hookmanager->executeHooks('doActions', $parameters, $object, $action);
if ($reshook < 0) {
	setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');
}

if (empty($reshook)) {
	if ($action === 'addcontact') {
		if (!$permissiontoadd) {
			accessforbidden();
		}
		$source = GETPOST('source', 'alpha');
		if (!in_array($source, array('internal', 'external'), true)) {
			accessforbidden($langs->trans('ErrorBadParameters'));
		}
		$contactId = $source === 'internal' ? GETPOSTINT('userid') : GETPOSTINT('contactid');
		$typeId = GETPOSTINT('typecontact');
		if ($typeId <= 0) {
			$typeId = GETPOSTINT('type');
		}
		$availableTypes = $object->liste_type_contact($source, 'position', 2, 1);
		if (!is_array($availableTypes) || !isset($availableTypes[$typeId])) {
			accessforbidden($langs->trans('ErrorBadParameters'));
		}
		$result = $object->add_contact($contactId, $typeId, $source, 1);
		if ($result >= 0) {
			if ($result > 0 && $object->triggerContactUpdate($user, 'add') < 0) {
				setEventMessages($object->error, $object->errors, 'errors');
			} else {
				header('Location: '.$_SERVER['PHP_SELF'].'?id='.(int) $object->id);
				exit;
			}
		} else {
			$message = $object->error === 'DB_ERROR_RECORD_ALREADY_EXISTS'
				? $langs->trans('ErrorThisContactIsAlreadyDefinedAsThisType')
				: $object->error;
			setEventMessages($message, $object->errors, 'errors');
		}
	}

	if ($action === 'swapstatut') {
		if (!$permissiontoadd) {
			accessforbidden();
		}
		$lineId = GETPOSTINT('ligne');
		if (!$object->hasLinkedContactLine($lineId)) {
			accessforbidden($langs->trans('ErrorRecordNotFound'));
		}
		$result = $object->swapContactStatus($lineId);
		if ($result >= 0 && $object->triggerContactUpdate($user, 'status', $lineId) < 0) {
			setEventMessages($object->error, $object->errors, 'errors');
		}
	}

	if ($action === 'deletecontact') {
		if (!$permissiontoadd) {
			accessforbidden();
		}
		$lineId = GETPOSTINT('lineid');
		if (!$object->hasLinkedContactLine($lineId)) {
			accessforbidden($langs->trans('ErrorRecordNotFound'));
		}
		$result = $object->delete_contact($lineId, 1);
		if ($result >= 0) {
			if ($object->triggerContactUpdate($user, 'delete', $lineId) < 0) {
				setEventMessages($object->error, $object->errors, 'errors');
			} else {
				header('Location: '.$_SERVER['PHP_SELF'].'?id='.(int) $object->id);
				exit;
			}
		} else {
			setEventMessages($object->error, $object->errors, 'errors');
		}
	}
}

$form = new Form($db);
if ((int) $object->socid > 0) {
	$object->fetch_thirdparty();
}

$title = $langs->trans('ContactsAddresses').' - '.(string) $object->ref;
llxHeader('', $title, '', '', 0, 0, '', '', '', 'mod-procedurespv page-raccordement-contact');

$head = procedurespvRaccordementPrepareHead($object);
print dol_get_fiche_head($head, 'contacts', $langs->trans('Raccordement'), -1, $object->picto);

$linkback = '<a href="'.dol_buildpath('/procedurespv/raccordement/list.php', 1).'?restore_lastsearch_values=1">'.$langs->trans('BackToList').'</a>';
dol_banner_tab($object, 'ref', $linkback, 1, 'ref', 'ref');

print dol_get_fiche_end();
print '<br>';

include DOL_DOCUMENT_ROOT.'/core/tpl/contacts.tpl.php';

llxFooter();
$db->close();
