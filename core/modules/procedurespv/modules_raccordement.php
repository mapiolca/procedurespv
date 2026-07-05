<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

require_once DOL_DOCUMENT_ROOT.'/core/class/commonnumrefgenerator.class.php';

/**
 * Parent class for Raccordement numbering modules.
 */
abstract class ModeleNumRefRaccordement extends CommonNumRefGenerator
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
	 * Return name of numbering module.
	 *
	 * @param Translate $langs Language handler
	 * @return string
	 */
	public function getName($langs)
	{
		return !empty($this->name) ? $langs->trans($this->name) : get_class($this);
	}

	/**
	 * Return module version.
	 *
	 * @return string
	 */
	public function getVersion()
	{
		return !empty($this->version) ? (string) $this->version : 'dolibarr';
	}

	/**
	 * Return example.
	 *
	 * @return string
	 */
	abstract public function getExample();

	/**
	 * Return next value.
	 *
	 * @param Societe|null $objsoc Thirdparty
	 * @param Raccordement|null $object Object
	 * @return string|int
	 */
	abstract public function getNextValue($objsoc = null, $object = null);

	/**
	 * Return entities where Raccordement references must be unique.
	 *
	 * @param Raccordement|null $object Current object
	 * @return string Comma-separated sanitized entity ids
	 */
	public static function getRaccordementReferenceEntityList($object = null)
	{
		global $conf;

		$entities = array();
		if (function_exists('getEntity')) {
			foreach (array('procedurespv_raccordement', 'procedurespv_raccordementnumber') as $element) {
				foreach (explode(',', (string) getEntity($element, 1, $object)) as $entity) {
					$entity = trim($entity);
					if ($entity !== '' && preg_match('/^\d+$/', $entity)) {
						$entities[(int) $entity] = (int) $entity;
					}
				}
			}
		}

		if (empty($entities)) {
			$entities[(int) $conf->entity] = (int) $conf->entity;
		}

		ksort($entities, SORT_NUMERIC);

		return implode(',', $entities);
	}

	/**
	 * Resolve date used for numbering.
	 *
	 * @param Raccordement|null $object Current object
	 * @return int
	 */
	protected function getReferenceDate($object = null)
	{
		if (is_object($object) && !empty($object->date_creation)) {
			return (int) $object->date_creation;
		}

		return dol_now();
	}

	/**
	 * Generate next reference from a Dolibarr mask.
	 *
	 * @param string $mask Reference mask
	 * @param Raccordement|null $object Current object
	 * @return string|int
	 */
	protected function getNextValueFromMask($mask, $object = null)
	{
		require_once DOL_DOCUMENT_ROOT.'/core/lib/functions2.lib.php';

		if ($mask === '') {
			$this->error = 'NotConfigured';
			return 0;
		}

		$where = ' AND entity IN ('.self::getRaccordementReferenceEntityList($object).')';

		return get_next_value($this->db, $mask, 'pvproc_raccordement', 'ref', $where, '', $this->getReferenceDate($object), 'next', false);
	}
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
	 * Name.
	 *
	 * @var string
	 */
	public $name = 'PvprocStandardNumberingName';

	/**
	 * Standard mask.
	 *
	 * @var string
	 */
	public $mask = 'DDR{yyyy}{mm}-{0000}';

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
		return 'DDR'.dol_print_date(dol_now(), '%Y%m').'-0001';
	}

	/**
	 * Tell whether this model can be activated.
	 *
	 * @return bool
	 */
	public function canBeActivated($object = null)
	{
		return true;
	}

	/**
	 * Return next value.
	 *
	 * @param Societe|null $objsoc Thirdparty
	 * @param Raccordement|null $object Object
	 * @return string|int
	 */
	public function getNextValue($objsoc = null, $object = null)
	{
		if (!is_object($object) && is_object($objsoc) && property_exists($objsoc, 'table_element')) {
			$object = $objsoc;
		}

		return $this->getNextValueFromMask(getDolGlobalString('PROCEDURESPV_RACCORDEMENT_STANDARD_MASK', $this->mask), $object);
	}
}

