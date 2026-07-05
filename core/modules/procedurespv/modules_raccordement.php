<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * Parent class for Raccordement numbering modules.
 */
abstract class ModeleNumRefRaccordement
{
	/**
	 * Database handler.
	 *
	 * @var DoliDB
	 */
	public $db;

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
	 * Return numbering module information.
	 *
	 * @param Translate $langs Language handler
	 * @return string
	 */
	abstract public function info($langs);

	/**
	 * Return example.
	 *
	 * @return string
	 */
	abstract public function getExample();

	/**
	 * Tell whether this model can be activated.
	 *
	 * @return bool
	 */
	abstract public function canBeActivated();

	/**
	 * Return next value.
	 *
	 * @param Societe|null $objsoc Thirdparty
	 * @param Raccordement|null $object Object
	 * @return string
	 */
	abstract public function getNextValue($objsoc = null, $object = null);
}

/**
 * Standard Raccordement numbering module.
 */
class mod_pvproc_standard extends ModeleNumRefRaccordement
{
	/**
	 * Version.
	 *
	 * @var string
	 */
	public $version = 'dolibarr';

	/**
	 * Return numbering module information.
	 *
	 * @param Translate $langs Language handler
	 * @return string
	 */
	public function info($langs)
	{
		return $langs->trans('PvprocStandardNumberingDescription');
	}

	/**
	 * Return example.
	 *
	 * @return string
	 */
	public function getExample()
	{
		return 'PVPROC-'.dol_print_date(dol_now(), '%Y').'-0001';
	}

	/**
	 * Tell whether this model can be activated.
	 *
	 * @return bool
	 */
	public function canBeActivated()
	{
		return true;
	}

	/**
	 * Return next value.
	 *
	 * @param Societe|null $objsoc Thirdparty
	 * @param Raccordement|null $object Object
	 * @return string
	 */
	public function getNextValue($objsoc = null, $object = null)
	{
		global $conf;

		$prefix = 'PVPROC-'.dol_print_date(dol_now(), '%Y').'-';
		$startpos = strlen($prefix) + 1;
		$entityFilter = function_exists('getEntity') ? getEntity('procedurespv_raccordement') : (string) ((int) $conf->entity);

		$sql = 'SELECT MAX(CAST(SUBSTRING(ref, '.$startpos.') AS UNSIGNED)) as maxref';
		$sql .= ' FROM '.MAIN_DB_PREFIX.'pvproc_raccordement';
		$sql .= " WHERE entity IN (".$entityFilter.")";
		$sql .= " AND ref LIKE '".$this->db->escape($prefix)."%'";

		$resql = $this->db->query($sql);
		if (!$resql) {
			return '';
		}

		$max = 0;
		$obj = $this->db->fetch_object($resql);
		if (is_object($obj) && isset($obj->maxref)) {
			$max = (int) $obj->maxref;
		}

		return $prefix.sprintf('%04d', $max + 1);
	}
}

