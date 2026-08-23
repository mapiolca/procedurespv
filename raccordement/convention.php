<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

require '../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once dol_buildpath('/procedurespv/class/raccordement.class.php', 0);
require_once dol_buildpath('/procedurespv/class/convention.class.php', 0);
require_once dol_buildpath('/procedurespv/class/raccordementworkflow.class.php', 0);
require_once dol_buildpath('/procedurespv/lib/procedurespv.lib.php', 0);

/**
 * Read Dolibarr date selector value.
 *
 * @param string $prefix Field prefix
 * @return int|null
 */
function procedurespvConventionReadDateTimeFromPost($prefix)
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

/**
 * Build convention input payload.
 *
 * @return array<string, string|int|null>
 */
function procedurespvConventionReadPayload()
{
	return array(
		'type_convention' => GETPOST('type_convention', 'alphanohtml'),
		'ref_convention' => GETPOST('ref_convention', 'alphanohtml'),
		'status' => GETPOSTINT('status'),
		'date_reception' => procedurespvConventionReadDateTimeFromPost('date_reception'),
		'date_envoi_client' => procedurespvConventionReadDateTimeFromPost('date_envoi_client'),
		'date_signature_client' => procedurespvConventionReadDateTimeFromPost('date_signature_client'),
		'date_retour_enedis' => procedurespvConventionReadDateTimeFromPost('date_retour_enedis'),
		'date_validation' => procedurespvConventionReadDateTimeFromPost('date_validation'),
		'document_recu' => '',
		'document_signe' => '',
		'commentaire' => GETPOST('commentaire', 'restricthtml'),
	);
}

/**
 * Populate a convention used by the add/edit form from submitted values.
 *
 * @param Convention $convention Form convention
 * @param array<string, string|int|null> $payload Submitted values
 * @return void
 */
function procedurespvConventionApplyPayload($convention, array $payload)
{
	$convention->type_convention = (string) $payload['type_convention'];
	$convention->ref_convention = (string) $payload['ref_convention'];
	$convention->status = (int) $payload['status'];
	$convention->date_reception = $payload['date_reception'];
	$convention->date_envoi_client = $payload['date_envoi_client'];
	$convention->date_signature_client = $payload['date_signature_client'];
	$convention->date_retour_enedis = $payload['date_retour_enedis'];
	$convention->date_validation = $payload['date_validation'];
	$convention->commentaire = (string) $payload['commentaire'];
}

/**
 * Store convention documents in the native raccordement attached-files directory.
 *
 * @param Raccordement $object Parent object
 * @param Convention $convention Convention being created or updated
 * @param array<string, string|int|null> $payload Convention payload updated with stored filenames
 * @return array{result:int,files:list<string>,error:string}
 */
function procedurespvConventionStoreUploadedDocuments($object, $convention, array &$payload)
{
	$storedFiles = array();
	$definitions = array(
		'document_recu_file' => array('payload_key' => 'document_recu', 'code' => 'convention_'.((int) $convention->id).'_received'),
		'document_signe_file' => array('payload_key' => 'document_signe', 'code' => 'convention_'.((int) $convention->id).'_signed'),
	);

	foreach ($definitions as $fieldName => $definition) {
		$upload = procedurespvStoreRaccordementAttachedFile($fieldName, $object, $definition['code']);
		if ($upload['result'] < 0) {
			return array('result' => -1, 'files' => $storedFiles, 'error' => $upload['error']);
		}
		if ($upload['result'] > 0) {
			$payload[$definition['payload_key']] = $upload['filename'];
			$storedFiles[] = $upload['filename'];
		}
	}

	return array('result' => 1, 'files' => $storedFiles, 'error' => '');
}

/**
 * Remove only files created by the current failed transaction.
 *
 * @param Raccordement $object Parent object
 * @param list<string> $filenames Stored filenames
 * @return void
 */
function procedurespvConventionCleanupStoredFiles($object, array $filenames)
{
	$uploadDir = procedurespvGetRaccordementUploadDir($object);
	if ($uploadDir === '') {
		return;
	}
	foreach ($filenames as $filename) {
		$safeFilename = dol_sanitizeFileName($filename);
		if ($safeFilename !== '' && $safeFilename === $filename) {
			dol_delete_file($uploadDir.'/'.$safeFilename);
		}
	}
}

