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
		'contacts' => array('ContactsAddresses', '/procedurespv/raccordement/card.php'),
		'notes' => array('Notes', '/procedurespv/raccordement/card.php'),
		'documents' => array('AttachedFiles', '/procedurespv/raccordement/card.php'),
		'agenda' => array('EventsAgenda', '/procedurespv/raccordement/card.php'),
		'collecte' => array('CollecteClient', '/procedurespv/raccordement/collecte.php'),
		'demande' => array('DemandeRaccordement', '/procedurespv/raccordement/demande.php'),
		'cardi' => array('CARDi', '/procedurespv/raccordement/cardi.php'),
		'convention' => array('ConventionContrat', '/procedurespv/raccordement/convention.php'),
		'mes' => array('MiseEnService', '/procedurespv/raccordement/mes.php'),
		'relances' => array('Relances', '/procedurespv/raccordement/relances.php'),
		'history' => array('History', '/procedurespv/raccordement/card.php'),
	);

	foreach ($tabs as $tabKey => $tabDefinition) {
		$tabUrl = dol_buildpath($tabDefinition[1], 1).($id > 0 ? '?id='.$id : '');
		if ($tabDefinition[1] === '/procedurespv/raccordement/card.php') {
			$tabUrl .= ($id > 0 ? '&tab='.$tabKey : '?tab='.$tabKey);
		}
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
	if ($uploadDir !== '') {
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

	return $moduleOutput.'/'.$object->element.'/'.dol_sanitizeFileName((string) $object->ref);
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
