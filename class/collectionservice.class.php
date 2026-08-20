<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

require_once dol_buildpath('/procedurespv/class/piece.class.php', 0);
require_once dol_buildpath('/procedurespv/class/signature.class.php', 0);

/** Business rules for collection revisions, documents and allowed actions. */
class CollectionService
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

	/**
	 * @param array<string,mixed> $payload Collection payload
	 * @return array<int,array{code:string,label:string,required:int}>
	 */
	public static function getPieceDefinitions(array $payload)
	{
		$status = isset($payload['beneficiary_status']) ? (string) $payload['beneficiary_status'] : (isset($payload['client_type']) ? (string) $payload['client_type'] : 'particulier');
		$productionSite = isset($payload['production_site']) && is_array($payload['production_site']) ? $payload['production_site'] : array();
		$isCompany = $status === 'societe';
		$isOtherPdl = $isCompany && ($productionSite['already_connected'] ?? '') === 'yes' && ($productionSite['pdl_choice'] ?? '') === 'existing_other_legal_entity';

		return array(
			array('code' => 'facture_electricite', 'label' => 'PieceFactureElectricite', 'required' => $isCompany ? 1 : 0),
			array('code' => 'kbis_beneficiaire', 'label' => 'PieceKbisBeneficiary', 'required' => $isCompany ? 1 : 0),
			array('code' => 'kbis_etablissement_production', 'label' => 'PieceKbisProductionSite', 'required' => $isCompany ? 1 : 0),
			array('code' => 'autorisation_administrative', 'label' => 'PieceAdministrativeAuthorization', 'required' => $isCompany ? 1 : 0),
			array('code' => 'card_pdl_tiers', 'label' => 'PieceCardPdlOtherLegalEntity', 'required' => $isOtherPdl ? 1 : 0),
		);
	}

	/**
	 * Ensure every expected piece exists for the revision.
	 *
	 * @param Raccordement $raccordement Parent object
	 * @param PublicLink $publicLink Revision
	 * @param array<string,mixed> $payload Collection payload
	 * @param Translate $langs Language handler
	 * @param bool $submitted Revision submitted
	 * @return int
	 */
	public function prelistRevision($raccordement, $publicLink, array $payload, $langs, $submitted = false)
	{
		$piece = new Piece($this->db);
		foreach (self::getPieceDefinitions($payload) as $definition) {
			$result = $piece->ensureDefinition($raccordement, (int) $publicLink->id, $definition['code'], $langs->transnoentitiesnoconv($definition['label']), 'client', (int) $definition['required'], $submitted);
			if ($result <= 0) {
				$this->error = $piece->error;
				$this->errors[] = $this->error;
				return -1;
			}
		}
		return 1;
	}

	/** @param int $fkRaccordement Raccordement id @param int $fkPublicLink Revision id @return bool */
	public function canValidateCollection($fkRaccordement, $fkPublicLink)
	{
		$signature = new Signature($this->db);
		if (
			$signature->fetchForRevision($fkRaccordement, $fkPublicLink) <= 0
			|| (int) $signature->status !== Signature::STATUS_VALIDATED
			|| trim((string) $signature->filepath) === ''
			|| trim((string) $signature->filename) === ''
			|| !is_file(rtrim((string) $signature->filepath, '/').'/'.(string) $signature->filename)
		) {
			$this->error = 'CollectionRequiresValidatedMandate';
			return false;
		}
		$piece = new Piece($this->db);
		foreach ($piece->fetchAllByRaccordement($fkRaccordement, $fkPublicLink) as $document) {
			if (
				(int) $document->required === 1
				&& (
					(int) $document->status !== Piece::STATUS_VALID
					|| trim((string) $document->filepath) === ''
					|| trim((string) $document->filename) === ''
					|| !is_file(rtrim((string) $document->filepath, '/').'/'.(string) $document->filename)
				)
			) {
				$this->error = 'CollectionRequiresValidatedDocuments';
				return false;
			}
		}
		return true;
	}

	/** @param int $objectStatus Raccordement status @param string $action Action @return bool */
	public static function isRaccordementActionAllowed($objectStatus, $action)
	{
		$allowed = array(
			'send_collecte' => array(0, 1),
			'mark_collecte_submitted' => array(2, 3),
			'validate_collecte' => array(4, 5),
			'mark_ready_to_deposit' => array(6),
			'mark_deposited' => array(7),
			'mark_complete' => array(8),
			'mark_validated' => array(9),
			'mark_service' => array(10),
		);
		return isset($allowed[$action]) && in_array((int) $objectStatus, $allowed[$action], true);
	}
}
