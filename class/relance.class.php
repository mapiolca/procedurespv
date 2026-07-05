<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * Raccordement reminder.
 */
class Relance
{
	public const STATUS_PLANNED = 0;
	public const STATUS_SENT = 1;
	public const STATUS_CANCELED = 2;

	/**
	 * Database handler.
	 *
	 * @var DoliDB
	 */
	private $db;

	public $id;
	public $rowid;
	public $entity;
	public $fk_raccordement;
	public $type_relance;
	public $target_type;
	public $target_id;
	public $destinataire_email;
	public $date_prevue;
	public $date_envoi;
	public $canal;
	public $status;
	public $modele_utilise;
	public $resultat;
	public $commentaire;
	public $fk_actioncomm;
	public $datec;
	public $tms;
	public $import_key;
	public $error = '';
	public $errors = array();

	/**
	 * Constructor.
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		$this->db = $db;
		$this->status = self::STATUS_PLANNED;
		$this->canal = 'email';
	}

	/**
	 * Fetch one reminder.
	 *
	 * @param int $id Reminder id
	 * @return int
	 */
	public function fetch($id)
	{
		global $conf;

		$entityFilter = function_exists('getEntity') ? getEntity('procedurespv_raccordement') : (string) ((int) $conf->entity);
		$sql = 'SELECT rowid, entity, fk_raccordement, type_relance, target_type, target_id, destinataire_email, date_prevue, date_envoi,';
		$sql .= ' canal, status, modele_utilise, resultat, commentaire, fk_actioncomm, datec, tms, import_key';
		$sql .= ' FROM '.MAIN_DB_PREFIX.'pvproc_relance';
		$sql .= ' WHERE rowid = '.((int) $id);
		$sql .= ' AND entity IN ('.$entityFilter.')';

		return $this->fetchFromSql($sql);
	}

	/**
	 * Fetch reminders linked to raccordement.
	 *
	 * @param int $fkRaccordement Raccordement id
	 * @return array<int, Relance>
	 */
	public function fetchAllByRaccordement($fkRaccordement)
	{
		global $conf;

		$list = array();
		$entityFilter = function_exists('getEntity') ? getEntity('procedurespv_raccordement') : (string) ((int) $conf->entity);
		$sql = 'SELECT rowid, entity, fk_raccordement, type_relance, target_type, target_id, destinataire_email, date_prevue, date_envoi,';
		$sql .= ' canal, status, modele_utilise, resultat, commentaire, fk_actioncomm, datec, tms, import_key';
		$sql .= ' FROM '.MAIN_DB_PREFIX.'pvproc_relance';
		$sql .= ' WHERE fk_raccordement = '.((int) $fkRaccordement);
		$sql .= ' AND entity IN ('.$entityFilter.')';
		$sql .= ' ORDER BY status ASC, date_prevue ASC, rowid DESC';

		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			$this->errors[] = $this->error;
			return $list;
		}

		while (is_object($obj = $this->db->fetch_object($resql))) {
			$relance = new self($this->db);
			$relance->setVarsFromObj($obj);
			$list[(int) $relance->id] = $relance;
		}

