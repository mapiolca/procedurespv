<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

/** Raccordement supporting document attached to a collection revision. */
class Piece
{
	public const STATUS_WAITING = 0;
	public const STATUS_OPTIONAL = 1;
	public const STATUS_MISSING = 2;
	public const STATUS_TO_CONTROL = 3;
	public const STATUS_VALID = 4;
	public const STATUS_INVALID = 5;

	// Compatibility aliases for code written before revision support.
	public const STATUS_TO_PROVIDE = self::STATUS_WAITING;
	public const STATUS_TRANSMITTED = self::STATUS_TO_CONTROL;
	public const STATUS_NON_COMPLIANT = self::STATUS_INVALID;
	public const STATUS_VALIDATED = self::STATUS_VALID;

	/** @var DoliDB */
	private $db;

	public $id;
	public $rowid;
	public $entity;
	public $fk_raccordement;
	public $fk_publiclink;
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

	/** @param DoliDB $db Database handler */
	public function __construct($db)
	{
		$this->db = $db;
		$this->required = 0;
		$this->status = self::STATUS_WAITING;
	}

	/** @param int $id Piece id @return int */
	public function fetch($id)
	{
		global $conf;
		$entityFilter = function_exists('getEntity') ? getEntity('procedurespv_raccordement') : (string) ((int) $conf->entity);
		return $this->fetchFromSql($this->getSelectSql().' WHERE rowid = '.((int) $id).' AND entity IN ('.$entityFilter.')');
	}

