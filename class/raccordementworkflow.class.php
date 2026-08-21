<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

require_once dol_buildpath('/procedurespv/class/collectionservice.class.php', 0);
require_once dol_buildpath('/procedurespv/class/convention.class.php', 0);
require_once dol_buildpath('/procedurespv/class/piece.class.php', 0);
require_once dol_buildpath('/procedurespv/class/publiclink.class.php', 0);
require_once dol_buildpath('/procedurespv/class/signature.class.php', 0);

/** Centralized workflow rules for a grid-connection procedure. */
class RaccordementWorkflow
{
	public const CONSUEL_PIECE_CODE = 'consuel';
	public const CONSUEL_PIECE_ORIGIN = 'internal_mes';

	/** @var DoliDB */
	private $db;

	/** @var string */
	public $error = '';

	/** @var array<int, string> */
	public $errors = array();

	/** @param DoliDB $db Database handler */
	public function __construct($db)
	{
		$this->db = $db;
	}

	/**
	 * Return the latest collection revision.
	 *
	 * @param Raccordement $raccordement Parent object
	 * @return PublicLink|null
	 */
	public function getLatestCollectionRevision($raccordement)
	{
		$link = new PublicLink($this->db);
		$result = $link->fetchLatestForRaccordement((int) $raccordement->id, PublicLink::TYPE_COLLECTE_RACCORDEMENT);
		if ($result < 0) {
			$this->error = $link->error;
			$this->errors = $link->errors;
		}

		return $result > 0 ? $link : null;
	}

	/**
	 * Return the internal Consuel supporting document.
	 *
	 * @param Raccordement $raccordement Parent object
	 * @return Piece|null
	 */
	public function getConsuelPiece($raccordement)
	{
		$pieceRepository = new Piece($this->db);
		foreach ($pieceRepository->fetchAllByRaccordement((int) $raccordement->id, 0) as $piece) {
			if ((string) $piece->code_piece === self::CONSUEL_PIECE_CODE && (string) $piece->origin === self::CONSUEL_PIECE_ORIGIN) {
				return $piece;
			}
		}

		return null;
	}

	/**
	 * Test that a Piece references a file that still exists.
	 *
	 * @param Piece|null $piece Supporting document
	 * @return bool
	 */
	public function pieceFileExists($piece)
	{
		if (!is_object($piece) || trim((string) $piece->filename) === '' || trim((string) $piece->filepath) === '') {
			return false;
		}

		return is_file(rtrim((string) $piece->filepath, '/').'/'.(string) $piece->filename);
	}

	/**
	 * Test the three factual Consuel requirements.
	 *
	 * @param Raccordement $raccordement Parent object
	 * @return bool
	 */
	public function hasCompleteConsuel($raccordement)
	{
		return !empty($raccordement->date_consuel)
			&& trim((string) $raccordement->ref_consuel) !== ''
			&& $this->pieceFileExists($this->getConsuelPiece($raccordement));
	}

	/**
	 * Return missing prerequisites for requesting commissioning.
	 *
	 * Returned values are translation keys so every UI uses the same rules.
	 *
	 * @param Raccordement $raccordement Parent object
	 * @return list<string>
	 */
	public function getMesMissingRequirements($raccordement)
	{
		$missing = array();
		$revision = $this->getLatestCollectionRevision($raccordement);
		if (!is_object($revision) || (int) $revision->status !== PublicLink::STATUS_SUBMITTED) {
			$missing[] = 'MESRequirementCollectionSubmitted';
		} else {
			$collectionService = new CollectionService($this->db);
			if (!$collectionService->canValidateCollection((int) $raccordement->id, (int) $revision->id)) {
				$missing[] = $collectionService->error !== '' ? $collectionService->error : 'MESRequirementCollectionValidated';
			}
		}

		$pieceRepository = new Piece($this->db);
		foreach ($pieceRepository->fetchAllByRaccordement((int) $raccordement->id, 0) as $piece) {
			if ((string) $piece->code_piece === self::CONSUEL_PIECE_CODE && (string) $piece->origin === self::CONSUEL_PIECE_ORIGIN) {
				continue;
			}
			if ((int) $piece->required === 1 && ((int) $piece->status !== Piece::STATUS_VALID || !$this->pieceFileExists($piece))) {
				$missing[] = 'MESRequirementInternalDocumentsValid';
				break;
			}
		}

		if (!in_array((int) $raccordement->demande_status, array(2, 4), true)) {
			$missing[] = 'MESRequirementEnedisRequestAdvanced';
		}
		if ($raccordement->isCardiApplicable()) {
			if ((int) $raccordement->cardi_required === 2) {
				$missing[] = 'MESRequirementCardiDetermined';
			} elseif ((int) $raccordement->cardi_required === 1 && (int) $raccordement->cardi_status !== 6) {
				$missing[] = 'MESRequirementCardiValidated';
			}
		}

		$conventions = (new Convention($this->db))->fetchAllByRaccordement((int) $raccordement->id);
		$latestConvention = !empty($conventions) ? reset($conventions) : false;
		if (!is_object($latestConvention) || !in_array((int) $latestConvention->status, array(Convention::STATUS_SIGNED, Convention::STATUS_RETURNED_ENEDIS, Convention::STATUS_VALIDATED), true)) {
			$missing[] = 'MESRequirementConventionSigned';
		}

		if (empty($raccordement->date_consuel)) {
			$missing[] = 'MESRequirementConsuelDate';
		}
		if (trim((string) $raccordement->ref_consuel) === '') {
			$missing[] = 'MESRequirementConsuelReference';
		}
		if (!$this->pieceFileExists($this->getConsuelPiece($raccordement))) {
			$missing[] = 'MESRequirementConsuelFile';
		}

		return array_values(array_unique($missing));
	}