/**
 * Render a stored convention document with native preview and download actions.
 *
 * @param Raccordement $object Parent object
 * @param string $filename Stored filename or legacy text value
 * @return string
 */
function procedurespvConventionRenderDocument($object, $filename)
{
	if ($filename === '') {
		return '';
	}
	$safeFilename = dol_sanitizeFileName($filename);
	$uploadDir = procedurespvGetRaccordementUploadDir($object);
	if ($safeFilename === $filename && $uploadDir !== '' && is_file($uploadDir.'/'.$safeFilename)) {
		return procedurespvRenderRaccordementDocumentLink($object, $safeFilename);
	}

	return dol_escape_htmltag($filename);
}

$langs->loadLangs(array('documents', 'other', 'procedurespv@procedurespv'));

$id = GETPOSTINT('id');
$lineid = GETPOSTINT('lineid');
$action = GETPOST('action', 'aZ09');
$displayForm = GETPOST('displayform', 'alpha');

if (!isModEnabled('procedurespv')) {
	accessforbidden();
}

$object = new Raccordement($db);
$result = $object->fetch($id);
if ($result <= 0) {
	accessforbidden($langs->trans('ErrorRecordNotFound'));
}

$permissiontoread = procedurespvCanDo($user, 'raccordement', 'read');
$permissiontowrite = procedurespvCanDo($user, 'raccordement', 'manage_convention');
if (!$permissiontoread) {
	accessforbidden();
}
$workflow = new RaccordementWorkflow($db);
/** @var array<string, string|int|null>|null $submittedConventionPayload */
$submittedConventionPayload = null;

$sensitiveActions = array('add_convention', 'update_convention', 'mark_received', 'mark_sent_signature', 'mark_signed', 'mark_returned_enedis', 'mark_validated', 'mark_obsolete');
if (in_array($action, $sensitiveActions, true) && (!GETPOST('token', 'alpha') || (function_exists('checkToken') && !checkToken()))) {
	accessforbidden($langs->trans('ErrorBadToken'));
}

if (in_array($action, $sensitiveActions, true) && !$permissiontowrite) {
	accessforbidden();
}

if ($action === 'add_convention') {
	$convention = new Convention($db);
	$payload = procedurespvConventionReadPayload();
	$payload['status'] = Convention::STATUS_NOT_RECEIVED;
	$submittedConventionPayload = $payload;
	$storedFiles = array();
	$uploadError = '';
	$db->begin();
	$result = $convention->create($object, $payload);
	if ($result > 0) {
		$result = $convention->fetch((int) $result);
	}
	if ($result > 0) {
		$upload = procedurespvConventionStoreUploadedDocuments($object, $convention, $payload);
		$storedFiles = $upload['files'];
		$uploadError = $upload['error'];
		$result = $upload['result'];
	}
	if ($result > 0) {
		$result = $convention->update($payload);
	}
	if ($result > 0) {
		$details = trim((string) $convention->type_convention.' '.(string) $convention->ref_convention);
		if ($object->triggerUserAction($user, 'convention_created', array('conventions', 'documents'), $details) < 0) {
			$result = -1;
		}
	}
	if ($result > 0) {
		$db->commit();
		setEventMessages($langs->trans('ConventionCreated'), null, 'mesgs');
		header('Location: '.dol_buildpath('/procedurespv/raccordement/convention.php', 1).'?id='.(int) $object->id);
		exit;
	}

	$db->rollback();
	procedurespvConventionCleanupStoredFiles($object, $storedFiles);
	if ($uploadError !== '') {
		setEventMessages($langs->trans($uploadError), null, 'errors');
	} else {
		setEventMessages($convention->error, $convention->errors, 'errors');
	}
}

