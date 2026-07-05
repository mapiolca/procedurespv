<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * Conservative adapter for optional Centrale PV integration.
 */
class CentralePVAdapter
{
	/**
	 * Database handler.
	 *
	 * @var DoliDB
	 */
	private $db;

	/**
	 * Detected module key.
	 *
	 * @var string
	 */
	private $moduleKey = '';

	/**
	 * Last error.
	 *
	 * @var string
	 */
	public $error = '';

	/**
	 * Constructor.
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		$this->db = $db;
	}

	/**
	 * Return candidate module keys.
	 *
	 * @return list<string>
	 */
	private function getCandidateModuleKeys()
	{
		return array('centralepv', 'centrale_pv', 'centralespv');
	}

	/**
	 * Detect optional module availability.
	 *
	 * @return bool
	 */
	public function isAvailable()
	{
		if (getDolGlobalInt('PROCEDURESPV_USE_CENTRALEPV_IF_AVAILABLE', 1) <= 0) {
			return false;
		}

		foreach ($this->getCandidateModuleKeys() as $moduleKey) {
			if (function_exists('isModEnabled') && isModEnabled($moduleKey)) {
				$this->moduleKey = $moduleKey;
				return true;
			}
		}

		return false;
	}

	/**
	 * Return main menu key to use when the module is detected.
	 *
	 * @return string
	 */
	public function getMainMenuKey()
	{
		if ($this->moduleKey === '') {
			$this->isAvailable();
		}

		return $this->moduleKey !== '' ? $this->moduleKey : 'procedurespv';
	}

	/**
	 * Fetch a Centrale PV-like row without assuming an external PHP class.
	 *
	 * @param int $id Centrale identifier
	 * @return stdClass|null
	 */
	public function fetchCentrale($id)
	{
		$id = (int) $id;
		if ($id <= 0 || !$this->isAvailable()) {
			return null;
		}

		foreach ($this->getCandidateTables() as $tableName) {
			if (!$this->tableExists($tableName)) {
				continue;
			}

			$sql = 'SELECT * FROM '.$tableName.' WHERE rowid = '.$id;
			$resql = $this->db->query($sql);
			if (!$resql) {
				$this->error = $this->db->lasterror();
				return null;
			}

			$obj = $this->db->fetch_object($resql);
			if (is_object($obj)) {
				return $obj;
			}
		}

		return null;
	}

	/**
	 * Return Centrale label.
	 *
	 * @param int $id Centrale identifier
	 * @return string
	 */
	public function getCentraleLabel($id)
	{
		$obj = $this->fetchCentrale($id);
		if (!is_object($obj)) {
			return '';
		}

		foreach (array('label', 'nom', 'name', 'ref') as $field) {
			if (isset($obj->{$field}) && (string) $obj->{$field} !== '') {
				return (string) $obj->{$field};
			}
		}

		return (string) $id;
	}

	/**
	 * Return Centrale URL when a known route is available.
	 *
	 * @param int $id Centrale identifier
	 * @return string
	 */
	public function getCentraleUrl($id)
	{
		if (!$this->isAvailable()) {
			return '';
		}

		$moduleKey = $this->getMainMenuKey();
		$candidatePaths = array(
			'/'.$moduleKey.'/centrale/card.php?id='.(int) $id,
			'/'.$moduleKey.'/card.php?id='.(int) $id,
		);

		foreach ($candidatePaths as $path) {
			$localPath = dol_buildpath($path, 0);
			if ($localPath !== '' && file_exists($localPath)) {
				return dol_buildpath($path, 1);
			}
		}

		return '';
	}

	/**
	 * Return mapped site data.
	 *
	 * @param int $id Centrale identifier
	 * @return array<string, string|float|null>
	 */
	public function getSiteData($id)
	{
		$obj = $this->fetchCentrale($id);
		if (!is_object($obj)) {
			return array();
		}

		return array(
			'site_name' => $this->readFirstString($obj, array('label', 'nom', 'name', 'ref')),
			'address' => $this->readFirstString($obj, array('address', 'site_address', 'adresse')),
			'zip' => $this->readFirstString($obj, array('zip', 'site_zip', 'cp', 'code_postal')),
			'town' => $this->readFirstString($obj, array('town', 'site_town', 'ville')),
			'prm' => $this->readFirstString($obj, array('prm', 'pdl', 'prm_pdl')),
			'puissance_installee_kwc' => $this->readFirstFloat($obj, array('puissance_installee_kwc', 'power_kwc', 'puissance')),
			'puissance_injection_kva' => $this->readFirstFloat($obj, array('puissance_injection_kva', 'injection_kva', 'puissance_injection')),
			'type_exploitation' => $this->readFirstString($obj, array('type_exploitation', 'exploitation_type')),
			'type_pose' => $this->readFirstString($obj, array('type_pose', 'pose_type')),
		);
	}

	/**
	 * Return candidate table names.
	 *
	 * @return list<string>
	 */
	private function getCandidateTables()
	{
		return array(
			MAIN_DB_PREFIX.'centralepv_centrale',
			MAIN_DB_PREFIX.'centrale_pv_centrale',
			MAIN_DB_PREFIX.'centralespv_centrale',
			MAIN_DB_PREFIX.'centralepv',
		);
	}

	/**
	 * Check table existence.
	 *
	 * @param string $tableName Full table name
	 * @return bool
	 */
	private function tableExists($tableName)
	{
		$sql = "SHOW TABLES LIKE '".$this->db->escape($tableName)."'";
		$resql = $this->db->query($sql);

		return $resql && $this->db->num_rows($resql) > 0;
	}

	/**
	 * Read first non-empty string field from an object.
	 *
	 * @param stdClass $obj Source object
	 * @param list<string> $fields Field names
	 * @return string|null
	 */
	private function readFirstString($obj, array $fields)
	{
		foreach ($fields as $field) {
			if (isset($obj->{$field}) && trim((string) $obj->{$field}) !== '') {
				return (string) $obj->{$field};
			}
		}

		return null;
	}

	/**
	 * Read first numeric field from an object.
	 *
	 * @param stdClass $obj Source object
	 * @param list<string> $fields Field names
	 * @return float|null
	 */
	private function readFirstFloat($obj, array $fields)
	{
		foreach ($fields as $field) {
			if (isset($obj->{$field}) && is_numeric($obj->{$field})) {
				return (float) $obj->{$field};
			}
		}

		return null;
	}
}

