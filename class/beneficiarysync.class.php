<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
require_once DOL_DOCUMENT_ROOT.'/contact/class/contact.class.php';

/** Synchronize the submitted beneficiary with native Dolibarr third parties and contacts. */
class BeneficiarySync
{
	/** @var DoliDB */
	private $db;
	public $error = '';
	public $errors = array();

	/** @param DoliDB $db Database handler */
	public function __construct($db)
	{
		$this->db = $db;
	}

	/** @param string $siret SIRET @param int $entity Owning entity, 0 for accessible entities @return Societe|null */
	public function findBySiret($siret, $entity = 0)
	{
		$siret = preg_replace('/\D+/', '', $siret);
		if (!is_string($siret) || strlen($siret) !== 14) {
			return null;
		}
		$entityFilter = $entity > 0 ? (string) ((int) $entity) : getEntity('societe');
		$sql = 'SELECT rowid FROM '.MAIN_DB_PREFIX.'societe WHERE idprof2 = \''.$this->db->escape($siret).'\' AND entity IN ('.$entityFilter.') ORDER BY entity DESC, rowid ASC'.$this->db->plimit(1);
		$resql = $this->db->query($sql);
		$obj = $resql ? $this->db->fetch_object($resql) : false;
		if (!is_object($obj)) {
			return null;
		}
		$thirdparty = new Societe($this->db);
		$result = $thirdparty->fetch((int) $obj->rowid);
		return $result > 0 ? $thirdparty : null;
	}

	/** @param string $siret SIRET @param int $entity Owning entity, 0 for accessible entities @return array<string,mixed> */
	public function getPrefillBySiret($siret, $entity = 0)
	{
		$thirdparty = $this->findBySiret($siret, $entity);
		if (!is_object($thirdparty)) {
			return array();
		}
		$result = array('fk_soc' => (int) $thirdparty->id, 'client_name' => (string) $thirdparty->name, 'client_siret' => (string) $thirdparty->idprof2, 'client_email' => (string) $thirdparty->email, 'client_phone' => (string) $thirdparty->phone, 'address' => (string) $thirdparty->address, 'zip' => (string) $thirdparty->zip, 'town' => (string) $thirdparty->town, 'country_id' => (int) $thirdparty->country_id);
		$sql = 'SELECT rowid FROM '.MAIN_DB_PREFIX.'socpeople WHERE fk_soc = '.((int) $thirdparty->id).' AND entity = '.((int) $thirdparty->entity).' ORDER BY rowid ASC'.$this->db->plimit(1);
		$resql = $this->db->query($sql);
		$obj = $resql ? $this->db->fetch_object($resql) : false;
		if (is_object($obj)) {
			$contact = new Contact($this->db);
			if ($contact->fetch((int) $obj->rowid) > 0) {
				$result['contact'] = array('civility' => (string) $contact->civility_code, 'lastname' => (string) $contact->lastname, 'firstname' => (string) $contact->firstname, 'function' => (string) $contact->poste, 'email' => (string) $contact->email, 'phone' => (string) $contact->phone, 'mobile' => (string) $contact->phone_mobile);
			}
		}
		return $result;
	}