if ($action === 'update_convention') {
	$convention = new Convention($db);
	$result = $convention->fetch($lineid);
	if ($result <= 0 || (int) $convention->fk_raccordement !== (int) $object->id) {
		accessforbidden($langs->trans('ErrorRecordNotFound'));
	}

	$payload = procedurespvConventionReadPayload();
	$payload['status'] = (int) $convention->status;
	$payload['document_recu'] = (string) $convention->document_recu;
	$payload['document_signe'] = (string) $convention->document_signe;
	$submittedConventionPayload = $payload;
	$storedFiles = array();
	$uploadError = '';
	$db->begin();
	$upload = procedurespvConventionStoreUploadedDocuments($object, $convention, $payload);
	$storedFiles = $upload['files'];
	$uploadError = $upload['error'];
	$result = $upload['result'];
	if ($result > 0) {
		$result = $convention->update($payload);
	}
	if ($result > 0) {
		$details = trim((string) $convention->type_convention.' '.(string) $convention->ref_convention);
		if ($object->triggerUserAction($user, 'convention_updated', array('conventions', 'documents'), $details) < 0) {
			$result = -1;
		}
	}
	if ($result > 0) {
		$db->commit();
		setEventMessages($langs->trans('RecordSaved'), null, 'mesgs');
		header('Location: '.dol_buildpath('/procedurespv/raccordement/convention.php', 1).'?id='.(int) $object->id);
		exit;
	}

	$db->rollback();
	procedurespvConventionCleanupStoredFiles($object, $storedFiles);
	if ($uploadError !== '') {
		setEventMessages($langs->trans($uploadError), null, 'errors');
	} else {
		setEventMessages($convention->error, $convention->errors, 'errors');
	}
}

$statusActions = array(
	'mark_received' => array('status' => Convention::STATUS_RECEIVED, 'label' => 'MarkReceived'),
	'mark_sent_signature' => array('status' => Convention::STATUS_SENT_FOR_SIGNATURE, 'label' => 'MarkSentSignature'),
	'mark_signed' => array('status' => Convention::STATUS_SIGNED, 'label' => 'MarkSigned'),
	'mark_returned_enedis' => array('status' => Convention::STATUS_RETURNED_ENEDIS, 'label' => 'MarkReturnedEnedis'),
	'mark_validated' => array('status' => Convention::STATUS_VALIDATED, 'label' => 'MarkValidated'),
	'mark_obsolete' => array('status' => Convention::STATUS_OBSOLETE, 'label' => 'MarkObsolete'),
);
if (isset($statusActions[$action])) {
	$convention = new Convention($db);
	$result = $convention->fetch($lineid);
	if ($result <= 0 || (int) $convention->fk_raccordement !== (int) $object->id) {
		accessforbidden($langs->trans('ErrorRecordNotFound'));
	}

	$db->begin();
	$result = $convention->setStatus((int) $statusActions[$action]['status']);
	if ($result > 0) {
		$originalStatus = (int) $object->status;
		$targetStatus = $originalStatus;
		if ($action === 'mark_received' && (int) $object->status < 11) {
			$targetStatus = 11;
		}
		if ($action === 'mark_signed' && (int) $object->status < 12) {
			$targetStatus = 12;
		}
		$object->status = $targetStatus;
		$targetStatus = $workflow->getReconciledStatus($object);
		if ($targetStatus !== $originalStatus) {
			$object->context['trigger_reason'] = 'convention_status_changed';
			$object->context['changed_fields'] = array('conventions', 'status');
			$object->context['agenda_details'] = trim((string) $convention->type_convention.' '.(string) $convention->ref_convention);
			$result = $object->setStatus($user, $targetStatus);
		} elseif ($object->triggerUserAction(
			$user,
			'convention_status_changed',
			array('conventions'),
			trim((string) $convention->type_convention.' '.(string) $convention->ref_convention)
		) < 0) {
			$result = -1;
		}
		if ($result > 0) {
			$db->commit();
			setEventMessages($langs->trans('ConventionStatusUpdated'), null, 'mesgs');
			header('Location: '.dol_buildpath('/procedurespv/raccordement/convention.php', 1).'?id='.(int) $object->id);
			exit;
		}
	}

	$db->rollback();
	setEventMessages($object->error !== '' ? $object->error : $convention->error, !empty($object->errors) ? $object->errors : $convention->errors, 'errors');
}