		return $list;
	}

	/**
	 * Create reminder.
	 *
	 * @param Raccordement $raccordement Parent object
	 * @param array<string, string|int|null> $data Input data
	 * @return int
	 */
	public function create($raccordement, array $data)
	{
		$sql = 'INSERT INTO '.MAIN_DB_PREFIX.'pvproc_relance (';
		$sql .= 'entity, fk_raccordement, type_relance, target_type, target_id, destinataire_email, date_prevue, date_envoi, canal, status, modele_utilise, resultat, commentaire, fk_actioncomm, datec';
		$sql .= ') VALUES (';
		$sql .= ((int) $raccordement->entity).', ';
		$sql .= ((int) $raccordement->id).', ';
		$sql .= $this->quote((string) $data['type_relance']).', ';
		$sql .= $this->quote((string) $data['target_type']).', ';
		$sql .= $this->intOrNull($data['target_id']).', ';
		$sql .= $this->quote((string) $data['destinataire_email']).', ';
		$sql .= $this->dateToSql($data['date_prevue']).', ';
		$sql .= $this->dateToSql($data['date_envoi']).', ';
		$sql .= $this->quote((string) $data['canal']).', ';
		$sql .= ((int) $data['status']).', ';
		$sql .= $this->quote((string) $data['modele_utilise']).', ';
		$sql .= $this->quote((string) $data['resultat']).', ';
		$sql .= $this->quote((string) $data['commentaire']).', ';
		$sql .= $this->intOrNull($data['fk_actioncomm']).', ';
		$sql .= "'".$this->db->idate(dol_now())."'";
		$sql .= ')';

		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			$this->errors[] = $this->error;
			return -1;
		}

		$this->id = (int) $this->db->last_insert_id(MAIN_DB_PREFIX.'pvproc_relance');
		$this->rowid = $this->id;

		return $this->id;
	}

	/**
	 * Update reminder.
	 *
	 * @param array<string, string|int|null> $data Input data
	 * @return int
	 */
	public function update(array $data)
	{
		if ((int) $this->id <= 0) {
			return -1;
		}

		$sql = 'UPDATE '.MAIN_DB_PREFIX.'pvproc_relance SET';
		$sql .= ' type_relance = '.$this->quote((string) $data['type_relance']);
		$sql .= ', target_type = '.$this->quote((string) $data['target_type']);
		$sql .= ', target_id = '.$this->intOrNull($data['target_id']);
		$sql .= ', destinataire_email = '.$this->quote((string) $data['destinataire_email']);
		$sql .= ', date_prevue = '.$this->dateToSql($data['date_prevue']);
		$sql .= ', date_envoi = '.$this->dateToSql($data['date_envoi']);
		$sql .= ', canal = '.$this->quote((string) $data['canal']);
		$sql .= ', status = '.((int) $data['status']);
		$sql .= ', modele_utilise = '.$this->quote((string) $data['modele_utilise']);
		$sql .= ', resultat = '.$this->quote((string) $data['resultat']);
		$sql .= ', commentaire = '.$this->quote((string) $data['commentaire']);
		$sql .= ', fk_actioncomm = '.$this->intOrNull($data['fk_actioncomm']);
		$sql .= ' WHERE rowid = '.((int) $this->id);
		$sql .= ' AND fk_raccordement = '.((int) $this->fk_raccordement);

		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			$this->errors[] = $this->error;
			return -1;
		}

		return 1;
	}

	/**
	 * Mark reminder as sent.
	 *
	 * @param int $fkActioncomm Native agenda event id
	 * @return int
	 */
	public function markSent($fkActioncomm = 0)
	{
		if ((int) $this->id <= 0) {
			return -1;
		}

		$sql = 'UPDATE '.MAIN_DB_PREFIX.'pvproc_relance SET';
		$sql .= ' status = '.((int) self::STATUS_SENT);
		$sql .= ", date_envoi = '".$this->db->idate(dol_now())."'";
		if ($fkActioncomm > 0) {
			$sql .= ', fk_actioncomm = '.((int) $fkActioncomm);
		}
		$sql .= ' WHERE rowid = '.((int) $this->id);

		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			$this->errors[] = $this->error;
			return -1;
		}

		$this->status = self::STATUS_SENT;
		$this->date_envoi = dol_now();
		$this->fk_actioncomm = (int) $fkActioncomm;

		return 1;
	}

	/**
	 * Mark reminder as canceled.
	 *
	 * @return int
	 */
	public function markCanceled()
	{
		if ((int) $this->id <= 0) {
			return -1;
		}

		$sql = 'UPDATE '.MAIN_DB_PREFIX.'pvproc_relance SET status = '.((int) self::STATUS_CANCELED);
		$sql .= ' WHERE rowid = '.((int) $this->id);

		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			$this->errors[] = $this->error;
			return -1;
		}

		$this->status = self::STATUS_CANCELED;

		return 1;
	}

	/**
	 * Find planned reminders due before or at a date.
	 *
	 * @param DoliDB $db Database handler
	 * @param int $timestamp Timestamp
	 * @return array<int, Relance>
	 */
	public static function findDueRelances($db, $timestamp)
	{
		$finder = new self($db);

		return $finder->fetchDueRelances((int) $timestamp);
	}

	/**
	 * Fetch planned reminders due before or at a date.
	 *
	 * @param int $timestamp Timestamp
	 * @return array<int, Relance>
	 */
	private function fetchDueRelances($timestamp)
	{
		global $conf;

		$list = array();
		$entityFilter = function_exists('getEntity') ? getEntity('procedurespv_raccordement') : (string) ((int) $conf->entity);
		$sql = 'SELECT rowid, entity, fk_raccordement, type_relance, target_type, target_id, destinataire_email, date_prevue, date_envoi,';
		$sql .= ' canal, status, modele_utilise, resultat, commentaire, fk_actioncomm, datec, tms, import_key';
		$sql .= ' FROM '.MAIN_DB_PREFIX.'pvproc_relance';
		$sql .= ' WHERE status = '.((int) self::STATUS_PLANNED);
		$sql .= ' AND date_prevue <= '."'".$this->db->idate((int) $timestamp)."'";
		$sql .= ' AND entity IN ('.$entityFilter.')';
		$sql .= ' ORDER BY date_prevue ASC, rowid ASC';

		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			$this->errors[] = $this->error;
			return $list;
		}

		while (is_object($obj = $this->db->fetch_object($resql))) {
			$relance = new self($this->db);
			$relance->setVarsFromObj($obj);
			$list[(int) $relance->id] = $relance;
		}

		return $list;
	}

	/**
	 * Return summary for a raccordement.
	 *
	 * @param int $fkRaccordement Raccordement id
	 * @return array{last_sent:int|null, next_due:int|null, active_count:int, overdue_count:int}
	 */
	public function getSummaryByRaccordement($fkRaccordement)
	{
		global $conf;

		$summary = array(
			'last_sent' => null,
			'next_due' => null,
			'active_count' => 0,
			'overdue_count' => 0,
		);
		$entityFilter = function_exists('getEntity') ? getEntity('procedurespv_raccordement') : (string) ((int) $conf->entity);
		$sql = 'SELECT MAX(date_envoi) as last_sent, MIN(CASE WHEN status = '.((int) self::STATUS_PLANNED).' THEN date_prevue ELSE NULL END) as next_due,';
		$sql .= ' SUM(CASE WHEN status = '.((int) self::STATUS_PLANNED).' THEN 1 ELSE 0 END) as active_count,';
		$sql .= " SUM(CASE WHEN status = ".((int) self::STATUS_PLANNED)." AND date_prevue < '".$this->db->idate(dol_now())."' THEN 1 ELSE 0 END) as overdue_count";
		$sql .= ' FROM '.MAIN_DB_PREFIX.'pvproc_relance';
		$sql .= ' WHERE fk_raccordement = '.((int) $fkRaccordement);
		$sql .= ' AND entity IN ('.$entityFilter.')';

		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			$this->errors[] = $this->error;
			return $summary;
		}

		$obj = $this->db->fetch_object($resql);
		if (!is_object($obj)) {
			return $summary;
		}

		$summary['last_sent'] = !empty($obj->last_sent) ? $this->db->jdate($obj->last_sent) : null;
		$summary['next_due'] = !empty($obj->next_due) ? $this->db->jdate($obj->next_due) : null;
		$summary['active_count'] = (int) $obj->active_count;
		$summary['overdue_count'] = (int) $obj->overdue_count;

		return $summary;
	}

	/**
	 * Return status label key.
	 *
	 * @return string
	 */
	public function getStatusLabelKey()
	{
		$labels = self::getStatusLabels();

		return isset($labels[(int) $this->status]) ? $labels[(int) $this->status] : 'RelanceStatusUnknown';
	}

	/**
	 * Return status labels.
	 *
	 * @return array<int, string>
	 */
	public static function getStatusLabels()
	{
		return array(
			self::STATUS_PLANNED => 'RelanceStatusPlanned',
			self::STATUS_SENT => 'RelanceStatusSent',
			self::STATUS_CANCELED => 'RelanceStatusCanceled',
		);
	}

	/**
	 * Return reminder type labels.
	 *
	 * @return array<string, string>
	 */
	public static function getTypeLabels()
	{
		return array(
			'lien_public_non_ouvert' => 'RelanceTypePublicLinkNotOpened',
			'collecte_non_soumise' => 'RelanceTypeCollecteNotSubmitted',
			'mandat_non_signe' => 'RelanceTypeMandatNotSigned',
			'mandat_non_controle' => 'RelanceTypeMandatNotControlled',
			'piece_manquante' => 'RelanceTypeMissingPiece',
			'piece_non_conforme' => 'RelanceTypeNonCompliantPiece',
			'dossier_pret_non_depose' => 'RelanceTypeReadyNotDeposited',
			'enedis_sans_evolution' => 'RelanceTypeEnedisIdle',
			'complement_enedis_non_traite' => 'RelanceTypeEnedisComplementNotHandled',
			'cardi_non_retourne' => 'RelanceTypeCardiNotReturned',
			'convention_non_signee' => 'RelanceTypeConventionNotSigned',
			'mes_sans_retour' => 'RelanceTypeMesWithoutFeedback',
		);
	}

	/**
	 * Return channel labels.
	 *
	 * @return array<string, string>
	 */
	public static function getCanalLabels()
	{
		return array(
			'email' => 'RelanceCanalEmail',
			'telephone' => 'RelanceCanalPhone',
			'manuel' => 'RelanceCanalManual',
		);
	}

	/**
	 * Fetch one object from SQL.
	 *
	 * @param string $sql SQL query
	 * @return int
	 */
	private function fetchFromSql($sql)
	{
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			$this->errors[] = $this->error;
			return -1;
		}

		$obj = $this->db->fetch_object($resql);
		if (!is_object($obj)) {
			return 0;
		}

		$this->setVarsFromObj($obj);

		return 1;
	}

	/**
	 * Populate object from database row.
	 *
	 * @param stdClass $obj Database row
	 * @return void
	 */
	private function setVarsFromObj($obj)
	{
		$this->id = (int) $obj->rowid;
		$this->rowid = (int) $obj->rowid;
		$this->entity = (int) $obj->entity;
		$this->fk_raccordement = (int) $obj->fk_raccordement;
		$this->type_relance = (string) $obj->type_relance;
		$this->target_type = (string) $obj->target_type;
		$this->target_id = (int) $obj->target_id;
		$this->destinataire_email = (string) $obj->destinataire_email;
		$this->date_prevue = $this->db->jdate($obj->date_prevue);
		$this->date_envoi = $this->db->jdate($obj->date_envoi);
		$this->canal = (string) $obj->canal;
		$this->status = (int) $obj->status;
		$this->modele_utilise = (string) $obj->modele_utilise;
		$this->resultat = (string) $obj->resultat;
		$this->commentaire = (string) $obj->commentaire;
		$this->fk_actioncomm = (int) $obj->fk_actioncomm;
		$this->datec = $this->db->jdate($obj->datec);
		$this->tms = $this->db->jdate($obj->tms);
		$this->import_key = (string) $obj->import_key;
	}

	/**
	 * Quote a string or return SQL null.
	 *
	 * @param string $value Value to quote
	 * @return string
	 */
	private function quote($value)
	{
		if ($value === '') {
			return 'NULL';
		}

		return "'".$this->db->escape($value)."'";
	}

	/**
	 * Convert timestamp to SQL datetime or null.
	 *
	 * @param string|int|null $date Timestamp
	 * @return string
	 */
	private function dateToSql($date)
	{
		$timestamp = (int) $date;
		if ($timestamp <= 0) {
			return 'NULL';
		}

		return "'".$this->db->idate($timestamp)."'";
	}

	/**
	 * Convert integer to SQL integer or null.
	 *
	 * @param string|int|null $value Integer value
	 * @return string
	 */
	private function intOrNull($value)
	{
		$intValue = (int) $value;
		if ($intValue <= 0) {
			return 'NULL';
		}

		return (string) $intValue;
	}
}
