<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

require_once DOL_DOCUMENT_ROOT.'/core/class/commonobject.class.php';

/**
 * Business object Raccordement.
 */
class Raccordement extends CommonObject
{
	/** CARD-i applicability threshold, in kVA. */
	public const CARDI_POWER_THRESHOLD_KVA = 36.0;

	/**
	 * Module key.
	 *
	 * @var string
	 */
	public $module = 'procedurespv';

	/**
	 * Element identifier.
	 *
	 * @var string
	 */
	public $element = 'procedurespv_raccordement';

	/**
	 * Table element.
	 *
	 * @var string
	 */
	public $table_element = 'pvproc_raccordement';

	/**
	 * Picto.
	 *
	 * @var string
	 */
	public $picto = 'fa-plug';

	/**
	 * Multientity mode.
	 *
	 * @var int
	 */
	public $ismultientitymanaged = 1;

	/**
	 * Fields definition.
	 *
	 * @var array<string, array<string, mixed>>
	 */
	public $fields = array(
		'rowid' => array('type' => 'integer', 'label' => 'TechnicalID', 'enabled' => 1, 'visible' => -1, 'notnull' => 1, 'position' => 1),
		'entity' => array('type' => 'integer', 'label' => 'Entity', 'enabled' => 1, 'visible' => -1, 'notnull' => 1, 'default' => 1, 'position' => 5),
		'ref' => array('type' => 'varchar(128)', 'label' => 'Ref', 'enabled' => 1, 'visible' => 1, 'notnull' => 1, 'position' => 10, 'showoncombobox' => 1),
		'fk_soc' => array('type' => 'integer:Societe:societe/class/societe.class.php', 'label' => 'ThirdParty', 'enabled' => 1, 'visible' => 1, 'position' => 20),
		'fk_project' => array('type' => 'integer:Project:projet/class/project.class.php', 'label' => 'Project', 'enabled' => 1, 'visible' => 1, 'position' => 30),
		'fk_centrale_pv' => array('type' => 'integer', 'label' => 'CentralePV', 'enabled' => 1, 'visible' => 1, 'position' => 40),
		'site_source' => array('type' => 'varchar(32)', 'label' => 'SiteSource', 'enabled' => 1, 'visible' => 1, 'default' => 'local', 'position' => 50),
		'status' => array('type' => 'integer', 'label' => 'Status', 'enabled' => 1, 'visible' => 1, 'notnull' => 1, 'default' => 0, 'position' => 60),
		'type_exploitation' => array('type' => 'varchar(64)', 'label' => 'ExploitationType', 'enabled' => 1, 'visible' => 1, 'position' => 70),
		'puissance_installee_kwc' => array('type' => 'double(24,8)', 'label' => 'InstalledPowerKwc', 'enabled' => 1, 'visible' => 1, 'position' => 80),
		'puissance_injection_kva' => array('type' => 'double(24,8)', 'label' => 'InjectionPowerKva', 'enabled' => 1, 'visible' => 1, 'position' => 90),
		'prm' => array('type' => 'varchar(64)', 'label' => 'PRM', 'enabled' => 1, 'visible' => 1, 'position' => 100),
		'site_name_snapshot' => array('type' => 'varchar(255)', 'label' => 'SiteNameSnapshot', 'enabled' => 1, 'visible' => 1, 'position' => 110),
		'site_address_snapshot' => array('type' => 'varchar(255)', 'label' => 'SiteAddressSnapshot', 'enabled' => 1, 'visible' => 1, 'position' => 120),
		'site_zip_snapshot' => array('type' => 'varchar(20)', 'label' => 'SiteZipSnapshot', 'enabled' => 1, 'visible' => 1, 'position' => 130),
		'site_town_snapshot' => array('type' => 'varchar(128)', 'label' => 'SiteTownSnapshot', 'enabled' => 1, 'visible' => 1, 'position' => 140),
		'type_reseau' => array('type' => 'varchar(32)', 'label' => 'NetworkType', 'enabled' => 1, 'visible' => 1, 'position' => 150),
		'puissance_souscrite' => array('type' => 'varchar(64)', 'label' => 'SubscribedPower', 'enabled' => 1, 'visible' => 1, 'position' => 160),
		'date_collecte_envoi' => array('type' => 'datetime', 'label' => 'CollecteSentDate', 'enabled' => 1, 'visible' => 1, 'position' => 170),
		'date_collecte_ouverture' => array('type' => 'datetime', 'label' => 'CollecteOpenedDate', 'enabled' => 1, 'visible' => 1, 'position' => 180),
		'date_collecte_soumission' => array('type' => 'datetime', 'label' => 'CollecteSubmittedDate', 'enabled' => 1, 'visible' => 1, 'position' => 190),
		'date_mandat_signature' => array('type' => 'datetime', 'label' => 'MandatSignatureDate', 'enabled' => 1, 'visible' => 1, 'position' => 200),
		'date_mandat_validation' => array('type' => 'datetime', 'label' => 'MandatValidationDate', 'enabled' => 1, 'visible' => 1, 'position' => 210),
		'date_depot_enedis' => array('type' => 'datetime', 'label' => 'EnedisDepositDate', 'enabled' => 1, 'visible' => 1, 'position' => 220),
		'ref_enedis' => array('type' => 'varchar(128)', 'label' => 'EnedisReference', 'enabled' => 1, 'visible' => 1, 'position' => 230),
		'portail_utilise' => array('type' => 'varchar(128)', 'label' => 'PortalUsed', 'enabled' => 1, 'visible' => 1, 'position' => 232),
		'puissance_raccordement_demandee' => array('type' => 'double(24,8)', 'label' => 'RequestedConnectionPower', 'enabled' => 1, 'visible' => 1, 'position' => 234),
		'mono_tri_confirme' => array('type' => 'varchar(32)', 'label' => 'ConfirmedPhaseType', 'enabled' => 1, 'visible' => 1, 'position' => 236),
		'onduleurs' => array('type' => 'text', 'label' => 'Inverters', 'enabled' => 1, 'visible' => 1, 'position' => 238),
		'nombre_onduleurs' => array('type' => 'integer', 'label' => 'InverterCount', 'enabled' => 1, 'visible' => 1, 'position' => 239),
		'references_onduleurs' => array('type' => 'text', 'label' => 'InverterReferences', 'enabled' => 1, 'visible' => 1, 'position' => 240),
		'puissance_onduleurs' => array('type' => 'double(24,8)', 'label' => 'InverterPower', 'enabled' => 1, 'visible' => 1, 'position' => 241),
		'modules' => array('type' => 'text', 'label' => 'Modules', 'enabled' => 1, 'visible' => 1, 'position' => 242),
		'nombre_modules' => array('type' => 'integer', 'label' => 'ModuleCount', 'enabled' => 1, 'visible' => 1, 'position' => 243),
		'puissance_unitaire_modules' => array('type' => 'double(24,8)', 'label' => 'ModuleUnitPower', 'enabled' => 1, 'visible' => 1, 'position' => 244),
		'schema_unifilaire' => array('type' => 'varchar(255)', 'label' => 'SingleLineDiagram', 'enabled' => 1, 'visible' => 1, 'position' => 245),
		'plan_masse' => array('type' => 'varchar(255)', 'label' => 'SitePlan', 'enabled' => 1, 'visible' => 1, 'position' => 246),
		'plan_cadastral' => array('type' => 'varchar(255)', 'label' => 'CadastralPlan', 'enabled' => 1, 'visible' => 1, 'position' => 247),
		'bilan_puissance' => array('type' => 'varchar(255)', 'label' => 'PowerBalance', 'enabled' => 1, 'visible' => 1, 'position' => 248),
		'commentaire_technique' => array('type' => 'text', 'label' => 'TechnicalComment', 'enabled' => 1, 'visible' => 1, 'position' => 250),
		'demande_status' => array('type' => 'integer', 'label' => 'RequestStatus', 'enabled' => 1, 'visible' => 1, 'position' => 251),
		'cardi_required' => array('type' => 'integer', 'label' => 'CardiRequired', 'enabled' => 1, 'visible' => 1, 'position' => 252),
		'cardi_status' => array('type' => 'integer', 'label' => 'CardiStatus', 'enabled' => 1, 'visible' => 1, 'position' => 253),
		'cardi_date_demande' => array('type' => 'datetime', 'label' => 'CardiRequestDate', 'enabled' => 1, 'visible' => 1, 'position' => 254),
		'cardi_date_envoi_client' => array('type' => 'datetime', 'label' => 'CardiClientSentDate', 'enabled' => 1, 'visible' => 1, 'position' => 255),
		'cardi_date_retour_client' => array('type' => 'datetime', 'label' => 'CardiClientReturnDate', 'enabled' => 1, 'visible' => 1, 'position' => 256),
		'cardi_date_validation' => array('type' => 'datetime', 'label' => 'CardiValidationDate', 'enabled' => 1, 'visible' => 1, 'position' => 257),
		'cardi_document' => array('type' => 'varchar(255)', 'label' => 'CardiDocument', 'enabled' => 1, 'visible' => 1, 'position' => 258),
		'cardi_commentaire' => array('type' => 'text', 'label' => 'CardiComment', 'enabled' => 1, 'visible' => 1, 'position' => 259),
		'mes_required' => array('type' => 'integer', 'label' => 'MESRequired', 'enabled' => 1, 'visible' => 1, 'position' => 262),
		'mes_status' => array('type' => 'integer', 'label' => 'MESStatus', 'enabled' => 1, 'visible' => 1, 'position' => 263),
		'date_demande_mes' => array('type' => 'datetime', 'label' => 'MESRequestDate', 'enabled' => 1, 'visible' => 1, 'position' => 264),
		'date_previsionnelle_mes' => array('type' => 'datetime', 'label' => 'MESPlannedDate', 'enabled' => 1, 'visible' => 1, 'position' => 265),
		'date_reelle_mes' => array('type' => 'datetime', 'label' => 'MESRealDate', 'enabled' => 1, 'visible' => 1, 'position' => 266),
		'date_consuel' => array('type' => 'datetime', 'label' => 'ConsuelDate', 'enabled' => 1, 'visible' => 1, 'position' => 268),
		'ref_consuel' => array('type' => 'varchar(128)', 'label' => 'ConsuelReference', 'enabled' => 1, 'visible' => 1, 'position' => 269),
		'injection_autorisee' => array('type' => 'integer', 'label' => 'InjectionAuthorized', 'enabled' => 1, 'visible' => 1, 'position' => 270),
		'date_autorisation_injection' => array('type' => 'datetime', 'label' => 'InjectionAuthorizationDate', 'enabled' => 1, 'visible' => 1, 'position' => 271),
		'ref_intervention_enedis' => array('type' => 'varchar(128)', 'label' => 'EnedisInterventionReference', 'enabled' => 1, 'visible' => 1, 'position' => 272),
		'commentaire_mes' => array('type' => 'text', 'label' => 'MESComment', 'enabled' => 1, 'visible' => 1, 'position' => 273),
		'date_snapshot' => array('type' => 'datetime', 'label' => 'SnapshotDate', 'enabled' => 1, 'visible' => 1, 'position' => 280),
		'date_mes' => array('type' => 'datetime', 'label' => 'MESDate', 'enabled' => 1, 'visible' => 1, 'position' => 281),
		'fk_user_author' => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'Author', 'enabled' => 1, 'visible' => 1, 'position' => 260),
		'fk_user_resp' => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'Responsible', 'enabled' => 1, 'visible' => 1, 'position' => 270),
		'fk_user_modif' => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'UserModif', 'enabled' => 1, 'visible' => -1, 'position' => 280),
		'note_private' => array('type' => 'text', 'label' => 'NotePrivate', 'enabled' => 1, 'visible' => 0, 'position' => 500),
		'note_public' => array('type' => 'text', 'label' => 'NotePublic', 'enabled' => 1, 'visible' => 0, 'position' => 510),
		'datec' => array('type' => 'datetime', 'label' => 'DateCreation', 'enabled' => 1, 'visible' => -1, 'position' => 5000),
		'tms' => array('type' => 'timestamp', 'label' => 'DateModification', 'enabled' => 1, 'visible' => -1, 'position' => 5010),
		'import_key' => array('type' => 'varchar(14)', 'label' => 'ImportId', 'enabled' => 1, 'visible' => -2, 'position' => 5020),
	);

	public $rowid;
	public $id;
	/** @var string|null Native Agenda event description, populated only while calling a trigger. */
	public $actionmsg;
	/** @var string|null Native Agenda event label, populated only while calling a trigger. */
	public $actionmsg2;
	public $entity;
	public $ref;
	public $fk_soc;
	/** @var int Native alias used by Dolibarr contact and agenda templates. */
	public $socid;
	public $fk_project;
	public $fk_centrale_pv;
	public $site_source;
	public $status;
	public $type_exploitation;
	public $puissance_installee_kwc;
	public $puissance_injection_kva;
	public $prm;
	public $site_name_snapshot;
	public $site_address_snapshot;
	public $site_zip_snapshot;
	public $site_town_snapshot;
	public $type_reseau;
	public $puissance_souscrite;
	public $date_collecte_envoi;
	public $date_collecte_ouverture;
	public $date_collecte_soumission;
	public $date_mandat_signature;
	public $date_mandat_validation;
	public $date_depot_enedis;
	public $ref_enedis;
	public $portail_utilise;
	public $puissance_raccordement_demandee;
	public $mono_tri_confirme;
	public $onduleurs;
	public $nombre_onduleurs;
	public $references_onduleurs;
	public $puissance_onduleurs;
	public $modules;
	public $nombre_modules;
	public $puissance_unitaire_modules;
	public $schema_unifilaire;
	public $plan_masse;
	public $plan_cadastral;
	public $bilan_puissance;
	public $commentaire_technique;
	public $demande_status;
	public $cardi_required;
	public $cardi_status;
	public $cardi_date_demande;
	public $cardi_date_envoi_client;
	public $cardi_date_retour_client;
	public $cardi_date_validation;
	public $cardi_document;
	public $cardi_commentaire;
	public $mes_required;
	public $mes_status;
	public $date_demande_mes;
	public $date_previsionnelle_mes;
	public $date_reelle_mes;
	public $date_consuel;
	public $ref_consuel;
	public $injection_autorisee;
	public $date_autorisation_injection;
	public $ref_intervention_enedis;
	public $commentaire_mes;
	public $date_snapshot;
	public $date_mes;
	public $fk_user_author;
	public $fk_user_resp;
	public $fk_user_modif;
	public $note_private;
	public $note_public;
	public $datec;
	public $tms;
	public $import_key;

	/**
	 * Return the stable external element type used to resolve this object.
	 *
	 * The historical element identifier already contains the module prefix, so
	 * CommonObject::getElementType() would otherwise prefix it a second time.
	 *
	 * @return string
	 */
	public function getElementType()
	{
		return (string) $this->element;
	}

	/**
	 * Check whether CARD-i applies to this grid-connection request.
	 *
	 * CARD-i applies when either the requested connection power or the
	 * withdrawal subscribed power is strictly greater than 36 kVA.
	 *
	 * @return bool
	 */
	public function isCardiApplicable()
	{
		$requestedPower = price2num((string) $this->puissance_raccordement_demandee);
		$withdrawalPower = price2num((string) $this->puissance_souscrite);

		return (is_numeric($requestedPower) && (float) $requestedPower > self::CARDI_POWER_THRESHOLD_KVA)
			|| (is_numeric($withdrawalPower) && (float) $withdrawalPower > self::CARDI_POWER_THRESHOLD_KVA);
	}

	/**
	 * Constructor.
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		$this->db = $db;
		$this->status = 0;
		$this->site_source = 'local';
	}

	/**
	 * Create object.
	 *
	 * @param User $user User creating
	 * @param int $notrigger 1 disables triggers
	 * @return int
	 */
	public function create($user, $notrigger = 0)
	{
		global $conf;

		$this->entity = (int) $conf->entity;
		$this->fk_user_author = is_object($user) ? (int) $user->id : 0;

		if (empty($this->ref)) {
			$this->ref = $this->getNextNumRef();
		}

		if (empty($this->ref)) {
			$this->error = 'ErrorNoRaccordementRef';
			$this->errors[] = $this->error;
			return -1;
		}

		$this->db->begin();
		$result = $this->createCommon($user, 1);
		if ($result > 0 && !$notrigger) {
			$triggerResult = $this->callProceduresPVTrigger('PVPROC_RACCORDEMENT_CREATE', $user, 'raccordement_created');
			if ($triggerResult < 0) {
				$this->db->rollback();
				return -1;
			}
		}
		if ($result <= 0) {
			$this->db->rollback();
			return $result;
		}
		$this->db->commit();

		return $result;
	}

	/**
	 * Fetch object.
	 *
	 * @param int $id Object id
	 * @param string|null $ref Object ref
	 * @param int $checkEntity 1 checks current entity visibility
	 * @return int
	 */
	public function fetch($id, $ref = null, $checkEntity = 1)
	{
		global $conf;

		$result = $this->fetchCommon($id, $ref);
		if ($result <= 0) {
			return $result;
		}
		$this->socid = (int) $this->fk_soc;
		$this->date_creation = $this->datec;
		$this->date_modification = $this->tms;
		$this->user_creation_id = (int) $this->fk_user_author;
		$this->user_modification_id = (int) $this->fk_user_modif;

		if ($checkEntity) {
			$authorizedEntities = function_exists('getEntity') ? explode(',', getEntity($this->element)) : array((string) ((int) $conf->entity));
			if (!in_array((string) ((int) $this->entity), $authorizedEntities, true)) {
				$this->error = 'ErrorRecordNotFound';
				$this->errors[] = $this->error;
				return -1;
			}
		}

		return $result;
	}

	/**
	 * Notify native integrations after a contact link mutation.
	 *
	 * Contact links are written through CommonObject with their legacy contact
	 * triggers disabled. The raccordement exposes the change through its single
	 * CRUD update trigger and a stable business context instead.
	 *
	 * @param User $user User performing the operation
	 * @param string $operation Contact operation (add, delete or status)
	 * @param int $contactLineId Native element_contact row id when available
	 * @return int
	 */
	public function triggerContactUpdate($user, $operation, $contactLineId = 0)
	{
		$this->context['contact_operation'] = $operation;
		$this->context['contact_line_id'] = $contactLineId;

		return $this->triggerUserAction($user, 'contact_change', array('contacts'));
	}

	/**
	 * Expose a mutation of a child record through the parent CRUD update event.
	 *
	 * @param User $user User performing the action
	 * @param string $reason Stable business reason
	 * @param array<int,string> $changedFields Aggregate fields or relations changed
	 * @param string $details Optional readable event details
	 * @return int
	 */
	public function triggerUserAction($user, $reason, $changedFields = array(), $details = '')
	{
		if (!isset($this->context) || !is_array($this->context)) {
			$this->context = array();
		}
		$this->context['trigger_reason'] = $reason;
		$this->context['changed_fields'] = array_values($changedFields);
		if ($details !== '') {
			$this->context['agenda_details'] = $details;
		}

		return $this->callProceduresPVTrigger('PVPROC_RACCORDEMENT_UPDATE', $user, 'raccordement_updated');
	}

	/**
	 * Check that a native element_contact row belongs to this raccordement.
	 *
	 * @param int $contactLineId Native element_contact row id
	 * @return bool
	 */
	public function hasLinkedContactLine($contactLineId)
	{
		if ($contactLineId <= 0) {
			return false;
		}

		foreach (array('internal', 'external') as $source) {
			$contacts = $this->liste_contact(-1, $source);
			if (!is_array($contacts)) {
				continue;
			}
			foreach ($contacts as $contact) {
				if (isset($contact['rowid']) && (int) $contact['rowid'] === $contactLineId) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Update object.
	 *
	 * @param User $user User updating
	 * @param int $notrigger 1 disables triggers
	 * @return int
	 */
	public function update($user, $notrigger = 0)
	{
		$this->fk_user_modif = is_object($user) ? (int) $user->id : 0;

		$this->db->begin();
		$result = $this->updateCommon($user, 1);
		if ($result > 0 && !$notrigger) {
			$triggerResult = $this->callProceduresPVTrigger('PVPROC_RACCORDEMENT_UPDATE', $user, 'raccordement_updated');
			if ($triggerResult < 0) {
				$this->db->rollback();
				return -1;
			}
		}
		if ($result <= 0) {
			$this->db->rollback();
			return $result;
		}
		$this->db->commit();

		return $result;
	}

	/**
	 * Delete object.
	 *
	 * @param User $user User deleting
	 * @param int $notrigger 1 disables triggers
	 * @return int
	 */
	public function delete($user, $notrigger = 0)
	{
		$this->db->begin();
		$result = $this->deleteCommon($user, 1);
		if ($result > 0 && !$notrigger) {
			$triggerResult = $this->callProceduresPVTrigger('PVPROC_RACCORDEMENT_DELETE', $user, 'raccordement_deleted');
			if ($triggerResult < 0) {
				$this->db->rollback();
				return -1;
			}
		}
		if ($result <= 0) {
			$this->db->rollback();
			return $result;
		}
		$this->db->commit();

		return $result;
	}

	/**
	 * Prepare the native Agenda context and execute one stable CRUD trigger.
	 *
	 * Dolibarr's Agenda trigger consumes actionmsg/actionmsg2 and creates the
	 * ActionComm according to MAIN_AGENDA_ACTIONAUTO_<TRIGGER_CODE>.
	 *
	 * @param string $triggerCode CRUD trigger code
	 * @param User $user Acting user
	 * @param string $defaultReason Fallback business reason
	 * @return int
	 */
	private function callProceduresPVTrigger($triggerCode, $user, $defaultReason)
	{
		global $langs;
		/** @var Translate $langs */

		if (!isset($this->context) || !is_array($this->context)) {
			$this->context = array();
		}

		$reason = isset($this->context['trigger_reason']) && is_string($this->context['trigger_reason']) && $this->context['trigger_reason'] !== ''
			? $this->context['trigger_reason']
			: $defaultReason;
		$labelKey = $this->getAgendaLabelKey($reason);
		$langs->loadLangs(array('procedurespv@procedurespv'));
		$label = $langs->transnoentitiesnoconv($labelKey);
		if ($label === $labelKey) {
			$label = $langs->transnoentitiesnoconv('AgendaRaccordementUpdated');
		}
		$details = isset($this->context['agenda_details']) && is_string($this->context['agenda_details'])
			? trim($this->context['agenda_details'])
			: '';

		$objectvars = get_object_vars($this);
		$hadActionMsg = array_key_exists('actionmsg', $objectvars);
		$hadActionMsg2 = array_key_exists('actionmsg2', $objectvars);
		$hadActionTypeCode = array_key_exists('actiontypecode', $objectvars);
		$oldActionMsg = $hadActionMsg ? $this->actionmsg : null;
		$oldActionMsg2 = $hadActionMsg2 ? $this->actionmsg2 : null;
		$oldActionTypeCode = $hadActionTypeCode ? $this->actiontypecode : null;

		$this->context['trigger_reason'] = $reason;
		$this->context['actionmsg2'] = $label.' - '.(string) $this->ref;
		$this->context['actionmsg'] = $details !== ''
			? $details
			: $langs->transnoentitiesnoconv('AgendaRaccordementEventDetails', $label, (string) $this->ref);
		$this->actiontypecode = 'AC_OTH_AUTO';
		$this->actionmsg2 = $this->context['actionmsg2'];
		$this->actionmsg = $this->context['actionmsg'];

		$result = $this->call_trigger($triggerCode, $user);
		foreach (array('trigger_reason', 'changed_fields', 'agenda_details', 'agenda_label_key', 'actionmsg', 'actionmsg2', 'actionmsgmore', 'actionmsg2more', 'old_status', 'new_status', 'contact_operation', 'contact_line_id') as $contextKey) {
			unset($this->context[$contextKey]);
		}
		if ($hadActionMsg) {
			$this->actionmsg = $oldActionMsg;
		} else {
			unset($this->actionmsg);
		}
		if ($hadActionMsg2) {
			$this->actionmsg2 = $oldActionMsg2;
		} else {
			unset($this->actionmsg2);
		}
		if ($hadActionTypeCode) {
			$this->actiontypecode = $oldActionTypeCode;
		} else {
			unset($this->actiontypecode);
		}

		return $result;
	}

	/**
	 * Resolve a business reason to a translated native Agenda label.
	 *
	 * @param string $reason Stable business reason
	 * @return string Translation key
	 */
	private function getAgendaLabelKey($reason)
	{
		if (isset($this->context['agenda_label_key']) && is_string($this->context['agenda_label_key']) && $this->context['agenda_label_key'] !== '') {
			return $this->context['agenda_label_key'];
		}

		$labels = array(
			'raccordement_created' => 'AgendaRaccordementCreated',
			'raccordement_updated' => 'AgendaRaccordementUpdated',
			'raccordement_deleted' => 'AgendaRaccordementDeleted',
			'status_change' => 'AgendaRaccordementStatusUpdated',
			'snapshot_freeze' => 'SnapshotFrozen',
			'contact_change' => 'AgendaRaccordementContactUpdated',
			'beneficiary_synchronized' => 'AgendaBeneficiarySynchronized',
			'centralepv_site_prefill' => 'AgendaCentraleSiteDataSynchronized',
			'draft_field_update' => 'AgendaRaccordementUpdated',
			'equipment_changed' => 'EquipmentUpdatedAgendaEvent',
			'collection_link_created' => 'AgendaCollecteLinkCreated',
			'collection_reopened' => 'AgendaCollecteReopened',
			'collection_link_revoked' => 'AgendaCollecteLinkRevoked',
			'collection_document_uploaded' => 'AgendaCollectionDocumentUploaded',
			'collection_document_validated' => 'AgendaCollectionDocumentValidated',
			'collection_document_refused' => 'AgendaCollectionDocumentRefused',
			'mandate_validated' => 'AgendaMandateValidated',
			'mandate_refused' => 'AgendaMandateRefused',
			'collection_validated' => 'AgendaCollectionValidated',
			'cardi_update' => 'AgendaCardiUpdated',
			'enedis_request_update' => 'AgendaEnedisRequestUpdated',
			'mes_update' => 'AgendaMesUpdated',
			'document_change' => 'AgendaRaccordementDocumentUpdated',
			'public_collecte_opened' => 'AgendaCollecteOpened',
			'public_collecte_saved_mobile' => 'AgendaCollecteSavedForMobile',
			'public_collecte_submitted' => 'AgendaCollecteSubmitted',
			'convention_created' => 'AgendaConventionCreated',
			'convention_updated' => 'AgendaConventionUpdated',
			'convention_status_changed' => 'AgendaConventionStatusUpdated',
			'relance_created' => 'AgendaRelanceCreated',
			'relance_updated' => 'AgendaRelanceUpdated',
			'relance_sent' => 'AgendaRelanceSent',
			'relance_canceled' => 'AgendaRelanceCanceled',
		);

		return isset($labels[$reason]) ? $labels[$reason] : 'AgendaRaccordementUpdated';
	}

	/**
	 * Initialize specimen.
	 *
	 * @return void
	 */
	public function initAsSpecimen()
	{
		$this->id = 0;
		$this->rowid = 0;
		$this->ref = 'DDR'.dol_print_date(dol_now(), '%Y%m').'-0001';
		$this->status = 0;
		$this->type_exploitation = 'autoconsommation_surplus';
		$this->puissance_installee_kwc = 6.0;
		$this->puissance_injection_kva = 6.0;
		$this->site_source = 'local';
	}

	/**
	 * Return next reference.
	 *
	 * @return string|int
	 */
	public function getNextNumRef()
	{
		$model = getDolGlobalString('PROCEDURESPV_RACCORDEMENT_ADDON', 'mod_pvproc_standard');

		dol_include_once('/procedurespv/core/modules/procedurespv/modules_raccordement.php');
		if (!class_exists($model)) {
			$model = 'mod_pvproc_standard';
		}

		/** @var ModeleNumRefRaccordement $numbering */
		$numbering = new $model($this->db);

		return $numbering->getNextValue(null, $this);
	}

	/**
	 * Alias kept for the planned API.
	 *
	 * @return string|int
	 */
	public function generateRef()
	{
		return $this->getNextNumRef();
	}

	/**
	 * Return object URL.
	 *
	 * @param int $withpicto Include picto
	 * @param string $option Option
	 * @return string
	 */
	public function getNomUrl($withpicto = 0, $option = '')
	{
		global $langs;

		$result = '';
		$label = img_picto('', $this->picto).' <u>'.$langs->trans('Raccordement').'</u>';
		$label .= '<br><b>'.$langs->trans('Ref').':</b> '.dol_escape_htmltag((string) $this->ref);
		$linkclose = '';

		if (!empty($option) && $option === 'nolink') {
			$linkclose = '';
		} else {
			$result .= '<a href="'.dol_buildpath('/procedurespv/raccordement/card.php', 1).'?id='.(int) $this->id.'"';
			$result .= ' title="'.dol_escape_htmltag($label, 1).'" class="classfortooltip">';
			$linkclose = '</a>';
		}

		if ($withpicto) {
			$result .= img_object(($withpicto === 2 ? $langs->trans('ShowRaccordement') : ''), $this->picto).' ';
		}

		$result .= dol_escape_htmltag((string) $this->ref).$linkclose;

		return $result;
	}

	/**
	 * Return status label.
	 *
	 * @param int $mode Display mode
	 * @return string
	 */
	public function getLibStatut($mode = 0)
	{
		return $this->LibStatut((int) $this->status, $mode);
	}

	/**
	 * Return status label.
	 *
	 * @param int $status Status
	 * @param int $mode Display mode
	 * @return string
	 */
	public function LibStatut($status, $mode = 0)
	{
		global $langs;

		$labels = self::getStatusLabels();
		$labelKey = isset($labels[$status]) ? $labels[$status] : 'RaccordementStatusUnknown';
		$label = $langs->trans($labelKey);
		$statusTypes = array(
			-1 => 8,
			0 => 0,
			1 => 0,
			2 => 1,
			3 => 1,
			4 => 1,
			5 => 3,
			6 => 3,
			7 => 3,
			8 => 1,
			9 => 1,
			10 => 3,
			11 => 1,
			12 => 4,
			13 => 3,
			14 => 1,
			15 => 4,
			16 => 6,
		);
		$statusType = $statusTypes[(int) $status] ?? 0;

		if ($mode === 0) {
			return $label;
		}

		return dolGetStatus($label, '', '', 'status'.$statusType, $mode);
	}

	/**
	 * Set object status.
	 *
	 * @param User $user User updating
	 * @param int $status New status
	 * @return int
	 */
	public function setStatus($user, $status)
	{
		$oldStatus = (int) $this->status;
		$this->status = (int) $status;
		if (empty($this->context['trigger_reason'])) {
			$this->context['trigger_reason'] = 'status_change';
		}
		$this->context['changed_fields'] = array('status');
		$this->context['old_status'] = $oldStatus;
		$this->context['new_status'] = (int) $status;

		return $this->update($user);
	}

	/**
	 * Freeze site snapshot for ENEDIS deposit.
	 *
	 * @param User $user User updating
	 * @return int
	 */
	public function freezeSnapshot($user)
	{
		if (!empty($this->date_snapshot)) {
			$this->error = 'SnapshotAlreadyFrozen';
			return -1;
		}

		$this->date_snapshot = dol_now();
		$this->context['trigger_reason'] = 'snapshot_freeze';
		$this->context['changed_fields'] = array('date_snapshot');

		return $this->update($user);
	}

	/**
	 * Tell whether Centrale PV can be used.
	 *
	 * @return bool
	 */
	public function canUseCentralePV()
	{
		if (!function_exists('isModEnabled') || getDolGlobalInt('PROCEDURESPV_USE_CENTRALEPV_IF_AVAILABLE', 1) <= 0) {
			return false;
		}

		foreach (array('powerplantpv', 'centralepv', 'centrale_pv', 'centralespv') as $moduleKey) {
			if (isModEnabled($moduleKey)) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Placeholder for later Centrale PV adapter.
	 *
	 * @param int $fk_centrale_pv Centrale PV id
	 * @return int
	 */
	public function fetchFromCentralePV($fk_centrale_pv)
	{
		$this->fk_centrale_pv = (int) $fk_centrale_pv;

		return $this->fk_centrale_pv > 0 ? 1 : 0;
	}

	/**
	 * Return next business action label.
	 *
	 * @return string
	 */
	public function getNextAction()
	{
		$actions = array(
			0 => 'NextActionPrepareCollecte',
			1 => 'NextActionSendCollecte',
			2 => 'NextActionWaitClient',
			3 => 'NextActionWaitSubmission',
			4 => 'NextActionInternalControl',
			5 => 'NextActionCompleteInternalData',
			6 => 'NextActionPrepareEnedisDeposit',
			7 => 'NextActionDepositEnedis',
			8 => 'NextActionFollowEnedis',
			9 => 'NextActionFollowEnedis',
			10 => 'NextActionTreatComplement',
			11 => 'NextActionSendConvention',
			12 => 'NextActionPrepareMES',
			13 => 'NextActionRequestMES',
			14 => 'NextActionFollowMES',
			15 => 'NextActionCompleteInternalData',
			16 => 'NextActionNone',
			-1 => 'NextActionNone',
		);

		return isset($actions[(int) $this->status]) ? $actions[(int) $this->status] : 'NextActionNone';
	}

	/**
	 * Return blocking reason label.
	 *
	 * @return string
	 */
	public function getBlockingReason()
	{
		$reasons = array(
			2 => 'BlockingReasonClient',
			3 => 'BlockingReasonClient',
			4 => 'BlockingReasonInternal',
			5 => 'BlockingReasonInternal',
			8 => 'BlockingReasonEnedis',
			9 => 'BlockingReasonEnedis',
			10 => 'BlockingReasonInternal',
			11 => 'BlockingReasonConvention',
			13 => 'BlockingReasonMES',
			14 => 'BlockingReasonMES',
		);

		return isset($reasons[(int) $this->status]) ? $reasons[(int) $this->status] : 'BlockingReasonNone';
	}

	/**
	 * Return status labels.
	 *
	 * @return array<int, string>
	 */
	public static function getStatusLabels()
	{
		return array(
			0 => 'RaccordementStatusDraft',
			1 => 'RaccordementStatusCollecteToSend',
			2 => 'RaccordementStatusCollecteSent',
			3 => 'RaccordementStatusCollecteOpened',
			4 => 'RaccordementStatusCollecteSubmitted',
			5 => 'RaccordementStatusToControl',
			6 => 'RaccordementStatusToCompleteInternal',
			7 => 'RaccordementStatusReadyForEnedisDeposit',
			8 => 'RaccordementStatusDepositedEnedis',
			9 => 'RaccordementStatusInstructionEnedis',
			10 => 'RaccordementStatusComplementRequested',
			11 => 'RaccordementStatusConventionReceived',
			12 => 'RaccordementStatusConventionSigned',
			13 => 'RaccordementStatusMesToRequest',
			14 => 'RaccordementStatusMesRequested',
			15 => 'RaccordementStatusMesDone',
			16 => 'RaccordementStatusClosed',
			-1 => 'RaccordementStatusCanceled',
		);
	}
}