$form = new Form($db);
$conventionFetcher = new Convention($db);
$conventions = $conventionFetcher->fetchAllByRaccordement((int) $object->id);
$editedConvention = new Convention($db);
$openConventionDialog = false;
if (in_array($action, array('edit', 'update_convention'), true) && $lineid > 0) {
	if (!$permissiontowrite) {
		accessforbidden();
	}
	$result = $editedConvention->fetch($lineid);
	if ($result <= 0 || (int) $editedConvention->fk_raccordement !== (int) $object->id) {
		accessforbidden($langs->trans('ErrorRecordNotFound'));
	}
	$openConventionDialog = true;
	if ($action === 'update_convention' && is_array($submittedConventionPayload)) {
		procedurespvConventionApplyPayload($editedConvention, $submittedConventionPayload);
	}
} elseif ($displayForm === 'add' || $action === 'add_convention') {
	if (!$permissiontowrite) {
		accessforbidden();
	}
	$openConventionDialog = true;
	if ($action === 'add_convention' && is_array($submittedConventionPayload)) {
		procedurespvConventionApplyPayload($editedConvention, $submittedConventionPayload);
	}
}

llxHeader('', $langs->trans('ConventionContrat'), '', '', 0, 0, '', '', '', 'mod-procedurespv page-raccordement-convention');

$head = procedurespvRaccordementPrepareHead($object);
print dol_get_fiche_head($head, 'convention', $langs->trans('Raccordement'), -1, $object->picto);

$linkback = '<a href="'.dol_buildpath('/procedurespv/raccordement/list.php', 1).'">'.$langs->trans('BackToList').'</a>';
dol_banner_tab($object, 'ref', $linkback, 1, 'ref', 'ref');

$isEdit = ((int) $editedConvention->id > 0);
$formAction = $isEdit ? 'update_convention' : 'add_convention';
$formTitle = $isEdit ? $langs->trans('EditConvention') : $langs->trans('AddConvention');
$pageUrl = dol_buildpath('/procedurespv/raccordement/convention.php', 1).'?id='.(int) $object->id;

if ($permissiontowrite) {
	print '<div class="tabsAction">';
	print '<a id="pvproc-open-convention-dialog" class="butAction" href="'.dol_escape_htmltag($pageUrl.'&displayform=add').'">'.$langs->trans('AddConvention').'</a>';
	print '</div>';
}

print '<div class="div-table-responsive"><table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<td>'.$langs->trans('ConventionType').'</td>';
print '<td>'.$langs->trans('ConventionReference').'</td>';
print '<td class="center">'.$langs->trans('Status').'</td>';
print '<td class="center">'.$langs->trans('ConventionReceptionDate').'</td>';
print '<td class="center">'.$langs->trans('ConventionClientSignatureDate').'</td>';
print '<td>'.$langs->trans('Files').'</td>';
print '<td class="right">'.$langs->trans('Actions').'</td>';
print '</tr>';

