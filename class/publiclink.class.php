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
 * Public secure link.
 */
class PublicLink extends CommonObject
{
	public const TYPE_COLLECTE_RACCORDEMENT = 'collecte_raccordement';
	public const TYPE_SIGNATURE_MANDAT = 'signature_mandat';
	public const TYPE_COLLECTE_CARDI = 'collecte_cardi';

	public const STATUS_REVOKED = -1;
	public const STATUS_ACTIVE = 1;
	public const STATUS_SUBMITTED = 2;

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
	public $element = 'procedurespv_publiclink';

	/**
	 * Table element.
	 *
	 * @var string
	 */
	public $table_element = 'pvproc_publiclink';

	public $id;
	public $rowid;
	public $entity;
	public $fk_raccordement;
	public $type_link;
	public $token_hash;
	public $email_destinataire;
	public $date_creation;
	public $date_expiration;
	public $date_first_access;
	public $date_last_access;
	public $date_submit;
	public $payload;
	public $ip_last_access;
	public $user_agent_last_access;
	public $nb_access;
	public $status;
	public $datec;
	public $tms;
	public $import_key;

	/**
	 * Constructor.
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		$this->db = $db;
		$this->status = self::STATUS_ACTIVE;
		$this->nb_access = 0;
	}

	/**
	 * Create a secure public link and return the raw token once.
	 *
	 * @param Raccordement $raccordement Parent object
	 * @param string $type Link type
	 * @param string $email Destination email
	 * @param int $validityDays Validity in days
	 * @return string Raw token, empty string on error
	 */
	public function createForRaccordement($raccordement, $type, $email, $validityDays)
	{
		$token = bin2hex(random_bytes(32));
		$now = dol_now();
		$expiration = $now + (max(1, (int) $validityDays) * 86400);

		$this->entity = !empty($raccordement->entity) ? (int) $raccordement->entity : 1;
		$this->fk_raccordement = (int) $raccordement->id;
		$this->type_link = $type;
		$this->token_hash = hash('sha256', $token);
		$this->email_destinataire = $email;
		$this->date_creation = $now;
		$this->date_expiration = $expiration;
		$this->status = self::STATUS_ACTIVE;
		$this->nb_access = 0;

		$sql = 'INSERT INTO '.MAIN_DB_PREFIX.$this->table_element.' (';
		$sql .= 'entity, fk_raccordement, type_link, token_hash, email_destinataire, date_creation, date_expiration, nb_access, status, datec';
		$sql .= ') VALUES (';
		$sql .= ((int) $this->entity).', ';
		$sql .= ((int) $this->fk_raccordement).', ';
		$sql .= "'".$this->db->escape($this->type_link)."', ";
		$sql .= "'".$this->db->escape($this->token_hash)."', ";
		$sql .= "'".$this->db->escape($this->email_destinataire)."', ";
		$sql .= "'".$this->db->idate($now)."', ";
		$sql .= "'".$this->db->idate($expiration)."', ";
		$sql .= '0, ';
		$sql .= ((int) self::STATUS_ACTIVE).', ';
		$sql .= "'".$this->db->idate($now)."'";
		$sql .= ')';

		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			$this->errors[] = $this->error;
			return '';
		}

		$this->id = (int) $this->db->last_insert_id(MAIN_DB_PREFIX.$this->table_element);
		$this->rowid = $this->id;

