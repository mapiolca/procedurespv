<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

require_once DOL_DOCUMENT_ROOT.'/core/triggers/dolibarrtriggers.class.php';

/**
 * ProceduresPV trigger interface.
 *
 * Raccordement methods expose stable CRUD events. Business details are
 * carried by the object context. The native Agenda module consumes the
 * c_action_trigger declaration and remains the single ActionComm creator.
 */
class InterfaceProceduresPVTriggers extends DolibarrTriggers
{
	/**
	 * Constructor.
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		parent::__construct($db);
		$this->family = 'Les Métiers du Bâtiment';
		$this->description = 'ProceduresPV CRUD triggers';
		$this->version = self::VERSIONS['dev'];
		$this->picto = 'fa-solar-panel';
	}

	/**
	 * Receive Dolibarr business events.
	 *
	 * @param string $action Event code
	 * @param CommonObject $object Business object
	 * @param User $user Acting user
	 * @param Translate $langs Translations
	 * @param Conf $conf Configuration
	 * @return int 0 when no downstream treatment is required
	 */
	public function runTrigger($action, $object, User $user, Translate $langs, Conf $conf)
	{
		if (!isModEnabled('procedurespv')) {
			return 0;
		}

		if (!in_array($action, array('PVPROC_RACCORDEMENT_CREATE', 'PVPROC_RACCORDEMENT_UPDATE', 'PVPROC_RACCORDEMENT_DELETE'), true)) {
			return 0;
		}

		// The CRUD event is intentionally exposed for native integrations.
		// Do not create ActionComm here: Agenda's ActionsAuto trigger owns it.
		return 0;
	}
}