if (!empty($conventions)) {
	foreach ($conventions as $convention) {
		$typeLabels = Convention::getTypeLabels();
		$typeKey = isset($typeLabels[(string) $convention->type_convention]) ? $typeLabels[(string) $convention->type_convention] : 'ConventionTypeOtherEnedis';
		$baseUrl = dol_buildpath('/procedurespv/raccordement/convention.php', 1).'?id='.(int) $object->id.'&lineid='.(int) $convention->id;
		$token = newToken();

		print '<tr class="oddeven">';
		print '<td>'.$langs->trans($typeKey).'</td>';
		print '<td>'.dol_escape_htmltag((string) $convention->ref_convention).'</td>';
		print '<td class="center">'.$convention->getLibStatut(5).'</td>';
		print '<td class="center">'.($convention->date_reception ? dol_print_date((int) $convention->date_reception, 'dayhour') : '').'</td>';
		print '<td class="center">'.($convention->date_signature_client ? dol_print_date((int) $convention->date_signature_client, 'dayhour') : '').'</td>';
		print '<td>';
		if ($convention->document_recu !== '') {
			print '<div>'.$langs->trans('ConventionReceivedDocument').' : '.procedurespvConventionRenderDocument($object, (string) $convention->document_recu).'</div>';
		}
		if ($convention->document_signe !== '') {
			print '<div>'.$langs->trans('ConventionSignedDocument').' : '.procedurespvConventionRenderDocument($object, (string) $convention->document_signe).'</div>';
		}
		if ($convention->document_recu === '' && $convention->document_signe === '') {
			print '<span class="opacitymedium">'.$langs->trans('NoFileFound').'</span>';
		}
		print '</td>';
		print '<td class="right nowrap">';
		if ($permissiontowrite) {
			print '<a class="button button-edit reposition smallpaddingimp" href="'.$baseUrl.'&action=edit">'.$langs->trans('Modify').'</a> ';
			foreach ($statusActions as $actionCode => $actionDefinition) {
				if ($convention->canTransitionTo((int) $actionDefinition['status'])) {
					print '<a class="button reposition smallpaddingimp" href="'.$baseUrl.'&action='.$actionCode.'&token='.$token.'">'.$langs->trans($actionDefinition['label']).'</a> ';
				}
			}
		}
		print '</td>';
		print '</tr>';
	}
} else {
	print '<tr class="oddeven"><td colspan="7"><span class="opacitymedium">'.$langs->trans('NoRecordFound').'</span></td></tr>';
}
print '</table></div>';

