<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * Library file for Procedures PV.
 *
 * @package procedurespv
 */

/**
 * Prepare admin tabs.
 *
 * @return array<int, array{0:string, 1:string, 2:string}>
 */
function procedurespvAdminPrepareHead()
{
	global $langs;

	$langs->loadLangs(array('procedurespv@procedurespv'));

	$head = array();
	$h = 0;

	$head[$h][0] = dol_buildpath('/procedurespv/admin/setup.php', 1);
	$head[$h][1] = $langs->trans('Settings');
	$head[$h][2] = 'settings';
	$h++;

	$head[$h][0] = dol_buildpath('/procedurespv/admin/compatibility.php', 1);
	$head[$h][1] = $langs->trans('Compatibility');
	$head[$h][2] = 'compatibility';
	$h++;

	$head[$h][0] = dol_buildpath('/procedurespv/admin/about.php', 1);
	$head[$h][1] = $langs->trans('About');
	$head[$h][2] = 'about';
	$h++;

	return $head;
}

/**
 * Prepare raccordement tabs.
 *
 * @param Raccordement|null $object Current object
 * @return array<int, array{0:string, 1:string, 2:string}>
 */
function procedurespvRaccordementPrepareHead($object = null)
{
	global $langs;

	$langs->loadLangs(array('procedurespv@procedurespv'));

	$id = (is_object($object) && !empty($object->id)) ? (int) $object->id : 0;

	$head = array();
	$h = 0;

	$baseUrl = dol_buildpath('/procedurespv/raccordement/card.php', 1).($id > 0 ? '?id='.$id : '');

	$head[$h][0] = $baseUrl;
	$head[$h][1] = $langs->trans('RaccordementSummary');
	$head[$h][2] = 'card';
	$h++;

	$tabs = array(
		'collecte' => array('CollecteClient', '/procedurespv/raccordement/collecte.php'),
		'demande' => array('DemandeRaccordement', '/procedurespv/raccordement/demande.php'),
	);
	if (is_object($object) && $object->isCardiApplicable()) {
		$tabs['cardi'] = array('CARDi', '/procedurespv/raccordement/cardi.php');
	}
	$tabs['convention'] = array('ConventionContrat', '/procedurespv/raccordement/convention.php');
	$tabs['mes'] = array('MiseEnService', '/procedurespv/raccordement/mes.php');
	$tabs['relances'] = array('Relances', '/procedurespv/raccordement/relances.php');
	$tabs['contacts'] = array('ContactsAddresses', '/procedurespv/raccordement/contact.php');
	$tabs['documents'] = array('RaccordementAttachedFiles', '/procedurespv/raccordement/document.php');
	if (isModEnabled('agenda')) {
		$tabs['agenda'] = array('EventsAgenda', '/procedurespv/raccordement/agenda.php');
	}

	foreach ($tabs as $tabKey => $tabDefinition) {
		$tabUrl = dol_buildpath($tabDefinition[1], 1).($id > 0 ? '?id='.$id : '');
		$head[$h][0] = $tabUrl;
		$head[$h][1] = $langs->trans($tabDefinition[0]);
		$head[$h][2] = $tabKey;
		$h++;
	}

	return $head;
}

/**
 * Central access helper for Procedures PV business actions.
 *
 * Administrators keep functional access while standard users remain bound to granular rights.
 *
 * @param User|null $user User object
 * @param string $objectname Permission object name
 * @param string $action Permission action
 * @return bool
 */
function procedurespvCanDo($user, $objectname, $action)
{
	if (!is_object($user)) {
		return false;
	}

	if (!empty($user->admin)) {
		return true;
	}

	return $user->hasRight('procedurespv', $objectname, $action);
}

/**
 * Return document directory for a raccordement.
 *
 * @param Raccordement $object Raccordement object
 * @return string
 */
function procedurespvGetRaccordementUploadDir($object)
{
	global $conf;

	require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';

	$uploadDir = function_exists('getMultidirOutput') ? getMultidirOutput($object, 'procedurespv', 1) : '';
	if ($uploadDir !== '' && strpos($uploadDir, 'error-') !== 0) {
		return $uploadDir;
	}

	$objectEntity = !empty($object->entity) ? (int) $object->entity : (int) $conf->entity;
	$moduleOutput = '';

	if (isset($conf->procedurespv->multidir_output[$objectEntity]) && $conf->procedurespv->multidir_output[$objectEntity] !== '') {
		$moduleOutput = $conf->procedurespv->multidir_output[$objectEntity];
	} elseif (isset($conf->procedurespv->dir_output)) {
		$moduleOutput = $conf->procedurespv->dir_output;
	}

	if ($moduleOutput === '') {
		return '';
	}

	return $moduleOutput.'/'.dol_sanitizeFileName((string) $object->ref);
}

/** @param Raccordement $object Parent object @param string $filename Filename @return string */
function procedurespvGetRaccordementDocumentRelativePath($object, $filename)
{
	return dol_sanitizeFileName((string) $object->ref).'/'.dol_sanitizeFileName($filename);
}