	/** @param Raccordement $raccordement Parent object @return bool */
	public function canRequestMes($raccordement)
	{
		return count($this->getMesMissingRequirements($raccordement)) === 0;
	}

	/**
	 * Test whether every required stage is complete.
	 *
	 * Optional documents and CARDi explicitly marked as not required are ignored.
	 *
	 * @param Raccordement $raccordement Parent object
	 * @return bool
	 */
	public function isProcedureComplete($raccordement)
	{
		return (int) $raccordement->mes_status === 4 && $this->canRequestMes($raccordement);
	}

	/**
	 * Reconcile the stored overall status after a detailed workflow mutation.
	 *
	 * @param Raccordement $raccordement Parent object
	 * @return int Recommended overall status
	 */
	public function getReconciledStatus($raccordement)
	{
		if ((int) $raccordement->status < 0) {
			return (int) $raccordement->status;
		}
		if ($this->isProcedureComplete($raccordement)) {
			return 16;
		}
		if ((int) $raccordement->status !== 16) {
			return (int) $raccordement->status;
		}

		$revision = $this->getLatestCollectionRevision($raccordement);
		if (!is_object($revision) || (int) $revision->status !== PublicLink::STATUS_SUBMITTED) {
			return 2;
		}
		$collectionService = new CollectionService($this->db);
		if (!$collectionService->canValidateCollection((int) $raccordement->id, (int) $revision->id)) {
			return 5;
		}
		if (!in_array((int) $raccordement->demande_status, array(2, 4), true)) {
			return 6;
		}
		if ($raccordement->isCardiApplicable() && ((int) $raccordement->cardi_required === 2 || ((int) $raccordement->cardi_required === 1 && (int) $raccordement->cardi_status !== 6))) {
			return 9;
		}
		$conventions = (new Convention($this->db))->fetchAllByRaccordement((int) $raccordement->id);
		$latestConvention = !empty($conventions) ? reset($conventions) : false;
		if (!is_object($latestConvention) || !in_array((int) $latestConvention->status, array(Convention::STATUS_SIGNED, Convention::STATUS_RETURNED_ENEDIS, Convention::STATUS_VALIDATED), true)) {
			return 11;
		}
		if (!$this->canRequestMes($raccordement)) {
			return 13;
		}

		return in_array((int) $raccordement->mes_status, array(2, 3, 5), true) ? 14 : 13;
	}