if ($permissiontowrite) {
	$allowedExtensions = array_filter(array_map('trim', explode(',', strtolower(getDolGlobalString('PROCEDURESPV_PUBLIC_UPLOAD_ALLOWED_EXTENSIONS', 'pdf,jpg,jpeg,png')))));
	$acceptedFileTypes = array();
	foreach ($allowedExtensions as $allowedExtension) {
		if (preg_match('/^[a-z0-9]+$/', $allowedExtension)) {
			$acceptedFileTypes[] = '.'.$allowedExtension;
		}
	}
	$acceptAttribute = !empty($acceptedFileTypes) ? ' accept="'.dol_escape_htmltag(implode(',', $acceptedFileTypes)).'"' : '';
	$maximumUploadSize = getDolGlobalInt('PROCEDURESPV_PUBLIC_UPLOAD_MAX_SIZE', 10 * 1024 * 1024);

	print '<div id="pvproc-convention-dialog" title="'.dol_escape_htmltag($formTitle).'" style="display:none">';
	print '<form id="pvproc-convention-form" method="POST" enctype="multipart/form-data" action="'.dol_escape_htmltag($_SERVER['PHP_SELF']).'?id='.(int) $object->id.'">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="'.$formAction.'">';
	if ($isEdit) {
		print '<input type="hidden" name="lineid" value="'.((int) $editedConvention->id).'">';
	}

	print '<table class="border centpercent tableforfield">';
	print '<tr><td class="titlefield fieldrequired">'.$langs->trans('ConventionType').'</td><td><select class="flat minwidth300" name="type_convention" id="type_convention">';
	foreach (Convention::getTypeLabels() as $value => $labelKey) {
		print '<option value="'.dol_escape_htmltag($value).'"'.($editedConvention->type_convention === $value ? ' selected' : '').'>'.$langs->trans($labelKey).'</option>';
	}
	print '</select>'.ajax_combobox('type_convention').'</td></tr>';
	print '<tr><td>'.$langs->trans('ConventionReference').'</td><td><input type="text" class="flat minwidth300" name="ref_convention" value="'.dol_escape_htmltag((string) $editedConvention->ref_convention).'"></td></tr>';
	print '<tr><td>'.$langs->trans('ConventionStatus').'</td><td>'.$editedConvention->getLibStatut(5).'</td></tr>';
	print '<tr><td>'.$langs->trans('ConventionReceptionDate').'</td><td>';
	$form->selectDate($editedConvention->date_reception ? (int) $editedConvention->date_reception : -1, 'date_reception', 1, 1, 1, '', 1, 1);
	print '</td></tr>';
	print '<tr><td>'.$langs->trans('ConventionClientSentDate').'</td><td>';
	$form->selectDate($editedConvention->date_envoi_client ? (int) $editedConvention->date_envoi_client : -1, 'date_envoi_client', 1, 1, 1, '', 1, 1);
	print '</td></tr>';
	print '<tr><td>'.$langs->trans('ConventionClientSignatureDate').'</td><td>';
	$form->selectDate($editedConvention->date_signature_client ? (int) $editedConvention->date_signature_client : -1, 'date_signature_client', 1, 1, 1, '', 1, 1);
	print '</td></tr>';
	print '<tr><td>'.$langs->trans('ConventionReturnEnedisDate').'</td><td>';
	$form->selectDate($editedConvention->date_retour_enedis ? (int) $editedConvention->date_retour_enedis : -1, 'date_retour_enedis', 1, 1, 1, '', 1, 1);
	print '</td></tr>';
	print '<tr><td>'.$langs->trans('ConventionValidationDate').'</td><td>';
	$form->selectDate($editedConvention->date_validation ? (int) $editedConvention->date_validation : -1, 'date_validation', 1, 1, 1, '', 1, 1);
	print '</td></tr>';
	print '<tr><td>'.$langs->trans('ConventionReceivedDocument').'</td><td>';
	print '<input type="file" class="flat minwidth400" name="document_recu_file"'.$acceptAttribute.'>';
	if ((string) $editedConvention->document_recu !== '') {
		print '<div class="opacitymedium">'.$langs->trans('ConventionCurrentDocument').' '.procedurespvConventionRenderDocument($object, (string) $editedConvention->document_recu).'</div>';
	}
	print '<div class="opacitymedium">'.$langs->trans('ConventionDocumentUploadHelp', dol_print_size($maximumUploadSize, 1, 1)).'</div>';
	print '</td></tr>';
	print '<tr><td>'.$langs->trans('ConventionSignedDocument').'</td><td>';
	print '<input type="file" class="flat minwidth400" name="document_signe_file"'.$acceptAttribute.'>';
	if ((string) $editedConvention->document_signe !== '') {
		print '<div class="opacitymedium">'.$langs->trans('ConventionCurrentDocument').' '.procedurespvConventionRenderDocument($object, (string) $editedConvention->document_signe).'</div>';
	}
	print '<div class="opacitymedium">'.$langs->trans('ConventionDocumentUploadHelp', dol_print_size($maximumUploadSize, 1, 1)).'</div>';
	print '</td></tr>';
	print '<tr><td>'.$langs->trans('Comment').'</td><td><textarea class="flat centpercent" name="commentaire" rows="3">'.dol_escape_htmltag((string) $editedConvention->commentaire).'</textarea></td></tr>';
	print '</table>';
	print '<div class="center">';
	print '<button type="button" class="button button-cancel pvproc-close-convention-dialog">'.$langs->trans('Cancel').'</button> ';
	print '<input type="submit" class="button button-save" value="'.$langs->trans('Save').'">';
	print '</div>';
	print '</form></div>';

	print '<script nonce="'.getNonce().'" type="text/javascript">
jQuery(function ($) {
	var dialog = $("#pvproc-convention-dialog");
	var openOnLoad = '.($openConventionDialog ? 'true' : 'false').';
	var editMode = '.($isEdit ? 'true' : 'false').';
	if ($.fn.dialog) {
		dialog.dialog({
			autoOpen: false,
			modal: true,
			width: Math.min(window.innerWidth * 0.9, 1100),
			maxHeight: Math.max(300, window.innerHeight - 100),
			close: function () {
				if (openOnLoad && window.history && window.history.replaceState) {
					window.history.replaceState({}, document.title, "'.dol_escape_js($pageUrl).'");
				}
			}
		});
		$("#pvproc-open-convention-dialog").on("click", function (event) {
			if (editMode) {
				return true;
			}
			event.preventDefault();
			dialog.dialog("open");
			return false;
		});
		$(".pvproc-close-convention-dialog").on("click", function () {
			dialog.dialog("close");
		});
		if (openOnLoad) {
			dialog.dialog("open");
		}
	} else if (openOnLoad) {
		dialog.show();
	}
});
</script>';
}

print dol_get_fiche_end();

llxFooter();
$db->close();
