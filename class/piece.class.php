<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * Raccordement piece.
 */
class Piece
{
	public const STATUS_TO_PROVIDE = 0;
	public const STATUS_TRANSMITTED = 1;
	public const STATUS_TO_CONTROL = 2;
	public const STATUS_NON_COMPLIANT = 3;
	public const STATUS_VALIDATED = 4;

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
	public $code_piece;
	public $label;
	public $origin;
	public $required;
	public $status;
	public $filepath;
	public $filename;
	public $fk_user_valid;
	public $date_validation;
	public $motif_refus;
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
		$this->required = 0;
		$this->status = self::STATUS_TO_PROVIDE;
	}

	/**
	 * Fetch piece by id.
	 *
	 * @param int $id Piece id
	 * @return int
	 */
	public function fetch($id)
	{
		global $conf;

		$entityFilter = function_exists('getEntity') ? getEntity('procedurespv_raccordement') : (string) ((int) $conf->entity);
		$sql = 'SELECT rowid, entity, fk_raccordement, code_piece, label, origin, required, status, filepath, filename,';
		$sql .= ' fk_user_valid, date_validation, motif_refus, commentaire, datec, tms, import_key';
		$sql .= ' FROM '.MAIN_DB_PREFIX.'pvproc_piece';
		$sql .= ' WHERE rowid = '.((int) $id);
		$sql .= ' AND entity IN ('.$entityFilter.')';

		return $this->fetchFromSql($sql);
	}

	/**
	 * Fetch pieces for raccordement.
	 *
	 * @param int $fkRaccordement Raccordement id
	 * @return array<int, Piece>
	 */
	public function fetchAllByRaccordement($fkRaccordement)
	{
		global $conf;

		$pieces = array();
		$entityFilter = function_exists('getEntity') ? getEntity('procedurespv_raccordement') : (string) ((int) $conf->entity);
		$sql = 'SELECT rowid, entity, fk_raccordement, code_piece, label, origin, required, status, filepath, filename,';
		$sql .= ' fk_user_valid, date_validation, motif_refus, commentaire, datec, tms, import_key';
		$sql .= ' FROM '.MAIN_DB_PREFIX.'pvproc_piece';
		$sql .= ' WHERE fk_raccordement = '.((int) $fkRaccordement);
		$sql .= ' AND entity IN ('.$entityFilter.')';
		$sql .= ' ORDER BY required DESC, code_piece ASC, rowid ASC';

		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			$this->errors[] = $this->error;
			return $pieces;
		}

		while (is_object($obj = $this->db->fetch_object($resql))) {
			$piece = new self($this->db);
			$piece->setVarsFromObj($obj);
			$pieces[(int) $piece->id] = $piece;
		}

		return $pieces;
	}

	/**
	 * Create or update an uploaded piece entry.
	 *
	 * @param Raccordement $raccordement Parent object
	 * @param string $code Piece code
	 * @param string $label Piece label
	 * @param string $origin Piece origin
	 * @param string $filepath Stored directory
	 * @param string $filename Stored filename
	 * @param int $required 1 if required
	 * @return int
	 */
	public function createOrUpdateUploaded($raccordement, $code, $label, $origin, $filepath, $filename, $required = 0)
	{
		$existingId = $this->findExisting((int) $raccordement->id, $code, $origin);
		$now = dol_now();

		if ($existingId > 0) {
			$sql = 'UPDATE '.MAIN_DB_PREFIX.'pvproc_piece SET';
			$sql .= " label = '".$this->db->escape($label)."',";
			$sql .= ' required = '.((int) $required).',';
			$sql .= ' status = '.((int) self::STATUS_TRANSMITTED).',';
			$sql .= " filepath = '".$this->db->escape($filepath)."',";
			$sql .= " filename = '".$this->db->escape($filename)."'";
			$sql .= ' WHERE rowid = '.$existingId;
		} else {
			$sql = 'INSERT INTO '.MAIN_DB_PREFIX.'pvproc_piece (';
			$sql .= 'entity, fk_raccordement, code_piece, label, origin, required, status, filepath, filename, datec';
			$sql .= ') VALUES (';
			$sql .= ((int) $raccordement->entity).', ';
			$sql .= ((int) $raccordement->id).', ';
			$sql .= "'".$this->db->escape($code)."', ";
			$sql .= "'".$this->db->escape($label)."', ";
			$sql .= "'".$this->db->escape($origin)."', ";
			$sql .= ((int) $required).', ';
			$sql .= ((int) self::STATUS_TRANSMITTED).', ';
			$sql .= "'".$this->db->escape($filepath)."', ";
			$sql .= "'".$this->db->escape($filename)."', ";
			$sql .= "'".$this->db->idate($now)."'";
			$sql .= ')';
		}

		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			$this->errors[] = $this->error;
			return -1;
		}

		$this->id = $existingId > 0 ? $existingId : (int) $this->db->last_insert_id(MAIN_DB_PREFIX.'pvproc_piece');
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

		$sql = 'UPDATE '.MAIN_DB_PREFIX.'pvproc_piece SET';
		$sql .= ' status = '.((int) $status).',';
		$sql .= ' fk_user_valid = '.(is_object($user) ? (int) $user->id : 0).',';
		$sql .= " date_validation = '".$this->db->idate(dol_now())."',";
		$sql .= " motif_refus = '".$this->db->escape($motif)."'";
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
			self::STATUS_TO_PROVIDE => 'PieceStatusToProvide',
			self::STATUS_TRANSMITTED => 'PieceStatusTransmitted',
			self::STATUS_TO_CONTROL => 'PieceStatusToControl',
			self::STATUS_NON_COMPLIANT => 'PieceStatusNonCompliant',
			self::STATUS_VALIDATED => 'PieceStatusValidated',
		);
	}

	/**
	 * Return translated-like status key.
	 *
	 * @return string
	 */
	public function getStatusLabelKey()
	{
		$labels = self::getStatusLabels();

		return isset($labels[(int) $this->status]) ? $labels[(int) $this->status] : 'PieceStatusUnknown';
	}

	/**
	 * Find existing piece.
	 *
	 * @param int $fkRaccordement Raccordement id
	 * @param string $code Piece code
	 * @param string $origin Piece origin
	 * @return int
	 */
	private function findExisting($fkRaccordement, $code, $origin)
	{
		$sql = 'SELECT rowid FROM '.MAIN_DB_PREFIX.'pvproc_piece';
		$sql .= ' WHERE fk_raccordement = '.((int) $fkRaccordement);
		$sql .= " AND code_piece = '".$this->db->escape($code)."'";
		$sql .= " AND origin = '".$this->db->escape($origin)."'";
		$sql .= $this->db->plimit(1);

		$resql = $this->db->query($sql);
		if (!$resql) {
			return 0;
		}

		$obj = $this->db->fetch_object($resql);

		return is_object($obj) ? (int) $obj->rowid : 0;
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
		$this->code_piece = (string) $obj->code_piece;
		$this->label = (string) $obj->label;
		$this->origin = (string) $obj->origin;
		$this->required = (int) $obj->required;
		$this->status = (int) $obj->status;
		$this->filepath = (string) $obj->filepath;
		$this->filename = (string) $obj->filename;
		$this->fk_user_valid = (int) $obj->fk_user_valid;
		$this->date_validation = $this->db->jdate($obj->date_validation);
		$this->motif_refus = (string) $obj->motif_refus;
		$this->commentaire = (string) $obj->commentaire;
		$this->datec = $this->db->jdate($obj->datec);
		$this->tms = $this->db->jdate($obj->tms);
		$this->import_key = (string) $obj->import_key;
	}
}