	/**
	 * Return detailed stage states for the synthesis table.
	 *
	 * @param Raccordement $raccordement Parent object
	 * @return array<string, array{label:string,type:string,complete:bool,required:bool}>
	 */
	public function getStageStates($raccordement)
	{
		$revision = $this->getLatestCollectionRevision($raccordement);
		$collection = array('label' => 'RaccordementStatusCollecteToSend', 'type' => 'status0', 'complete' => false, 'required' => true);
		if (is_object($revision)) {
			if ((int) $revision->status === PublicLink::STATUS_SUBMITTED) {
				$collectionService = new CollectionService($this->db);
				$collectionValidatedStatuses = array(6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16);
				if (in_array((int) $raccordement->status, $collectionValidatedStatuses, true) && $collectionService->canValidateCollection((int) $raccordement->id, (int) $revision->id)) {
					$collection = array('label' => 'CollecteValidated', 'type' => 'status4', 'complete' => true, 'required' => true);
				} elseif ((int) $raccordement->status === 5) {
					$collection = array('label' => 'RaccordementStatusToControl', 'type' => 'status3', 'complete' => false, 'required' => true);
				} else {
					$collection = array('label' => 'RaccordementStatusCollecteSubmitted', 'type' => 'status1', 'complete' => false, 'required' => true);
				}
			} elseif ((int) $revision->status === PublicLink::STATUS_ACTIVE) {
				$collection = array('label' => 'RaccordementStatusCollecteSent', 'type' => 'status1', 'complete' => false, 'required' => true);
			}
		}

		$signature = new Signature($this->db);
		$signatureResult = is_object($revision)
			? $signature->fetchForRevision((int) $raccordement->id, (int) $revision->id)
			: $signature->fetchLatestForRaccordement((int) $raccordement->id);
		$mandate = array('label' => 'SignatureStatusToGenerate', 'type' => 'status0', 'complete' => false, 'required' => true);
		if ($signatureResult > 0) {
			$signatureTypes = array(
				Signature::STATUS_TO_GENERATE => 'status0',
				Signature::STATUS_SENT_TO_CLIENT => 'status1',
				Signature::STATUS_WAITING_SIGNATURE => 'status0',
				Signature::STATUS_SIGNED_ONLINE => 'status4',
				Signature::STATUS_TO_CONTROL => 'status3',
				Signature::STATUS_NON_COMPLIANT => 'status8',
				Signature::STATUS_VALIDATED => 'status4',
			);
			$mandate = array(
				'label' => $signature->getStatusLabelKey(),
				'type' => $signatureTypes[(int) $signature->status] ?? 'status0',
				'complete' => (int) $signature->status === Signature::STATUS_VALIDATED,
				'required' => true,
			);
		}

		$requestTypes = array(0 => 'status3', 1 => 'status4', 2 => 'status1', 3 => 'status3', 4 => 'status1');
		$requestLabels = array(0 => 'RequestStatusToComplete', 1 => 'RequestStatusComplete', 2 => 'RequestStatusDeposited', 3 => 'RequestStatusComplementRequested', 4 => 'RequestStatusInstruction');
		$requestComplete = in_array((int) $raccordement->demande_status, array(2, 4), true);

		$cardiTypes = array(0 => 'status0', 1 => 'status3', 2 => 'status3', 3 => 'status0', 4 => 'status1', 5 => 'status3', 6 => 'status4', 7 => 'status8');
		$cardiLabels = array(0 => 'CardiStatusNotRequired', 1 => 'CardiStatusToPrepare', 2 => 'CardiStatusToSendClient', 3 => 'CardiStatusWaitingClient', 4 => 'CardiStatusReceived', 5 => 'CardiStatusToControl', 6 => 'CardiStatusValidated', 7 => 'CardiStatusNonCompliant');
		$cardiPowerApplicable = $raccordement->isCardiApplicable();
		$cardiRequired = $cardiPowerApplicable && (int) $raccordement->cardi_required === 1;
		$cardiApplicable = $cardiPowerApplicable && (int) $raccordement->cardi_required !== 0;
		if ((int) $raccordement->cardi_required === 2) {
			$cardiLabels[(int) $raccordement->cardi_status] = 'CardiStatusToDetermine';
			$cardiTypes[(int) $raccordement->cardi_status] = 'status0';
		}

		$conventions = (new Convention($this->db))->fetchAllByRaccordement((int) $raccordement->id);
		$latestConvention = !empty($conventions) ? reset($conventions) : false;
		$conventionState = array('label' => 'ConventionStatusNotReceived', 'type' => 'status0', 'complete' => false, 'required' => true);
		if (is_object($latestConvention)) {
			$conventionTypes = array(0 => 'status0', 1 => 'status1', 2 => 'status3', 3 => 'status3', 4 => 'status1', 5 => 'status4', 6 => 'status4', 7 => 'status4', 8 => 'status8');
			$conventionState = array(
				'label' => $latestConvention->getStatusLabelKey(),
				'type' => $conventionTypes[(int) $latestConvention->status] ?? 'status0',
				'complete' => in_array((int) $latestConvention->status, array(Convention::STATUS_SIGNED, Convention::STATUS_RETURNED_ENEDIS, Convention::STATUS_VALIDATED), true),
				'required' => true,
			);
		}

		$mesTypes = array(0 => 'status0', 1 => 'status3', 2 => 'status1', 3 => 'status1', 4 => 'status4', 5 => 'status8', 6 => 'status8');
		$mesLabels = array(0 => 'MESStatusNotRequested', 1 => 'MESStatusToRequest', 2 => 'MESStatusRequested', 3 => 'MESStatusPlanned', 4 => 'MESStatusDone', 5 => 'MESStatusBlocked', 6 => 'MESStatusCanceled');

		return array(
			'collection' => $collection,
			'mandate' => $mandate,
			'request' => array('label' => $requestLabels[(int) $raccordement->demande_status] ?? 'RequestStatusToComplete', 'type' => $requestTypes[(int) $raccordement->demande_status] ?? 'status3', 'complete' => $requestComplete, 'required' => true),
			'cardi' => array('label' => $cardiLabels[(int) $raccordement->cardi_status] ?? 'CardiStatusToDetermine', 'type' => $cardiTypes[(int) $raccordement->cardi_status] ?? 'status0', 'complete' => !$cardiApplicable || ($cardiRequired && (int) $raccordement->cardi_status === 6), 'required' => $cardiApplicable),
			'convention' => $conventionState,
			'mes' => array('label' => $mesLabels[(int) $raccordement->mes_status] ?? 'MESStatusNotRequested', 'type' => $mesTypes[(int) $raccordement->mes_status] ?? 'status0', 'complete' => (int) $raccordement->mes_status === 4, 'required' => true),
		);
	}
}