		return $token;
	}

	/**
	 * Fetch latest link for a raccordement and type.
	 *
	 * @param int $fkRaccordement Parent object id
	 * @param string $type Link type
	 * @return int
	 */
	public function fetchLatestForRaccordement($fkRaccordement, $type)
	{
		global $conf;

		$entityFilter = function_exists('getEntity') ? getEntity('procedurespv_raccordement') : (string) ((int) $conf->entity);
		$sql = 'SELECT rowid, entity, fk_raccordement, type_link, token_hash, email_destinataire, date_creation, date_expiration,';
		$sql .= ' date_first_access, date_last_access, date_submit, payload, ip_last_access, user_agent_last_access, nb_access, status, datec, tms, import_key';
		$sql .= ' FROM '.MAIN_DB_PREFIX.$this->table_element;
		$sql .= ' WHERE fk_raccordement = '.((int) $fkRaccordement);
		$sql .= ' AND entity IN ('.$entityFilter.')';
		$sql .= " AND type_link = '".$this->db->escape($type)."'";
		$sql .= ' ORDER BY rowid DESC';
		$sql .= $this->db->plimit(1);

		return $this->fetchFromSql($sql);
	}

	/**
	 * Fetch public links for a raccordement and type.
	 *
	 * @param int $fkRaccordement Parent object id
	 * @param string $type Link type
	 * @param int $limit Maximum number of rows, 0 for no limit
	 * @param int $offset First row offset
	 * @return array<int, PublicLink>
	 */
	public function fetchAllForRaccordement($fkRaccordement, $type, $limit = 0, $offset = 0)
	{
		global $conf;

		$links = array();
		$entityFilter = function_exists('getEntity') ? getEntity('procedurespv_raccordement') : (string) ((int) $conf->entity);
		$sql = 'SELECT rowid, entity, fk_raccordement, type_link, token_hash, email_destinataire, date_creation, date_expiration,';
		$sql .= ' date_first_access, date_last_access, date_submit, payload, ip_last_access, user_agent_last_access, nb_access, status, datec, tms, import_key';
		$sql .= ' FROM '.MAIN_DB_PREFIX.$this->table_element;
		$sql .= ' WHERE fk_raccordement = '.((int) $fkRaccordement);
		$sql .= ' AND entity IN ('.$entityFilter.')';
		$sql .= " AND type_link = '".$this->db->escape($type)."'";
		$sql .= ' ORDER BY date_creation DESC, rowid DESC';
		if ($limit > 0) {
			$sql .= $this->db->plimit((int) $limit, max(0, (int) $offset));
		}

		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			$this->errors[] = $this->error;
			return $links;
		}

		while (is_object($obj = $this->db->fetch_object($resql))) {
			$link = new self($this->db);
			$link->setVarsFromObj($obj);
			$links[(int) $link->id] = $link;
		}
		$this->db->free($resql);

		return $links;
	}

	/**
	 * Count public links for a raccordement and type.
	 *
	 * @param int $fkRaccordement Parent object id
	 * @param string $type Link type
	 * @return int Number of links, -1 on error
	 */
	public function countForRaccordement($fkRaccordement, $type)
	{
		global $conf;

		$entityFilter = function_exists('getEntity') ? getEntity('procedurespv_raccordement') : (string) ((int) $conf->entity);
		$sql = 'SELECT COUNT(rowid) AS nb';
		$sql .= ' FROM '.MAIN_DB_PREFIX.$this->table_element;
		$sql .= ' WHERE fk_raccordement = '.((int) $fkRaccordement);
		$sql .= ' AND entity IN ('.$entityFilter.')';
		$sql .= " AND type_link = '".$this->db->escape($type)."'";

		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			$this->errors[] = $this->error;
			return -1;
		}

		$obj = $this->db->fetch_object($resql);
		$count = is_object($obj) ? (int) $obj->nb : 0;
		$this->db->free($resql);

		return $count;
	}

	/**
	 * Fetch the last submitted collection payload before the current revision.
	 *
	 * @param int $fkRaccordement Raccordement id
	 * @param int $excludeId Revision to exclude
	 * @return int
	 */
	public function fetchLatestSubmittedForRaccordement($fkRaccordement, $excludeId = 0)
	{
		global $conf;

		$entityFilter = function_exists('getEntity') ? getEntity('procedurespv_raccordement') : (string) ((int) $conf->entity);
		$sql = 'SELECT rowid, entity, fk_raccordement, type_link, token_hash, email_destinataire, date_creation, date_expiration,';
		$sql .= ' date_first_access, date_last_access, date_submit, payload, ip_last_access, user_agent_last_access, nb_access, status, datec, tms, import_key';
		$sql .= ' FROM '.MAIN_DB_PREFIX.$this->table_element;
		$sql .= ' WHERE fk_raccordement = '.((int) $fkRaccordement);
		$sql .= ' AND entity IN ('.$entityFilter.')';
		$sql .= " AND type_link = '".$this->db->escape(self::TYPE_COLLECTE_RACCORDEMENT)."'";
		$sql .= ' AND status = '.self::STATUS_SUBMITTED;
		if ($excludeId > 0) {
			$sql .= ' AND rowid <> '.((int) $excludeId);
		}
		$sql .= ' ORDER BY rowid DESC'.$this->db->plimit(1);

		return $this->fetchFromSql($sql);
	}

	/**
	 * Fetch link from a raw public token.
	 *
	 * @param string $token Raw token
	 * @param string $type Link type
	 * @return int
	 */
	public function fetchByToken($token, $type)
	{
		if ($token === '') {
			return 0;
		}

		$hash = hash('sha256', $token);
		$sql = 'SELECT rowid, entity, fk_raccordement, type_link, token_hash, email_destinataire, date_creation, date_expiration,';
		$sql .= ' date_first_access, date_last_access, date_submit, payload, ip_last_access, user_agent_last_access, nb_access, status, datec, tms, import_key';
		$sql .= ' FROM '.MAIN_DB_PREFIX.$this->table_element;
		$sql .= " WHERE token_hash = '".$this->db->escape($hash)."'";
		$sql .= " AND type_link = '".$this->db->escape($type)."'";
		$sql .= $this->db->plimit(1);

		return $this->fetchFromSql($sql);
	}

	/**
	 * Tell whether the loaded link can be used.
	 *
	 * @return bool
	 */
	public function isUsable()
	{
		if ((int) $this->status !== self::STATUS_ACTIVE) {
			return false;
		}

		if (!empty($this->date_expiration) && (int) $this->date_expiration < dol_now()) {
			return false;
		}

		return (int) $this->fk_raccordement > 0 && (string) $this->token_hash !== '';
	}

	/**
	 * Log public access.
	 *
	 * @param string $ip IP address
	 * @param string $userAgent User agent
	 * @return int
	 */
	public function logAccess($ip, $userAgent)
	{
		if ((int) $this->id <= 0) {
			return -1;
		}

		$now = dol_now();
		$shortUserAgent = dol_trunc($userAgent, 255);

		$sql = 'UPDATE '.MAIN_DB_PREFIX.$this->table_element.' SET';
		if (empty($this->date_first_access)) {
			$sql .= " date_first_access = '".$this->db->idate($now)."',";
		}
		$sql .= " date_last_access = '".$this->db->idate($now)."',";
		$sql .= " ip_last_access = '".$this->db->escape($ip)."',";
		$sql .= " user_agent_last_access = '".$this->db->escape($shortUserAgent)."',";
		$sql .= ' nb_access = nb_access + 1';
		$sql .= ' WHERE rowid = '.((int) $this->id);

		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			$this->errors[] = $this->error;
			return -1;
		}

		$this->date_last_access = $now;
		$this->ip_last_access = $ip;
		$this->user_agent_last_access = $shortUserAgent;
		$this->nb_access = (int) $this->nb_access + 1;
		if (empty($this->date_first_access)) {
			$this->date_first_access = $now;
		}

		return 1;
	}

	/**
	 * Revoke the link.
	 *
	 * @return int
	 */
	public function revoke()
	{
		if ((int) $this->status !== self::STATUS_ACTIVE) {
			$this->error = 'InvalidStatusTransition';
			return -1;
		}

		return $this->setLinkStatus(self::STATUS_REVOKED);
	}

	/**
	 * Mark link as submitted.
	 *
	 * @return int
	 */
	public function markSubmitted()
	{
		if ((int) $this->status !== self::STATUS_ACTIVE) {
			$this->error = 'InvalidStatusTransition';
			return -1;
		}

		$this->date_submit = dol_now();
		return $this->setLinkStatus(self::STATUS_SUBMITTED);
	}

	/** @param array<string,mixed> $payload Submitted form values @return int */
	public function savePayload(array $payload)
	{
		if ((int) $this->id <= 0) {
			return -1;
		}
		$json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		if (!is_string($json)) {
			$this->error = 'ErrorUnableToEncodeCollectionPayload';
			return -1;
		}
		$sql = 'UPDATE '.MAIN_DB_PREFIX.$this->table_element.' SET payload = \''.$this->db->escape($json).'\' WHERE rowid = '.((int) $this->id);
		if (!$this->db->query($sql)) {
			$this->error = $this->db->lasterror();
			return -1;
		}
		$this->payload = $json;
		return 1;
	}

	/** @return array<string,mixed> */
	public function getPayloadArray()
	{
		$decoded = json_decode((string) $this->payload, true);
		return is_array($decoded) ? $decoded : array();
	}

	/**
	 * Return public URL for a raw token.
	 *
	 * @param string $token Raw token
	 * @return string
	 */
	public function getPublicUrl($token)
	{
		return dol_buildpath('/procedurespv/public/raccordement_collecte.php', 3).'?public_token='.urlencode($token);
	}

	/**
	 * Return the display status translation key.
	 *
	 * Explicit terminal states take precedence over derived access and expiry
	 * states so a submitted or revoked revision remains historically stable.
	 *
	 * @return string Translation key
	 */
	public function getStatusLabelKey()
	{
		if ((int) $this->status === self::STATUS_REVOKED) {
			return 'PublicLinkStatusRevoked';
		}
		if ((int) $this->status === self::STATUS_SUBMITTED) {
			return 'PublicLinkStatusSubmitted';
		}
		if ((int) $this->status === self::STATUS_ACTIVE) {
			if (!empty($this->date_expiration) && (int) $this->date_expiration < dol_now()) {
				return 'PublicLinkStatusExpired';
			}
			if (!empty($this->date_first_access) || (int) $this->nb_access > 0) {
				return 'PublicLinkStatusOpened';
			}

			return 'PublicLinkStatusNotOpened';
		}

		return 'PublicLinkStatusUnknown';
	}

	/**
	 * Return the native Dolibarr status badge.
	 *
	 * @param int $mode Display mode
	 * @return string HTML status badge
	 */
	public function getLibStatut($mode = 5)
	{
		global $langs;

		$statusKey = $this->getStatusLabelKey();
		$statusTypes = array(
			'PublicLinkStatusRevoked' => 'status8',
			'PublicLinkStatusSubmitted' => 'status4',
			'PublicLinkStatusExpired' => 'status8',
			'PublicLinkStatusOpened' => 'status1',
			'PublicLinkStatusNotOpened' => 'status0',
			'PublicLinkStatusUnknown' => 'status0',
		);

		return dolGetStatus($langs->trans($statusKey), '', '', $statusTypes[$statusKey], $mode);
	}

	/**
	 * Populate object from SQL.
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
			$this->db->free($resql);
			return 0;
		}
		$this->setVarsFromObj($obj);
		$this->db->free($resql);

		return 1;
	}

	/**
	 * Populate object properties from a database row.
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
		$this->type_link = (string) $obj->type_link;
		$this->token_hash = (string) $obj->token_hash;
		$this->email_destinataire = (string) $obj->email_destinataire;
		$this->date_creation = $this->db->jdate($obj->date_creation);
		$this->date_expiration = $this->db->jdate($obj->date_expiration);
		$this->date_first_access = $this->db->jdate($obj->date_first_access);
		$this->date_last_access = $this->db->jdate($obj->date_last_access);
		$this->date_submit = $this->db->jdate($obj->date_submit);
		$this->payload = isset($obj->payload) ? (string) $obj->payload : '';
		$this->ip_last_access = (string) $obj->ip_last_access;
		$this->user_agent_last_access = (string) $obj->user_agent_last_access;
		$this->nb_access = (int) $obj->nb_access;
		$this->status = (int) $obj->status;
		$this->datec = $this->db->jdate($obj->datec);
		$this->tms = $this->db->jdate($obj->tms);
		$this->import_key = (string) $obj->import_key;
	}

	/**
	 * Set status.
	 *
	 * @param int $status New status
	 * @return int
	 */
	private function setLinkStatus($status)
	{
		if ((int) $this->id <= 0) {
			return -1;
		}

		$sql = 'UPDATE '.MAIN_DB_PREFIX.$this->table_element.' SET';
		$sql .= ' status = '.((int) $status);
		if ((int) $status === self::STATUS_SUBMITTED) {
			$sql .= ", date_submit = '".$this->db->idate((int) $this->date_submit)."'";
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
}
