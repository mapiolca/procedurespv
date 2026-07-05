<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * Raccordement convention / contract.
 */
class Convention
{
	public const STATUS_NOT_RECEIVED = 0;
	public const STATUS_RECEIVED = 1;
	public const STATUS_TO_CONTROL = 2;
	public const STATUS_TO_SIGN = 3;
	public const STATUS_SENT_FOR_SIGNATURE = 4;
	public const STATUS_SIGNED = 5;
	public const STATUS_RETURNED_ENEDIS = 6;
	public const STATUS_VALIDATED = 7;
	public const STATUS_OBSOLETE = 8;

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
	public $type_convention;
	public $ref_convention;
	public $status;
	public $date_reception;
	public $date_envoi_client;
	public $date_signature_client;
	public $date_retour_enedis;
	public $date_validation;
	public $document_recu;
	public $document_signe;
	public $commentaire;
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
		$this->status = self::STATUS_NOT_RECEIVED;
	}

	/**
	 * Fetch one convention.
	 *
	 * @param int $id Convention id
	 * @return int
	 */
	public function fetch($id)
	{
		global $conf;

		$entityFilter = function_exists('getEntity') ? getEntity('procedurespv_raccordement') : (string) ((int) $conf->entity);
		$sql = 'SELECT rowid, entity, fk_raccordement, type_convention, ref_convention, status, date_reception, date_envoi_client,';
		$sql .= ' date_signature_client, date_retour_enedis, date_validation, document_recu, document_signe, commentaire, datec, tms, import_key';
		$sql .= ' FROM '.MAIN_DB_PREFIX.'pvproc_convention';
		$sql .= ' WHERE rowid = '.((int) $id);
		$sql .= ' AND entity IN ('.$entityFilter.')';

		return $this->fetchFromSql($sql);
	}

	/**
	 * Fetch conventions linked to raccordement.
	 *
	 * @param int $fkRaccordement Raccordement id
	 * @return array<int, Convention>
	 */
	public function fetchAllByRaccordement($fkRaccordement)
	{
		global $conf;

		$list = array();
		$entityFilter = function_exists('getEntity') ? getEntity('procedurespv_raccordement') : (string) ((int) $conf->entity);
		$sql = 'SELECT rowid, entity, fk_raccordement, type_convention, ref_convention, status, date_reception, date_envoi_client,';
		$sql .= ' date_signature_client, date_retour_enedis, date_validation, document_recu, document_signe, commentaire, datec, tms, import_key';
		$sql .= ' FROM '.MAIN_DB_PREFIX.'pvproc_convention';
		$sql .= ' WHERE fk_raccordement = '.((int) $fkRaccordement);
		$sql .= ' AND entity IN ('.$entityFilter.')';
		$sql .= ' ORDER BY rowid DESC';

		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			$this->errors[] = $this->error;
			return $list;
		}

		while (is_object($obj = $this->db->fetch_object($resql))) {
			$convention = new self($this->db);
			$convention->setVarsFromObj($obj);
			$list[(int) $convention->id] = $convention;
		}

		return $list;
	}

	/**
	 * Create convention.
	 *
	 * @param Raccordement $raccordement Parent object
	 * @param array<string, string|int|null> $data Input data
	 * @return int
	 */
	public function create($raccordement, array $data)
	{
		$sql = 'INSERT INTO '.MAIN_DB_PREFIX.'pvproc_convention (';
		$sql .= 'entity, fk_raccordement, type_convention, ref_convention, status, date_reception, date_envoi_client, date_signature_client, date_retour_enedis, date_validation, document_recu, document_signe, commentaire, datec';
		$sql .= ') VALUES (';
		$sql .= ((int) $raccordement->entity).', ';
		$sql .= ((int) $raccordement->id).', ';
		$sql .= $this->quote((string) $data['type_convention']).', ';
		$sql .= $this->quote((string) $data['ref_convention']).', ';
		$sql .= ((int) $data['status']).', ';
		$sql .= $this->dateToSql($data['date_reception']).', ';
		$sql .= $this->dateToSql($data['date_envoi_client']).', ';
		$sql .= $this->dateToSql($data['date_signature_client']).', ';
		$sql .= $this->dateToSql($data['date_retour_enedis']).', ';
		$sql .= $this->dateToSql($data['date_validation']).', ';
		$sql .= $this->quote((string) $data['document_recu']).', ';
		$sql .= $this->quote((string) $data['document_signe']).', ';
		$sql .= $this->quote((string) $data['commentaire']).', ';
		$sql .= "'".$this->db->idate(dol_now())."'";
		$sql .= ')';

		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			$this->errors[] = $this->error;
			return -1;
		}

		$this->id = (int) $this->db->last_insert_id(MAIN_DB_PREFIX.'pvproc_convention');
		$this->rowid = $this->id;

		return $this->id;
	}

	/**
	 * Update convention data.
	 *
	 * @param array<string, string|int|null> $data Input data
	 * @return int
	 */
	public function update(array $data)
	{
		if ((int) $this->id <= 0) {
			return -1;
		}

		$sql = 'UPDATE '.MAIN_DB_PREFIX.'pvproc_convention SET';
		$sql .= ' type_convention = '.$this->quote((string) $data['type_convention']);
		$sql .= ', ref_convention = '.$this->quote((string) $data['ref_convention']);
		$sql .= ', status = '.((int) $data['status']);
		$sql .= ', date_reception = '.$this->dateToSql($data['date_reception']);
		$sql .= ', date_envoi_client = '.$this->dateToSql($data['date_envoi_client']);
		$sql .= ', date_signature_client = '.$this->dateToSql($data['date_signature_client']);
		$sql .= ', date_retour_enedis = '.$this->dateToSql($data['date_retour_enedis']);
		$sql .= ', date_validation = '.$this->dateToSql($data['date_validation']);
		$sql .= ', document_recu = '.$this->quote((string) $data['document_recu']);
		$sql .= ', document_signe = '.$this->quote((string) $data['document_signe']);
		$sql .= ', commentaire = '.$this->quote((string) $data['commentaire']);
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
	 * Set convention status.
	 *
	 * @param int $status New status
	 * @return int
	 */
	public function setStatus($status)
	{
		if ((int) $this->id <= 0) {
			return -1;
		}

		$now = dol_now();
		$sql = 'UPDATE '.MAIN_DB_PREFIX.'pvproc_convention SET status = '.((int) $status);
		if ($status === self::STATUS_RECEIVED || $status === self::STATUS_TO_CONTROL) {
			$sql .= ", date_reception = '".$this->db->idate($now)."'";
		}
		if ($status === self::STATUS_SENT_FOR_SIGNATURE) {
			$sql .= ", date_envoi_client = '".$this->db->idate($now)."'";
		}
		if ($status === self::STATUS_SIGNED) {
			$sql .= ", date_signature_client = '".$this->db->idate($now)."'";
		}
		if ($status === self::STATUS_RETURNED_ENEDIS) {
			$sql .= ", date_retour_enedis = '".$this->db->idate($now)."'";
		}
		if ($status === self::STATUS_VALIDATED) {
			$sql .= ", date_validation = '".$this->db->idate($now)."'";
		}
		$sql .= ' WHERE rowid = '.((int) $this->id);

		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			$this->errors[] = $this->error;
			return -1;
		}

		$this->status = (int) $status;

		return 1;
	}

	/**
	 * Return status label key.
	 *
	 * @return string
	 */
	public function getStatusLabelKey()
	{
		$labels = self::getStatusLabels();

		return isset($labels[(int) $this->status]) ? $labels[(int) $this->status] : 'ConventionStatusUnknown';
	}

	/**
	 * Return status labels.
	 *
	 * @return array<int, string>
	 */
	public static function getStatusLabels()
	{
		return array(
			self::STATUS_NOT_RECEIVED => 'ConventionStatusNotReceived',
			self::STATUS_RECEIVED => 'ConventionStatusReceived',
			self::STATUS_TO_CONTROL => 'ConventionStatusToControl',
			self::STATUS_TO_SIGN => 'ConventionStatusToSign',
			self::STATUS_SENT_FOR_SIGNATURE => 'ConventionStatusSentForSignature',
			self::STATUS_SIGNED => 'ConventionStatusSigned',
			self::STATUS_RETURNED_ENEDIS => 'ConventionStatusReturnedEnedis',
			self::STATUS_VALIDATED => 'ConventionStatusValidated',
			self::STATUS_OBSOLETE => 'ConventionStatusObsolete',
		);
	}

	/**
	 * Return convention type labels.
	 *
	 * @return array<string, string>
	 */
	public static function getTypeLabels()
	{
		return array(
			'cacsi' => 'ConventionTypeCACSI',
			'cae' => 'ConventionTypeCAE',
			'card_i' => 'ConventionTypeCARDI',
			'cex' => 'ConventionTypeCEX',
			'crd' => 'ConventionTypeCRD',
			'convention_raccordement' => 'ConventionTypeRaccordement',
			'contrat_unique_injection' => 'ConventionTypeContratUniqueInjection',
			'autre' => 'ConventionTypeOtherEnedis',
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
		$this->type_convention = (string) $obj->type_convention;
		$this->ref_convention = (string) $obj->ref_convention;
		$this->status = (int) $obj->status;
		$this->date_reception = $this->db->jdate($obj->date_reception);
		$this->date_envoi_client = $this->db->jdate($obj->date_envoi_client);
		$this->date_signature_client = $this->db->jdate($obj->date_signature_client);
		$this->date_retour_enedis = $this->db->jdate($obj->date_retour_enedis);
		$this->date_validation = $this->db->jdate($obj->date_validation);
		$this->document_recu = (string) $obj->document_recu;
		$this->document_signe = (string) $obj->document_signe;
		$this->commentaire = (string) $obj->commentaire;
		$this->datec = $this->db->jdate($obj->datec);
		$this->tms = $this->db->jdate($obj->tms);
		$this->import_key = (string) $obj->import_key;
	}
}