/**
 * Advanced Raccordement numbering module.
 */
class mod_pvproc_advanced extends ModeleNumRefRaccordement
{
	/**
	 * Version.
	 *
	 * @var string
	 */
	public $version = 'dolibarr';

	/**
	 * Name.
	 *
	 * @var string
	 */
	public $name = 'PvprocAdvancedNumberingName';

	/**
	 * Return numbering module information.
	 *
	 * @param Translate $langs Language handler
	 * @return string
	 */
	public function info($langs)
	{
		require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';

		$langs->load('bills');
		$form = new Form($this->db);
		$token = function_exists('currentToken') ? currentToken() : newToken();

		$text = $langs->trans('PvprocAdvancedNumberingDescription')."<br>\n";
		$text .= $langs->trans('GenericNumRefModelDesc')."<br>\n";
		$text .= '<form action="'.dol_escape_htmltag($_SERVER['PHP_SELF']).'" method="POST">';
		$text .= '<input type="hidden" name="token" value="'.dol_escape_htmltag($token).'">';
		$text .= '<input type="hidden" name="action" value="updateMask">';
		$text .= '<input type="hidden" name="maskconst" value="PROCEDURESPV_RACCORDEMENT_ADVANCED_MASK">';
		$text .= '<table class="nobordernopadding centpercent">';

		$tooltip = $langs->trans('GenericMaskCodes', $langs->transnoentities('Raccordement'), $langs->transnoentities('Raccordement'));
		$tooltip .= $langs->trans('GenericMaskCodes1');
		$tooltip .= '<br>'.$langs->trans('GenericMaskCodes2');
		$tooltip .= '<br>'.$langs->trans('GenericMaskCodes3');
		$tooltip .= $langs->trans('GenericMaskCodes4a', $langs->transnoentities('Raccordement'), $langs->transnoentities('Raccordement'));
		$tooltip .= $langs->trans('GenericMaskCodes5');
		$tooltip .= '<br>'.$langs->trans('GenericMaskCodes5b');

		$text .= '<tr><td>'.$langs->trans('Mask').':</td>';
		$text .= '<td class="right">'.$form->textwithpicto('<input type="text" class="flat minwidth175" name="maskvalue" value="'.dol_escape_htmltag(getDolGlobalString('PROCEDURESPV_RACCORDEMENT_ADVANCED_MASK', 'DDR{yyyy}{mm}-{0000}')).'">', $tooltip, 1, 'help').'</td>';
		$text .= '<td class="left" rowspan="2">&nbsp; <input type="submit" class="button button-edit" value="'.$langs->trans('Modify').'" name="Button"></td>';
		$text .= '</tr>';
		$text .= '</table>';
		$text .= '</form>';

		return $text;
	}

	/**
	 * Return example.
	 *
	 * @return string
	 */
	public function getExample()
	{
		global $langs;

		$numExample = $this->getNextValue();
		if (!$numExample) {
			return $langs->trans('NotConfigured');
		}

		return (string) $numExample;
	}

	/**
	 * Tell whether this model can be activated.
	 *
	 * @param Raccordement|null $object Current object
	 * @return bool
	 */
	public function canBeActivated($object = null)
	{
		return getDolGlobalString('PROCEDURESPV_RACCORDEMENT_ADVANCED_MASK', 'DDR{yyyy}{mm}-{0000}') !== '';
	}

	/**
	 * Return next value.
	 *
	 * @param Societe|null $objsoc Thirdparty
	 * @param Raccordement|null $object Object
	 * @return string|int
	 */
	public function getNextValue($objsoc = null, $object = null)
	{
		if (!is_object($object) && is_object($objsoc) && property_exists($objsoc, 'table_element')) {
			$object = $objsoc;
		}

		return $this->getNextValueFromMask(getDolGlobalString('PROCEDURESPV_RACCORDEMENT_ADVANCED_MASK', 'DDR{yyyy}{mm}-{0000}'), $object);
	}
}