	/**
	 * @param Raccordement $raccordement Parent object
	 * @param array<string,mixed> $payload Collection payload
	 * @param User $user Acting user
	 * @return int Third party id, 0 when no SIRET, negative on error
	 */
	public function synchronize($raccordement, array $payload, $user)
	{
		if (!is_object($user) || (empty($user->admin) && (!$user->hasRight('societe', 'creer') || !$user->hasRight('societe', 'contact', 'creer')))) {
			$this->error = 'NotEnoughPermissionsToSynchronizeBeneficiary';
			return -1;
		}
		$siret = preg_replace('/\D+/', '', isset($payload['client_siret']) ? (string) $payload['client_siret'] : '');
		if (!is_string($siret) || $siret === '') {
			return 0;
		}
		if (strlen($siret) !== 14) {
			$this->error = 'InvalidSiret';
			return -1;
		}
		$contactData = isset($payload['beneficiary_contact']) && is_array($payload['beneficiary_contact']) ? $payload['beneficiary_contact'] : array();
		$thirdparty = $this->findBySiret($siret, (int) $raccordement->entity);
		$isNew = !is_object($thirdparty);
		if ($isNew) {
			$thirdparty = new Societe($this->db);
			$thirdparty->entity = (int) $raccordement->entity;
			$thirdparty->client = 1;
		}
		$thirdparty->name = trim((string) ($payload['client_name'] ?? ''));
		$thirdparty->idprof2 = $siret;
		$thirdparty->email = trim((string) ($payload['client_email'] ?? ''));
		$thirdparty->phone = trim((string) ($payload['client_phone'] ?? ''));
		$thirdparty->address = trim((string) ($contactData['address'] ?? ''));
		if (!empty($contactData['street_number'])) {
			$thirdparty->address = trim((string) $contactData['street_number'].' '.$thirdparty->address);
		}
		if (!empty($contactData['address_complement'])) {
			$thirdparty->address = trim($thirdparty->address."\n".(string) $contactData['address_complement']);
		}
		$thirdparty->zip = trim((string) ($contactData['zip'] ?? ''));
		$thirdparty->town = trim((string) ($contactData['town'] ?? ''));
		$thirdparty->country_id = (int) ($contactData['country_id'] ?? 0);
		if ($thirdparty->name === '') {
			$this->error = 'BeneficiaryOrganizationNameRequired';
			return -1;
		}

		$this->db->begin();
		$result = $isNew ? $thirdparty->create($user) : $thirdparty->update((int) $thirdparty->id, $user);
		if ($result <= 0) {
			$this->error = $thirdparty->error;
			$this->errors = $thirdparty->errors;
			$this->db->rollback();
			return -1;
		}
		$thirdpartyId = $isNew ? (int) $result : (int) $thirdparty->id;
		$contactId = $this->findContactId($thirdpartyId, (int) $raccordement->entity, (string) ($payload['client_email'] ?? ''), (string) ($contactData['lastname'] ?? ''), (string) ($contactData['firstname'] ?? ''));
		$contact = new Contact($this->db);
		if ($contactId > 0 && $contact->fetch($contactId) <= 0) {
			$contactId = 0;
		}
		$contact->socid = $contact->fk_soc = $thirdpartyId;
		$contact->entity = (int) $raccordement->entity;
		$contact->civility_code = (string) ($contactData['civility'] ?? '');
		$contact->lastname = (string) ($contactData['lastname'] ?? '');
		$contact->firstname = (string) ($contactData['firstname'] ?? '');
		$contact->poste = (string) ($contactData['function'] ?? '');
		$contact->email = (string) ($payload['client_email'] ?? '');
		$contact->phone = (string) ($payload['client_phone'] ?? '');
		$contact->phone_mobile = (string) ($contactData['mobile'] ?? '');
		$contact->address = $thirdparty->address;
		$contact->zip = $thirdparty->zip;
		$contact->town = $thirdparty->town;
		$contact->country_id = $thirdparty->country_id;
		if ($contact->lastname !== '' || $contact->firstname !== '') {
			$result = $contactId > 0 ? $contact->update($contactId, $user) : $contact->create($user);
			if ($result <= 0) {
				$this->error = $contact->error;
				$this->errors = $contact->errors;
				$this->db->rollback();
				return -1;
			}
		}
		$raccordement->fk_soc = $thirdpartyId;
		$raccordement->context['trigger_reason'] = 'beneficiary_synchronized';
		$raccordement->context['changed_fields'] = array('fk_soc');
		if ($raccordement->update($user, 1) < 0) {
			$this->error = $raccordement->error;
			$this->db->rollback();
			return -1;
		}
		$this->db->commit();
		return $thirdpartyId;
	}

	/** @param int $thirdpartyId Third party id @param int $entity Entity @param string $email Email @param string $lastname Lastname @param string $firstname Firstname @return int */
	private function findContactId($thirdpartyId, $entity, $email, $lastname, $firstname)
	{
		$sql = 'SELECT rowid FROM '.MAIN_DB_PREFIX.'socpeople WHERE fk_soc = '.((int) $thirdpartyId).' AND entity = '.((int) $entity);
		if ($email !== '') {
			$sql .= ' AND email = \''.$this->db->escape($email).'\'';
		} else {
			$sql .= ' AND lastname = \''.$this->db->escape($lastname).'\' AND firstname = \''.$this->db->escape($firstname).'\'';
		}
		$sql .= ' ORDER BY rowid ASC'.$this->db->plimit(1);
		$resql = $this->db->query($sql);
		$obj = $resql ? $this->db->fetch_object($resql) : false;
		return is_object($obj) ? (int) $obj->rowid : 0;
	}
}