/** @param Raccordement $object Parent object @param string $filename Filename @param bool $attachment Force download @return string */
function procedurespvGetRaccordementDocumentUrl($object, $filename, $attachment = false)
{
	$url = DOL_URL_ROOT.'/document.php?modulepart=procedurespv&file='.urlencode(procedurespvGetRaccordementDocumentRelativePath($object, $filename));
	if ($attachment) {
		$url .= '&attachment=1';
	}
	if (!empty($object->entity)) {
		$url .= '&entity='.((int) $object->entity);
	}
	return $url;
}

/**
 * Render a native preview/download link for a raccordement document.
 *
 * @param Raccordement $object Parent object
 * @param string $filename Filename
 * @param bool $withDownload Add explicit download icon
 * @return string
 */
function procedurespvRenderRaccordementDocumentLink($object, $filename, $withDownload = true)
{
	global $langs;

	if ($filename === '') {
		return '';
	}
	$relativePath = procedurespvGetRaccordementDocumentRelativePath($object, $filename);
	$previewData = getAdvancedPreviewUrl('procedurespv', $relativePath, 1, '&entity='.(int) $object->entity);
	$previewUrl = is_array($previewData) && !empty($previewData['url']) ? (string) $previewData['url'] : procedurespvGetRaccordementDocumentUrl($object, $filename);
	$previewMime = is_array($previewData) && !empty($previewData['mime']) ? (string) $previewData['mime'] : dol_mimetype($filename);
	$html = '<a class="documentpreview" mime="'.dol_escape_htmltag($previewMime).'" target="_blank" rel="noopener" href="'.dol_escape_htmltag($previewUrl).'">'.dol_escape_htmltag($filename).'</a>';
	if ($withDownload) {
		$html .= ' <a href="'.dol_escape_htmltag(procedurespvGetRaccordementDocumentUrl($object, $filename, true)).'">'.img_picto($langs->trans('Download'), 'download').'</a>';
	}

	return $html;
}

/**
 * Store an attached file in the native raccordement document directory.
 *
 * @param string $fieldName Upload field
 * @param Raccordement $object Parent object
 * @param string $code Document code used in the stored filename
 * @return array{result:int,filename:string,error:string}
 */
function procedurespvStoreRaccordementAttachedFile($fieldName, $object, $code)
{
	if (!isset($_FILES[$fieldName]) || !is_array($_FILES[$fieldName]) || empty($_FILES[$fieldName]['name'])) {
		return array('result' => 0, 'filename' => '', 'error' => '');
	}
	require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
	$file = $_FILES[$fieldName];
	$errorCode = isset($file['error']) ? (int) $file['error'] : UPLOAD_ERR_NO_FILE;
	$originalName = isset($file['name']) ? (string) $file['name'] : '';
	$tmpName = isset($file['tmp_name']) ? (string) $file['tmp_name'] : '';
	$fileSize = isset($file['size']) ? (int) $file['size'] : 0;
	$extension = strtolower((string) pathinfo($originalName, PATHINFO_EXTENSION));
	$allowedExtensions = array_filter(array_map('trim', explode(',', strtolower(getDolGlobalString('PROCEDURESPV_PUBLIC_UPLOAD_ALLOWED_EXTENSIONS', 'pdf,jpg,jpeg,png')))));
	$maxSize = getDolGlobalInt('PROCEDURESPV_PUBLIC_UPLOAD_MAX_SIZE', 10 * 1024 * 1024);
	$uploadDir = procedurespvGetRaccordementUploadDir($object);
	if ($errorCode !== UPLOAD_ERR_OK || $originalName === '' || $tmpName === '' || $uploadDir === '' || dol_mkdir($uploadDir) < 0) {
		return array('result' => -1, 'filename' => '', 'error' => 'UploadDirectoryUnavailable');
	}
	if ($fileSize <= 0 || $fileSize > $maxSize) {
		return array('result' => -1, 'filename' => '', 'error' => 'UploadInvalidSize');
	}
	if ($extension === '' || !in_array($extension, $allowedExtensions, true)) {
		return array('result' => -1, 'filename' => '', 'error' => 'UploadInvalidExtension');
	}
	$filenamePrefix = dol_sanitizeFileName($code).'_'.dol_print_date(dol_now(), '%Y%m%d%H%M%S');
	$sanitizedOriginalName = dol_sanitizeFileName($originalName);
	$storedFilename = $filenamePrefix.'_'.$sanitizedOriginalName;
	$suffix = 1;
	while (file_exists($uploadDir.'/'.$storedFilename)) {
		$storedFilename = $filenamePrefix.'_'.$suffix.'_'.$sanitizedOriginalName;
		$suffix++;
	}
	$result = dol_move_uploaded_file($tmpName, $uploadDir.'/'.$storedFilename, 0, 0, $errorCode, 0, $fieldName, $uploadDir);
	if (!is_int($result) || $result <= 0) {
		return array('result' => -1, 'filename' => '', 'error' => is_string($result) && $result !== '' ? $result : 'UploadMoveFailed');
	}
	if ($result === 2) {
		$storedFilename .= '.noexe';
	}

	return array('result' => 1, 'filename' => $storedFilename, 'error' => '');
}

