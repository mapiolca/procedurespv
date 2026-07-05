<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * ENEDIS mandate signature.
 */
class Signature
{
	public const TYPE_MANDAT_ENEDIS = 'mandat_enedis';

	public const STATUS_TO_GENERATE = 0;
	public const STATUS_SENT_TO_CLIENT = 1;
	public const STATUS_WAITING_SIGNATURE = 2;
	public const STATUS_SIGNED_ONLINE = 3;
	public const STATUS_TO_CONTROL = 4;
	public const STATUS_NON_COMPLIANT = 5;
	public const STATUS_VALIDATED = 6;

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
	public $type_signature;
	public $signataire_nom;
	public $signataire_fonction;
	public $signataire_email;
	public $signature_date;
	public $signature_ip;
	public $signature_user_agent;
	public $pdf_hash;
	public $filepath;
	public $filename;
	public $status;
	public $date_validation;
	public $fk_user_valid;
	public $motif_non_conformite;
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
		$this->type_signature = self::TYPE_MANDAT_ENEDIS;
		$this->status = self::STATUS_TO_GENERATE;
	}

	/**
	 * Fetch signature by id.
	 *
	 * @param int $id Signature id
	 * @return int
	 */
	public function fetch($id)
	{
		global $conf;

		$entityFilter = function_exists('getEntity') ? getEntity('procedurespv_raccordement') : (string) ((int) $conf->entity);
		$sql = 'SELECT rowid, entity, fk_raccordement, type_signature, signataire_nom, signataire_fonction, signataire_email,';
		$sql .= ' signature_date, signature_ip, signature_user_agent, pdf_hash, filepath, filename, status, date_validation,';
		$sql .= ' fk_user_valid, motif_non_conformite, datec, tms, import_key';
		$sql .= ' FROM '.MAIN_DB_PREFIX.'pvproc_signature';
		$sql .= ' WHERE rowid = '.((int) $id);
		$sql .= ' AND entity IN ('.$entityFilter.')';

		return $this->fetchFromSql($sql);
	}

	/**
	 * Fetch latest signature for a raccordement.
	 *
	 * @param int $fkRaccordement Raccordement id
	 * @param string $type Signature type
	 * @return int
	 */
	public function fetchLatestForRaccordement($fkRaccordement, $type = self::TYPE_MANDAT_ENEDIS)
	{
		global $conf;

		$entityFilter = function_exists('getEntity') ? getEntity('procedurespv_raccordement') : (string) ((int) $conf->entity);
		$sql = 'SELECT rowid, entity, fk_raccordement, type_signature, signataire_nom, signataire_fonction, signataire_email,';
		$sql .= ' signature_date, signature_ip, signature_user_agent, pdf_hash, filepath, filename, status, date_validation,';
		$sql .= ' fk_user_valid, motif_non_conformite, datec, tms, import_key';
		$sql .= ' FROM '.MAIN_DB_PREFIX.'pvproc_signature';
		$sql .= ' WHERE fk_raccordement = '.((int) $fkRaccordement);
		$sql .= ' AND entity IN ('.$entityFilter.')';
		$sql .= " AND type_signature = '".$this->db->escape($type)."'";
		$sql .= ' ORDER BY rowid DESC';
		$sql .= $this->db->plimit(1);

		return $this->fetchFromSql($sql);
	}

	/**
	 * Fetch latest signature for a raccordement in a known entity.
	 *
	 * @param int $fkRaccordement Raccordement id
	 * @param int $entity Entity id
	 * @param string $type Signature type
	 * @return int
	 */
	public function fetchLatestForRaccordementEntity($fkRaccordement, $entity, $type = self::TYPE_MANDAT_ENEDIS)
	{
		$sql = 'SELECT rowid, entity, fk_raccordement, type_signature, signataire_nom, signataire_fonction, signataire_email,';
		$sql .= ' signature_date, signature_ip, signature_user_agent, pdf_hash, filepath, filename, status, date_validation,';
		$sql .= ' fk_user_valid, motif_non_conformite, datec, tms, import_key';
		$sql .= ' FROM '.MAIN_DB_PREFIX.'pvproc_signature';
		$sql .= ' WHERE fk_raccordement = '.((int) $fkRaccordement);
		$sql .= ' AND entity = '.((int) $entity);
		$sql .= " AND type_signature = '".$this->db->escape($type)."'";
		$sql .= ' ORDER BY rowid DESC';
		$sql .= $this->db->plimit(1);

		return $this->fetchFromSql($sql);
	}

	/**
	 * Save a signed mandate.
	 *
	 * @param Raccordement $raccordement Parent object
	 * @param array{signataire_nom:string, signataire_fonction:string, signataire_email:string, signature_ip:string, signature_user_agent:string, filepath:string, filename:string, pdf_hash:string} $data Signature data
	 * @return int Signature id or negative value
	 */
	public function createSignedMandate($raccordement, array $data)
	{
		$now = dol_now();
		$sql = 'INSERT INTO '.MAIN_DB_PREFIX.'pvproc_signature (';
		$sql .= 'entity, fk_raccordement, type_signature, signataire_nom, signataire_fonction, signataire_email, signature_date, signature_ip, signature_user_agent, pdf_hash, filepath, filename, status, datec';
		$sql .= ') VALUES (';
		$sql .= ((int) $raccordement->entity).', ';
		$sql .= ((int) $raccordement->id).', ';
		$sql .= "'".$this->db->escape(self::TYPE_MANDAT_ENEDIS)."', ";
		$sql .= "'".$this->db->escape($data['signataire_nom'])."', ";
		$sql .= "'".$this->db->escape($data['signataire_fonction'])."', ";
		$sql .= "'".$this->db->escape($data['signataire_email'])."', ";
		$sql .= "'".$this->db->idate($now)."', ";
		$sql .= "'".$this->db->escape($data['signature_ip'])."', ";
		$sql .= "'".$this->db->escape(dol_trunc($data['signature_user_agent'], 255))."', ";
		$sql .= "'".$this->db->escape($data['pdf_hash'])."', ";
		$sql .= "'".$this->db->escape($data['filepath'])."', ";
		$sql .= "'".$this->db->escape($data['filename'])."', ";
		$sql .= ((int) self::STATUS_TO_CONTROL).', ';
		$sql .= "'".$this->db->idate($now)."'";
		$sql .= ')';

		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			$this->errors[] = $this->error;
			return -1;
		}

		$this->id = (int) $this->db->last_insert_id(MAIN_DB_PREFIX.'pvproc_signature');
		$this->rowid = $this->id;

		return $this->id;
	}

	/**
	 * Set validation status.
	 *
	 * @param int $status New status
	 * @param User $user User validating
	 * @param string $motif Refusal reason
	 * @return int
	 */
	public function setValidationStatus($status, $user, $motif = '')
	{
		if ((int) $this->id <= 0) {
			return -1;
		}

		$sql = 'UPDATE '.MAIN_DB_PREFIX.'pvproc_signature SET';
		$sql .= ' status = '.((int) $status).',';
		$sql .= ' fk_user_valid = '.(is_object($user) ? (int) $user->id : 0).',';
		$sql .= " date_validation = '".$this->db->idate(dol_now())."',";
		$sql .= " motif_non_conformite = '".$this->db->escape($motif)."'";
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
	 * Return status labels.
	 *
	 * @return array<int, string>
	 */
	public static function getStatusLabels()
	{
		return array(
			self::STATUS_TO_GENERATE => 'SignatureStatusToGenerate',
			self::STATUS_SENT_TO_CLIENT => 'SignatureStatusSentToClient',
			self::STATUS_WAITING_SIGNATURE => 'SignatureStatusWaiting',
			self::STATUS_SIGNED_ONLINE => 'SignatureStatusSignedOnline',
			self::STATUS_TO_CONTROL => 'SignatureStatusToControl',
			self::STATUS_NON_COMPLIANT => 'SignatureStatusNonCompliant',
			self::STATUS_VALIDATED => 'SignatureStatusValidated',
		);
	}

	/**
	 * Return status label key.
	 *
	 * @return string
	 */
	public function getStatusLabelKey()
	{
		$labels = self::getStatusLabels();

		return isset($labels[(int) $this->status]) ? $labels[(int) $this->status] : 'SignatureStatusUnknown';
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
		$this->type_signature = (string) $obj->type_signature;
		$this->signataire_nom = (string) $obj->signataire_nom;
		$this->signataire_fonction = (string) $obj->signataire_fonction;
		$this->signataire_email = (string) $obj->signataire_email;
		$this->signature_date = $this->db->jdate($obj->signature_date);
		$this->signature_ip = (string) $obj->signature_ip;
		$this->signature_user_agent = (string) $obj->signature_user_agent;
		$this->pdf_hash = (string) $obj->pdf_hash;
		$this->filepath = (string) $obj->filepath;
		$this->filename = (string) $obj->filename;
		$this->status = (int) $obj->status;
		$this->date_validation = $this->db->jdate($obj->date_validation);
		$this->fk_user_valid = (int) $obj->fk_user_valid;
		$this->motif_non_conformite = (string) $obj->motif_non_conformite;
		$this->datec = $this->db->jdate($obj->datec);
		$this->tms = $this->db->jdate($obj->tms);
		$this->import_key = (string) $obj->import_key;
	}
}