	/**
	 * @param int $fkRaccordement Raccordement id
	 * @param int $fkPublicLink Revision id, -1 for all, 0 for internal pieces
	 * @return array<int, Piece>
	 */
	public function fetchAllByRaccordement($fkRaccordement, $fkPublicLink = -1)
	{
		global $conf;
		$pieces = array();
		$entityFilter = function_exists('getEntity') ? getEntity('procedurespv_raccordement') : (string) ((int) $conf->entity);
		$sql = $this->getSelectSql().' WHERE fk_raccordement = '.((int) $fkRaccordement).' AND entity IN ('.$entityFilter.')';
		if ($fkPublicLink > 0) {
			$sql .= ' AND fk_publiclink = '.((int) $fkPublicLink);
		} elseif ($fkPublicLink === 0) {
			$sql .= ' AND fk_publiclink IS NULL';
		}
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
	 * @param Raccordement $raccordement Parent object
	 * @param int $fkPublicLink Revision id
	 * @param string $code Piece code
	 * @param string $label Piece label
	 * @param string $origin Origin
	 * @param int $required Required flag
	 * @param bool $submitted Revision submitted
	 * @return int Piece id
	 */
	public function ensureDefinition($raccordement, $fkPublicLink, $code, $label, $origin, $required, $submitted = false)
	{
		$existingId = $this->findExisting((int) $raccordement->id, (int) $fkPublicLink, $code, $origin);
		$status = $required ? ($submitted ? self::STATUS_MISSING : self::STATUS_WAITING) : self::STATUS_OPTIONAL;
		if ($existingId > 0) {
			$sql = 'UPDATE '.MAIN_DB_PREFIX.'pvproc_piece SET label = \''.$this->db->escape($label).'\', required = '.((int) $required);
			$sql .= ', status = CASE WHEN filename IS NULL OR filename = \'\' THEN '.((int) $status).' ELSE status END WHERE rowid = '.$existingId;
		} else {
			$sql = 'INSERT INTO '.MAIN_DB_PREFIX.'pvproc_piece (entity, fk_raccordement, fk_publiclink, code_piece, label, origin, required, status, datec) VALUES (';
			$sql .= ((int) $raccordement->entity).', '.((int) $raccordement->id).', '.((int) $fkPublicLink).', \''.$this->db->escape($code).'\', \''.$this->db->escape($label).'\', \''.$this->db->escape($origin).'\', '.((int) $required).', '.((int) $status).', \''.$this->db->idate(dol_now()).'\')';
		}
		if (!$this->db->query($sql)) {
			$this->error = $this->db->lasterror();
			return -1;
		}
		return $existingId > 0 ? $existingId : (int) $this->db->last_insert_id(MAIN_DB_PREFIX.'pvproc_piece');
	}

	/**
	 * @param Raccordement $raccordement Parent object
	 * @param string $code Piece code
	 * @param string $label Piece label
	 * @param string $origin Piece origin
	 * @param string $filepath Stored directory
	 * @param string $filename Stored filename
	 * @param int $required Required flag
	 * @param int $fkPublicLink Revision id, 0 for internal documents
	 * @return int
	 */
	public function createOrUpdateUploaded($raccordement, $code, $label, $origin, $filepath, $filename, $required = 0, $fkPublicLink = 0)
	{
		$existingId = $this->findExisting((int) $raccordement->id, (int) $fkPublicLink, $code, $origin);
		$now = dol_now();
		if ($existingId > 0) {
			$sql = 'UPDATE '.MAIN_DB_PREFIX.'pvproc_piece SET label = \''.$this->db->escape($label).'\', required = '.((int) $required).', status = '.self::STATUS_TO_CONTROL;
			$sql .= ', filepath = \''.$this->db->escape($filepath).'\', filename = \''.$this->db->escape($filename).'\', fk_user_valid = NULL, date_validation = NULL, motif_refus = NULL WHERE rowid = '.$existingId;
		} else {
			$sql = 'INSERT INTO '.MAIN_DB_PREFIX.'pvproc_piece (entity, fk_raccordement, fk_publiclink, code_piece, label, origin, required, status, filepath, filename, datec) VALUES (';
			$sql .= ((int) $raccordement->entity).', '.((int) $raccordement->id).', '.($fkPublicLink > 0 ? (int) $fkPublicLink : 'NULL').', \''.$this->db->escape($code).'\', \''.$this->db->escape($label).'\', \''.$this->db->escape($origin).'\', '.((int) $required).', '.self::STATUS_TO_CONTROL.', \''.$this->db->escape($filepath).'\', \''.$this->db->escape($filename).'\', \''.$this->db->idate($now).'\')';
		}
		if (!$this->db->query($sql)) {
			$this->error = $this->db->lasterror();
			$this->errors[] = $this->error;
			return -1;
		}
		$this->id = $existingId > 0 ? $existingId : (int) $this->db->last_insert_id(MAIN_DB_PREFIX.'pvproc_piece');
		$this->rowid = $this->id;
		return $this->id;
	}

	/** @param int $status New status @param User $user User @param string $motif Refusal reason @return int */
	public function setValidationStatus($status, $user, $motif = '')
	{
		if ((int) $this->id <= 0 || (int) $this->status !== self::STATUS_TO_CONTROL || !in_array((int) $status, array(self::STATUS_VALID, self::STATUS_INVALID), true)) {
			$this->error = 'ErrorInvalidPieceTransition';
			return -1;
		}
		$sql = 'UPDATE '.MAIN_DB_PREFIX.'pvproc_piece SET status = '.((int) $status).', fk_user_valid = '.(is_object($user) ? (int) $user->id : 0).', date_validation = \''.$this->db->idate(dol_now()).'\', motif_refus = \''.$this->db->escape($motif).'\' WHERE rowid = '.((int) $this->id);
		if (!$this->db->query($sql)) {
			$this->error = $this->db->lasterror();
			return -1;
		}
		$this->status = (int) $status;
		return 1;
	}

	/** @return array<int, string> */
	public static function getStatusLabels()
	{
		return array(self::STATUS_WAITING => 'PieceStatusWaiting', self::STATUS_OPTIONAL => 'PieceStatusOptional', self::STATUS_MISSING => 'PieceStatusMissing', self::STATUS_TO_CONTROL => 'PieceStatusToControl', self::STATUS_VALID => 'PieceStatusValid', self::STATUS_INVALID => 'PieceStatusInvalid');
	}

	/** @return string */
	public function getStatusLabelKey()
	{
		$labels = self::getStatusLabels();
		return $labels[(int) $this->status] ?? 'PieceStatusUnknown';
	}

	/** @param int $mode Display mode @return string */
	public function getLibStatut($mode = 5)
	{
		global $langs;
		$badgeStatus = array(self::STATUS_WAITING => 0, self::STATUS_OPTIONAL => 0, self::STATUS_MISSING => 8, self::STATUS_TO_CONTROL => 3, self::STATUS_VALID => 4, self::STATUS_INVALID => 8);
		return dolGetStatus($langs->trans($this->getStatusLabelKey()), '', '', 'status'.($badgeStatus[(int) $this->status] ?? 0), $mode);
	}

	/** @return string */
	private function getSelectSql()
	{
		return 'SELECT rowid, entity, fk_raccordement, fk_publiclink, code_piece, label, origin, required, status, filepath, filename, fk_user_valid, date_validation, motif_refus, commentaire, datec, tms, import_key FROM '.MAIN_DB_PREFIX.'pvproc_piece';
	}

	/** @param int $fkRaccordement Raccordement @param int $fkPublicLink Revision @param string $code Code @param string $origin Origin @return int */
	private function findExisting($fkRaccordement, $fkPublicLink, $code, $origin)
	{
		$sql = 'SELECT rowid FROM '.MAIN_DB_PREFIX.'pvproc_piece WHERE fk_raccordement = '.((int) $fkRaccordement);
		$sql .= $fkPublicLink > 0 ? ' AND fk_publiclink = '.((int) $fkPublicLink) : ' AND fk_publiclink IS NULL';
		$sql .= ' AND code_piece = \''.$this->db->escape($code).'\' AND origin = \''.$this->db->escape($origin).'\''.$this->db->plimit(1);
		$resql = $this->db->query($sql);
		$obj = $resql ? $this->db->fetch_object($resql) : false;
		return is_object($obj) ? (int) $obj->rowid : 0;
	}

	/** @param string $sql SQL @return int */
	private function fetchFromSql($sql)
	{
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return -1;
		}
		$obj = $this->db->fetch_object($resql);
		if (!is_object($obj)) {
			return 0;
		}
		$this->setVarsFromObj($obj);
		return 1;
	}

	/** @param stdClass $obj Database row @return void */
	private function setVarsFromObj($obj)
	{
		$this->id = $this->rowid = (int) $obj->rowid;
		$this->entity = (int) $obj->entity;
		$this->fk_raccordement = (int) $obj->fk_raccordement;
		$this->fk_publiclink = isset($obj->fk_publiclink) ? (int) $obj->fk_publiclink : 0;
		foreach (array('code_piece', 'label', 'origin', 'filepath', 'filename', 'motif_refus', 'commentaire', 'import_key') as $property) {
			$this->{$property} = isset($obj->{$property}) ? (string) $obj->{$property} : '';
		}
		$this->required = (int) $obj->required;
		$this->status = (int) $obj->status;
		$this->fk_user_valid = (int) $obj->fk_user_valid;
		$this->date_validation = $this->db->jdate($obj->date_validation);
		$this->datec = $this->db->jdate($obj->datec);
		$this->tms = $this->db->jdate($obj->tms);
	}
}