/**
 * Store an uploaded piece and index it in the collection piece table.
 *
 * @param string $fieldName Upload field
 * @param Raccordement $object Parent object
 * @param string $code Document code
 * @param string $label Label
 * @param string $origin Origin
 * @param int $required Required flag
 * @param int $fkPublicLink Revision id
 * @return array{result:int,filename:string,error:string}
 */
function procedurespvStoreRaccordementUpload($fieldName, $object, $code, $label, $origin = 'internal', $required = 0, $fkPublicLink = 0)
{
	global $db;

	$storedFile = procedurespvStoreRaccordementAttachedFile($fieldName, $object, $code);
	if ($storedFile['result'] <= 0) {
		return $storedFile;
	}

	require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
	require_once dol_buildpath('/procedurespv/class/piece.class.php', 0);
	$uploadDir = procedurespvGetRaccordementUploadDir($object);
	$storedFilename = $storedFile['filename'];
	$piece = new Piece($db);
	$pieceId = $piece->createOrUpdateUploaded($object, $code, $label, $origin, $uploadDir, $storedFilename, $required, $fkPublicLink);
	if ($pieceId <= 0) {
		dol_delete_file($uploadDir.'/'.$storedFilename);
		return array('result' => -1, 'filename' => '', 'error' => $piece->error);
	}
	return array('result' => $pieceId, 'filename' => $storedFilename, 'error' => '');
}

/**
 * Return module output directory for an entity.
 *
 * @param int $entity Entity id
 * @return string
 */
function procedurespvGetModuleOutputDir($entity = 0)
{
	global $conf;

	$entity = $entity > 0 ? $entity : (int) $conf->entity;
	if (isset($conf->procedurespv->multidir_output[$entity]) && $conf->procedurespv->multidir_output[$entity] !== '') {
		return (string) $conf->procedurespv->multidir_output[$entity];
	}
	if (isset($conf->procedurespv->dir_output) && $conf->procedurespv->dir_output !== '') {
		return (string) $conf->procedurespv->dir_output;
	}

	return '';
}

/**
 * Return ENEDIS mandate stamp directory for an entity.
 *
 * @param int $entity Entity id
 * @return string
 */
function procedurespvGetMandatStampDir($entity = 0)
{
	$moduleOutput = procedurespvGetModuleOutputDir($entity);
	if ($moduleOutput === '') {
		return '';
	}

	return $moduleOutput.'/config';
}

/**
 * Return configured ENEDIS mandate stamp relative path for an entity.
 *
 * @param int $entity Entity id
 * @return string
 */
function procedurespvGetMandatStampRelativePath($entity = 0)
{
	global $conf, $db;

	$entity = $entity > 0 ? $entity : (int) $conf->entity;
	$relativePath = '';
	if (isset($db) && is_object($db) && function_exists('dolibarr_get_const')) {
		$value = dolibarr_get_const($db, 'PROCEDURESPV_MANDATENEDIS_STAMP_IMAGE', $entity);
		$relativePath = is_scalar($value) ? (string) $value : '';
	}
	if ($relativePath === '' && (int) $entity === (int) $conf->entity) {
		$relativePath = getDolGlobalString('PROCEDURESPV_MANDATENEDIS_STAMP_IMAGE', '');
	}

	return trim($relativePath);
}

/**
 * Return configured ENEDIS mandate stamp absolute path.
 *
 * @param int $entity Entity id
 * @return string
 */
function procedurespvGetMandatStampPath($entity = 0)
{
	$relativePath = procedurespvGetMandatStampRelativePath($entity);
	if ($relativePath === '' || preg_match('/(^|\/)\.\.(\/|$)/', $relativePath)) {
		return '';
	}

	$moduleOutput = procedurespvGetModuleOutputDir($entity);
	if ($moduleOutput === '') {
		return '';
	}

	return $moduleOutput.'/'.$relativePath;
}

/**
 * Return configured ENEDIS mandate stamp URL.
 *
 * @param int $entity Entity id
 * @return string
 */
function procedurespvGetMandatStampUrl($entity = 0)
{
	global $conf;

	$entity = $entity > 0 ? $entity : (int) $conf->entity;
	$relativePath = procedurespvGetMandatStampRelativePath($entity);
	if ($relativePath === '' || preg_match('/(^|\/)\.\.(\/|$)/', $relativePath)) {
		return '';
	}

	$stampPath = procedurespvGetMandatStampPath($entity);
	if ($stampPath === '' || !is_readable($stampPath)) {
		return '';
	}

	return DOL_URL_ROOT.'/viewimage.php?modulepart=procedurespv&entity='.$entity.'&file='.urlencode($relativePath);
}
