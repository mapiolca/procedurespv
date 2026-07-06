<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

if (!defined('NOLOGIN')) {
	define('NOLOGIN', 1);
}
if (!defined('NOREQUIREMENU')) {
	define('NOREQUIREMENU', 1);
}

require '../../../main.inc.php';
require_once dol_buildpath('/procedurespv/class/raccordement.class.php', 0);
require_once dol_buildpath('/procedurespv/class/publiclink.class.php', 0);
require_once dol_buildpath('/procedurespv/class/piece.class.php', 0);
require_once dol_buildpath('/procedurespv/class/signature.class.php', 0);
require_once dol_buildpath('/procedurespv/lib/procedurespv.lib.php', 0);
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/company.lib.php';

$langs->loadLangs(array('procedurespv@procedurespv'));

/**
 * Load configured ENEDIS mandate PDF model.
 *
 * @param DoliDB $db Database handler
 * @return object|null
 */
function procedurespvLoadMandatEnedisPdfModel($db)
{
	$modelName = getDolGlobalString('PROCEDURESPV_MANDATENEDIS_ADDON_PDF', '');
	if ($modelName === '') {
		$modelName = getDolGlobalString('PROCEDURESPV_PDF_MODEL_MANDAT_ENEDIS', '');
	}
	if ($modelName === '') {
		$modelName = 'mandatenedis';
	}

	$modelName = preg_replace('/[^a-zA-Z0-9_]/', '', $modelName);
	if (!is_string($modelName) || $modelName === '') {
		$modelName = 'mandatenedis';
	}

	$file = dol_buildpath('/procedurespv/core/modules/procedurespv/doc/pdf_'.$modelName.'.modules.php', 0);
	if (!is_readable($file) && $modelName !== 'mandatenedis') {
		dol_syslog('ProceduresPV: configured mandate PDF model '.$modelName.' not found, fallback to mandatenedis', LOG_WARNING);
		$modelName = 'mandatenedis';
		$file = dol_buildpath('/procedurespv/core/modules/procedurespv/doc/pdf_'.$modelName.'.modules.php', 0);
	}
	if (!is_readable($file)) {
		return null;
	}

	require_once $file;

	$className = 'pdf_'.$modelName;
	if (!class_exists($className)) {
		if ($modelName !== 'mandatenedis') {
			dol_syslog('ProceduresPV: configured mandate PDF class '.$className.' not found, fallback to pdf_mandatenedis', LOG_WARNING);
			require_once dol_buildpath('/procedurespv/core/modules/procedurespv/doc/pdf_mandatenedis.modules.php', 0);
			$className = 'pdf_mandatenedis';
		}
		if (!class_exists($className)) {
			return null;
		}
	}

	return new $className($db);
}

/**
 * Return a company setup value for the requested entity with a controlled fallback.
 *
 * @param string $name Constant name
 * @param string $fallback Fallback value
 * @param int    $entity Entity id
 * @return string
 */
function procedurespvPublicGetCompanyConst($name, $fallback, $entity)
{
	global $db, $conf;

	$value = dolibarr_get_const($db, $name, $entity);
	if ($value === '' && (int) $entity === (int) $conf->entity) {
		$value = getDolGlobalString($name, '');
	}
	if ($value === '') {
		$value = $fallback;
	}

	return trim((string) $value);
}

/**
 * Return the public logo URL for a company entity.
 *
 * @param int $entity Entity id
 * @return string
 */
function procedurespvPublicGetCompanyLogoUrl($entity)
{
	global $conf, $mysoc;

	$entity = $entity > 0 ? $entity : (int) $conf->entity;
	$isCurrentEntity = (int) $entity === (int) $conf->entity;
	$logoDir = '';

	if (isset($conf->mycompany->multidir_output[$entity]) && $conf->mycompany->multidir_output[$entity] !== '') {
		$logoDir = (string) $conf->mycompany->multidir_output[$entity];
	} elseif (isset($conf->mycompany->dir_output) && $conf->mycompany->dir_output !== '') {
		$logoDir = (string) $conf->mycompany->dir_output;
	}

	if ($logoDir === '') {
		return '';
	}

	$smallFallback = ($isCurrentEntity && is_object($mysoc) && !empty($mysoc->logo_small)) ? (string) $mysoc->logo_small : '';
	$logoFallback = ($isCurrentEntity && is_object($mysoc) && !empty($mysoc->logo)) ? (string) $mysoc->logo : '';
	$logoSmall = procedurespvPublicGetCompanyConst('MAIN_INFO_SOCIETE_LOGO_SMALL', $smallFallback, $entity);
	$logo = procedurespvPublicGetCompanyConst('MAIN_INFO_SOCIETE_LOGO', $logoFallback, $entity);

	$candidates = array();
	if ($logoSmall !== '') {
		$candidates[] = 'logos/thumbs/'.$logoSmall;
	}
	if ($logo !== '') {
		$candidates[] = 'logos/'.$logo;
	}

	foreach ($candidates as $candidate) {
		if (is_readable($logoDir.'/'.$candidate)) {
			return DOL_URL_ROOT.'/viewimage.php?modulepart=mycompany&entity='.$entity.'&file='.urlencode($candidate);
		}
	}

	return '';
}

/**
 * Build legal company data displayed on the public page.
 *
 * @param int $entity Entity id
 * @return array{name:string, lines:array<int,array{label:string,value:string}>}
 */
function procedurespvPublicGetCompanyLegalData($entity)
{
	global $conf, $mysoc;

	$entity = $entity > 0 ? $entity : (int) $conf->entity;
	$isCurrentEntity = (int) $entity === (int) $conf->entity;
	$nameFallback = ($isCurrentEntity && is_object($mysoc) && !empty($mysoc->name)) ? (string) $mysoc->name : '';
	$addressFallback = ($isCurrentEntity && is_object($mysoc) && !empty($mysoc->address)) ? (string) $mysoc->address : '';
	$zipFallback = ($isCurrentEntity && is_object($mysoc) && !empty($mysoc->zip)) ? (string) $mysoc->zip : '';
	$townFallback = ($isCurrentEntity && is_object($mysoc) && !empty($mysoc->town)) ? (string) $mysoc->town : '';
	$emailFallback = ($isCurrentEntity && is_object($mysoc) && !empty($mysoc->email)) ? (string) $mysoc->email : '';
	$urlFallback = ($isCurrentEntity && is_object($mysoc) && !empty($mysoc->url)) ? (string) $mysoc->url : '';
	$sirenFallback = ($isCurrentEntity && is_object($mysoc) && !empty($mysoc->idprof1)) ? (string) $mysoc->idprof1 : '';
	$siretFallback = ($isCurrentEntity && is_object($mysoc) && !empty($mysoc->idprof2)) ? (string) $mysoc->idprof2 : '';
	$nafFallback = ($isCurrentEntity && is_object($mysoc) && !empty($mysoc->idprof3)) ? (string) $mysoc->idprof3 : '';
	$rcsFallback = ($isCurrentEntity && is_object($mysoc) && !empty($mysoc->idprof4)) ? (string) $mysoc->idprof4 : '';
	$vatFallback = ($isCurrentEntity && is_object($mysoc) && !empty($mysoc->tva_intra)) ? (string) $mysoc->tva_intra : '';
	$juridicalStatusFallback = ($isCurrentEntity && is_object($mysoc) && !empty($mysoc->forme_juridique_code)) ? (string) $mysoc->forme_juridique_code : '';

	$name = procedurespvPublicGetCompanyConst('MAIN_INFO_SOCIETE_NOM', $nameFallback, $entity);
	if ($name === '') {
		$name = procedurespvPublicGetCompanyConst('MAIN_INFO_SOCIETE_NAME', $nameFallback, $entity);
	}

	$address = procedurespvPublicGetCompanyConst('MAIN_INFO_SOCIETE_ADDRESS', $addressFallback, $entity);
	$zip = procedurespvPublicGetCompanyConst('MAIN_INFO_SOCIETE_ZIP', $zipFallback, $entity);
	$town = procedurespvPublicGetCompanyConst('MAIN_INFO_SOCIETE_TOWN', $townFallback, $entity);
	$email = procedurespvPublicGetCompanyConst('MAIN_INFO_SOCIETE_MAIL', $emailFallback, $entity);
	if ($email === '') {
		$email = procedurespvPublicGetCompanyConst('MAIN_INFO_SOCIETE_EMAIL', $emailFallback, $entity);
	}
	$url = procedurespvPublicGetCompanyConst('MAIN_INFO_SOCIETE_WEB', $urlFallback, $entity);
	$siren = procedurespvPublicGetCompanyConst('MAIN_INFO_SIREN', $sirenFallback, $entity);
	$siret = procedurespvPublicGetCompanyConst('MAIN_INFO_SIRET', $siretFallback, $entity);
	$naf = procedurespvPublicGetCompanyConst('MAIN_INFO_APE', $nafFallback, $entity);
	if ($naf === '') {
		$naf = procedurespvPublicGetCompanyConst('MAIN_INFO_NAF', $nafFallback, $entity);
	}
	$rcs = procedurespvPublicGetCompanyConst('MAIN_INFO_RCS', $rcsFallback, $entity);
	$vat = procedurespvPublicGetCompanyConst('MAIN_INFO_TVAINTRA', $vatFallback, $entity);
	$capital = procedurespvPublicGetCompanyConst('MAIN_INFO_SOCIETE_CAPITAL', procedurespvPublicGetCompanyConst('MAIN_INFO_CAPITAL', '', $entity), $entity);
	$juridicalStatusCode = procedurespvPublicGetCompanyConst('MAIN_INFO_SOCIETE_FORME_JURIDIQUE', $juridicalStatusFallback, $entity);
	$juridicalStatus = $juridicalStatusCode !== '' ? getFormeJuridiqueLabel($juridicalStatusCode) : '';

	$lines = array();
	$addressLine = trim($address.' '.trim($zip.' '.$town));
	if ($addressLine !== '') {
		$lines[] = array('label' => '', 'value' => $addressLine);
	}
	foreach (array(
		'PublicLegalJuridicalStatus' => $juridicalStatus,
		'PublicLegalSiren' => $siren,
		'PublicLegalSiret' => $siret,
		'PublicLegalApe' => $naf,
		'PublicLegalRcs' => $rcs,
		'PublicLegalVat' => $vat,
		'PublicLegalCapital' => $capital,
		'PublicLegalEmail' => $email,
		'PublicLegalWebsite' => $url,
	) as $label => $value) {
		if ($value !== '') {
			$lines[] = array('label' => $label, 'value' => $value);
		}
	}

	return array('name' => $name, 'lines' => $lines);
}

/**
 * Print the company brand header for the public page.
 *
 * @param Translate $langs Language handler
 * @param string    $logoUrl Logo URL
 * @param string    $companyName Company name
 * @return void
 */
function procedurespvPublicPrintBrand($langs, $logoUrl, $companyName)
{
	if ($logoUrl === '' && $companyName === '') {
		return;
	}

	print '<div class="public-brandbar">';
	if ($logoUrl !== '') {
		$alt = $companyName !== '' ? $companyName : $langs->trans('PublicCompanyLogo');
		print '<img class="public-entity-logo" src="'.dol_escape_htmltag($logoUrl).'" alt="'.dol_escape_htmltag($alt).'">';
	} else {
		print '<span class="public-brand-name">'.dol_escape_htmltag($companyName).'</span>';
	}
	print '</div>';
}

/**
 * Print public legal footer.
 *
 * @param Translate $langs Language handler
 * @param array{name:string, lines:array<int,array{label:string,value:string}>} $legalData Legal company data
 * @return void
 */
function procedurespvPublicPrintLegalFooter($langs, array $legalData)
{
	if ($legalData['name'] === '' && empty($legalData['lines'])) {
		return;
	}

	print '<footer class="public-legal-footer">';
	print '<div class="public-legal-title">'.$langs->trans('PublicLegalMentions').'</div>';
	if ($legalData['name'] !== '') {
		print '<div class="public-legal-company">'.dol_escape_htmltag($legalData['name']).'</div>';
	}
	if (!empty($legalData['lines'])) {
		print '<div class="public-legal-lines">';
		foreach ($legalData['lines'] as $line) {
			print '<span>';
			if ($line['label'] !== '') {
				print '<span class="public-legal-label">'.$langs->trans($line['label']).' : </span>';
			}
			print dol_escape_htmltag($line['value']);
			print '</span>';
		}
		print '</div>';
	}
	print '</footer>';
}

/**
 * Return public upload definitions.
 *
 * @param string $clientType Client type
 * @param string $pdlChoice PDL choice
 * @param string $siteAlreadyConnected Site already connected flag
 * @return array<int,array{code:string,input:string,label:string,help:string,company_only:int,pdl_other_only:int,required:int}>
 */
function procedurespvPublicGetPieceDefinitions($clientType, $pdlChoice = '', $siteAlreadyConnected = '')
{
	$isCompany = $clientType === 'societe';
	$isOtherPdl = $isCompany && $siteAlreadyConnected === 'yes' && $pdlChoice === 'existing_other_legal_entity';

	return array(
		array(
			'code' => 'facture_electricite',
			'input' => 'piece_facture_electricite',
			'label' => 'PieceFactureElectricite',
			'help' => 'PublicElectricityBillHelp',
			'company_only' => 0,
			'pdl_other_only' => 0,
			'required' => $isCompany ? 1 : 0,
		),
		array(
			'code' => 'kbis_beneficiaire',
			'input' => 'piece_kbis_beneficiaire',
			'label' => 'PieceKbisBeneficiary',
			'help' => 'PieceKbisBeneficiaryHelp',
			'company_only' => 1,
			'pdl_other_only' => 0,
			'required' => $isCompany ? 1 : 0,
		),
		array(
			'code' => 'kbis_etablissement_production',
			'input' => 'piece_kbis_etablissement_production',
			'label' => 'PieceKbisProductionSite',
			'help' => 'PieceKbisProductionSiteHelp',
			'company_only' => 1,
			'pdl_other_only' => 0,
			'required' => $isCompany ? 1 : 0,
		),
		array(
			'code' => 'autorisation_administrative',
			'input' => 'piece_autorisation_administrative',
			'label' => 'PieceAdministrativeAuthorization',
			'help' => 'PieceAdministrativeAuthorizationHelp',
			'company_only' => 1,
			'pdl_other_only' => 0,
			'required' => $isCompany ? 1 : 0,
		),
		array(
			'code' => 'card_pdl_tiers',
			'input' => 'piece_card_pdl_tiers',
			'label' => 'PieceCardPdlOtherLegalEntity',
			'help' => 'PieceCardPdlOtherLegalEntityHelp',
			'company_only' => 1,
			'pdl_other_only' => 1,
			'required' => $isOtherPdl ? 1 : 0,
		),
	);
}

/**
 * Store one uploaded public piece.
 *
 * @param Translate $langs Language handler
 * @param Raccordement $object Raccordement
 * @param array{code:string,input:string,label:string,help:string,company_only:int,pdl_other_only:int,required:int} $definition Piece definition
 * @param array<int,string> $uploadErrors Upload errors
 * @return int Stored piece id, 0 if no upload, -1 on error
 */
function procedurespvPublicStoreUploadedPiece($langs, $object, array $definition, array &$uploadErrors)
{
	global $db;

	$fieldName = $definition['input'];
	if (!isset($_FILES[$fieldName]) || !is_array($_FILES[$fieldName]) || empty($_FILES[$fieldName]['name'])) {
		return 0;
	}

	$uploadedFile = $_FILES[$fieldName];
	$uploadErrorCode = isset($uploadedFile['error']) ? (int) $uploadedFile['error'] : UPLOAD_ERR_NO_FILE;
	$originalName = isset($uploadedFile['name']) ? (string) $uploadedFile['name'] : '';
	$tmpName = isset($uploadedFile['tmp_name']) ? (string) $uploadedFile['tmp_name'] : '';
	$fileSize = isset($uploadedFile['size']) ? (int) $uploadedFile['size'] : 0;
	$extension = strtolower((string) pathinfo($originalName, PATHINFO_EXTENSION));
	$allowedExtensions = array_filter(array_map('trim', explode(',', strtolower(getDolGlobalString('PROCEDURESPV_PUBLIC_UPLOAD_ALLOWED_EXTENSIONS', 'pdf,jpg,jpeg,png')))));
	$maxSize = getDolGlobalInt('PROCEDURESPV_PUBLIC_UPLOAD_MAX_SIZE', 10 * 1024 * 1024);
	$localErrors = array();

	if ($uploadErrorCode !== UPLOAD_ERR_OK) {
		$localErrors[] = $langs->trans('UploadError');
	}
	if ($fileSize <= 0 || $fileSize > $maxSize) {
		$localErrors[] = $langs->trans('UploadInvalidSize');
	}
	if ($extension === '' || !in_array($extension, $allowedExtensions, true)) {
		$localErrors[] = $langs->trans('UploadInvalidExtension');
	}

	$mime = '';
	if ($tmpName !== '' && function_exists('finfo_open')) {
		$finfo = finfo_open(FILEINFO_MIME_TYPE);
		if ($finfo !== false) {
			$detectedMime = finfo_file($finfo, $tmpName);
			$mime = is_string($detectedMime) ? $detectedMime : '';
			finfo_close($finfo);
		}
	}
	if ($mime === '' || stripos($mime, 'php') !== false || stripos($mime, 'executable') !== false) {
		$localErrors[] = $langs->trans('UploadInvalidMime');
	}

	if (!empty($localErrors)) {
		foreach ($localErrors as $localError) {
			$uploadErrors[] = $langs->trans('UploadErrorForPiece', $langs->trans($definition['label']), $localError);
		}
		return -1;
	}

	$uploadDir = procedurespvGetRaccordementUploadDir($object);
	if ($uploadDir === '' || dol_mkdir($uploadDir) < 0) {
		$uploadErrors[] = $langs->trans('UploadDirectoryUnavailable');
		return -1;
	}

	$storedFilename = $definition['code'].'_'.dol_print_date(dol_now(), '%Y%m%d%H%M%S').'_'.dol_sanitizeFileName($originalName);
	$destPath = $uploadDir.'/'.$storedFilename;
	$moveResult = dol_move_uploaded_file($tmpName, $destPath, 1, 0, $uploadErrorCode);
	if ($moveResult <= 0) {
		$uploadErrors[] = $langs->trans('UploadMoveFailed');
		return -1;
	}

	$piece = new Piece($db);
	$uploadedPieceId = $piece->createOrUpdateUploaded($object, $definition['code'], $langs->transnoentitiesnoconv($definition['label']), 'client', $uploadDir, $storedFilename, (int) $definition['required']);
	if ($uploadedPieceId <= 0) {
		$uploadErrors[] = $piece->error;
		return -1;
	}

	return $uploadedPieceId;
}

$publicToken = GETPOST('public_token', 'alphanohtml');
$action = GETPOST('action', 'aZ09');
$submissionDone = false;

if (!isModEnabled('procedurespv')) {
	$publicToken = '';
}

$publicLink = new PublicLink($db);
$linkLoaded = $publicToken !== '' ? $publicLink->fetchByToken($publicToken, PublicLink::TYPE_COLLECTE_RACCORDEMENT) : 0;
$object = new Raccordement($db);
$linkObjectLoaded = false;
if ($linkLoaded > 0) {
	$result = $object->fetch((int) $publicLink->fk_raccordement, null, 0);
	if ($result <= 0 || (int) $object->entity !== (int) $publicLink->entity) {
		$linkLoaded = 0;
	} else {
		$linkObjectLoaded = true;
	}
}
$linkExpired = $linkLoaded > 0 && !empty($publicLink->date_expiration) && (int) $publicLink->date_expiration < dol_now();
$linkUsable = $linkObjectLoaded && !$linkExpired && $publicLink->isUsable();
$submittedLinkAvailable = $linkObjectLoaded && !$linkExpired && (int) $publicLink->status === PublicLink::STATUS_SUBMITTED;
$submissionDone = $submittedLinkAvailable;

if ($action === 'download_mandat') {
	if (!$submittedLinkAvailable) {
		accessforbidden($langs->trans('PublicLinkUnavailable'));
	}

	$signature = new Signature($db);
	$result = $signature->fetchLatestForRaccordementEntity((int) $object->id, (int) $object->entity, Signature::TYPE_MANDAT_ENEDIS);
	$signatureFile = $result > 0 ? $signature->filepath.'/'.$signature->filename : '';
	$expectedDir = procedurespvGetRaccordementUploadDir($object);
	$realSignatureFile = $signatureFile !== '' ? realpath($signatureFile) : false;
	$realExpectedDir = $expectedDir !== '' ? realpath($expectedDir) : false;
	if ($realSignatureFile === false || $realExpectedDir === false || strpos($realSignatureFile, rtrim($realExpectedDir, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR) !== 0) {
		accessforbidden($langs->trans('ErrorFileNotFound'));
	}

	top_httphead('application/pdf');
	header('Content-Disposition: attachment; filename="'.basename($realSignatureFile).'"');
	$fileSize = filesize($realSignatureFile);
	if (is_int($fileSize)) {
		header('Content-Length: '.$fileSize);
	}
	readfile($realSignatureFile);
	$db->close();
	exit;
}

$isSubmitCollecte = $action === 'submit_collecte';
$formClientType = $isSubmitCollecte ? GETPOST('client_type', 'alphanohtml') : 'particulier';
$formClientName = $isSubmitCollecte ? GETPOST('client_name', 'restricthtml') : '';
$formClientSiret = $isSubmitCollecte ? GETPOST('client_siret', 'alphanohtml') : '';
$formClientEmail = $isSubmitCollecte ? GETPOST('client_email', 'restricthtml') : (string) $publicLink->email_destinataire;
$formClientPhone = $isSubmitCollecte ? GETPOST('client_phone', 'alphanohtml') : '';
$formCompanyInseeCode = $isSubmitCollecte ? GETPOST('company_insee_code', 'alphanohtml') : '';
$formCompanyCapital = $isSubmitCollecte ? GETPOST('company_capital', 'alphanohtml') : '';
$formCompanyLegalForm = $isSubmitCollecte ? GETPOST('company_legal_form', 'restricthtml') : '';
$formCompanySize = $isSubmitCollecte ? GETPOST('company_size', 'alphanohtml') : 'pme';
$formCompanyNaceSector = $isSubmitCollecte ? GETPOST('company_nace_sector', 'restricthtml') : '';
$formRepresentativeLastname = $isSubmitCollecte ? GETPOST('representative_lastname', 'restricthtml') : '';
$formRepresentativeFirstname = $isSubmitCollecte ? GETPOST('representative_firstname', 'restricthtml') : '';
$formRepresentativeMobile = $isSubmitCollecte ? GETPOST('representative_mobile', 'alphanohtml') : '';
$formRepresentativeAuthorized = $isSubmitCollecte ? GETPOST('representative_authorized', 'alphanohtml') : 'yes';
$formHeadquartersAddress = $isSubmitCollecte ? GETPOST('headquarters_address', 'restricthtml') : '';
$formHeadquartersZip = $isSubmitCollecte ? GETPOST('headquarters_zip', 'alphanohtml') : '';
$formHeadquartersTown = $isSubmitCollecte ? GETPOST('headquarters_town', 'restricthtml') : '';
$formProducerIsBuildingOwner = $isSubmitCollecte ? GETPOST('producer_is_building_owner', 'alphanohtml') : 'yes';
$formBuildingOwnerName = $isSubmitCollecte ? GETPOST('building_owner_name', 'restricthtml') : '';
$formBuildingAlreadyBuilt = $isSubmitCollecte ? GETPOST('building_already_built', 'alphanohtml') : 'yes';
$formSiteName = $isSubmitCollecte ? GETPOST('site_name', 'restricthtml') : (string) $object->site_name_snapshot;
$formProductionSiteSiret = $isSubmitCollecte ? GETPOST('production_site_siret', 'alphanohtml') : '';
$formSiteAddress = $isSubmitCollecte ? GETPOST('site_address', 'restricthtml') : (string) $object->site_address_snapshot;
$formSiteZip = $isSubmitCollecte ? GETPOST('site_zip', 'alphanohtml') : (string) $object->site_zip_snapshot;
$formSiteTown = $isSubmitCollecte ? GETPOST('site_town', 'restricthtml') : (string) $object->site_town_snapshot;
$formPrm = $isSubmitCollecte ? GETPOST('prm', 'alphanohtml') : (string) $object->prm;
$formTypeReseau = $isSubmitCollecte ? GETPOST('type_reseau', 'alphanohtml') : (string) $object->type_reseau;
$formSiteAlreadyConnected = $isSubmitCollecte ? GETPOST('site_already_connected', 'alphanohtml') : 'yes';
$formExistingConnectionType = $isSubmitCollecte ? GETPOST('existing_connection_type', 'alphanohtml') : 'bt_soutirage';
$formPdlChoice = $isSubmitCollecte ? GETPOST('pdl_choice', 'alphanohtml') : 'new_pdl';
$formPuissanceSouscrite = $isSubmitCollecte ? GETPOST('puissance_souscrite', 'alphanohtml') : (string) $object->puissance_souscrite;
$formPdlContractHolder = $isSubmitCollecte ? GETPOST('pdl_contract_holder', 'restricthtml') : '';
$formTypeExploitation = $isSubmitCollecte ? GETPOST('type_exploitation', 'alphanohtml') : (string) $object->type_exploitation;
$formPuissanceInstallee = $isSubmitCollecte ? GETPOST('puissance_installee_kwc', 'alphanohtml') : (string) $object->puissance_installee_kwc;
$formPuissanceInjection = $isSubmitCollecte ? GETPOST('puissance_injection_kva', 'alphanohtml') : (string) $object->puissance_injection_kva;
$formNoRelatedProjectAttestation = $isSubmitCollecte ? GETPOST('no_related_project_attestation', 'alphanohtml') : 'yes';
$formRelatedProjectReferences = $isSubmitCollecte ? GETPOST('related_project_references', 'restricthtml') : '';
$formEnedisRequestType = $isSubmitCollecte ? GETPOST('enedis_request_type', 'alphanohtml') : 'enedis_full';
$formSignataireNom = $isSubmitCollecte ? GETPOST('signataire_nom', 'restricthtml') : '';
$formSignataireFonction = $isSubmitCollecte ? GETPOST('signataire_fonction', 'restricthtml') : '';
$formSignataireEmail = $isSubmitCollecte ? GETPOST('signataire_email', 'restricthtml') : (string) $publicLink->email_destinataire;
$formMandatAcceptance = $isSubmitCollecte ? GETPOST('mandat_acceptance', 'alphanohtml') : 'no';

if ($linkUsable && $action !== 'submit_collecte') {
	$ip = function_exists('getUserRemoteIP') ? getUserRemoteIP() : (isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : '');
	$userAgent = isset($_SERVER['HTTP_USER_AGENT']) ? (string) $_SERVER['HTTP_USER_AGENT'] : '';
	$publicLink->logAccess($ip, $userAgent);
	if (empty($object->date_collecte_ouverture)) {
		$object->date_collecte_ouverture = dol_now();
		$object->context['trigger_reason'] = 'public_collecte_opened';
		$object->context['changed_fields'] = array('date_collecte_ouverture');
		$object->update($user);
	}
}

if ($linkUsable && $action === 'submit_collecte') {
	if (!GETPOST('token', 'alpha') || (function_exists('checkToken') && !checkToken())) {
		accessforbidden($langs->trans('ErrorBadToken'));
	}

	$clientType = $formClientType;
	$clientName = $formClientName;
	$clientSiret = preg_replace('/\D+/', '', $formClientSiret);
	$clientSiret = is_string($clientSiret) ? $clientSiret : '';
	$clientEmail = $formClientEmail;
	$clientPhone = $formClientPhone;
	$companyInseeCode = $formCompanyInseeCode;
	$companyCapital = $formCompanyCapital;
	$companyLegalForm = $formCompanyLegalForm;
	$companySize = $formCompanySize;
	$companyNaceSector = $formCompanyNaceSector;
	$representativeLastname = $formRepresentativeLastname;
	$representativeFirstname = $formRepresentativeFirstname;
	$representativeMobile = $formRepresentativeMobile;
	$representativeAuthorized = $formRepresentativeAuthorized;
	$headquartersAddress = $formHeadquartersAddress;
	$headquartersZip = $formHeadquartersZip;
	$headquartersTown = $formHeadquartersTown;
	$producerIsBuildingOwner = $formProducerIsBuildingOwner;
	$buildingOwnerName = $formBuildingOwnerName;
	$buildingAlreadyBuilt = $formBuildingAlreadyBuilt;
	$siteName = $formSiteName;
	$productionSiteSiret = preg_replace('/\D+/', '', $formProductionSiteSiret);
	$productionSiteSiret = is_string($productionSiteSiret) ? $productionSiteSiret : '';
	$siteAddress = $formSiteAddress;
	$siteZip = $formSiteZip;
	$siteTown = $formSiteTown;
	$prm = $formPrm;
	$typeReseau = $formTypeReseau;
	$siteAlreadyConnected = $formSiteAlreadyConnected;
	$existingConnectionType = $formExistingConnectionType;
	$pdlChoice = $formPdlChoice;
	$puissanceSouscrite = $formPuissanceSouscrite;
	$pdlContractHolder = $formPdlContractHolder;
	$typeExploitation = $formTypeExploitation;
	$puissanceInstallee = (float) price2num($formPuissanceInstallee);
	$puissanceInjection = (float) price2num($formPuissanceInjection);
	$noRelatedProjectAttestation = $formNoRelatedProjectAttestation;
	$relatedProjectReferences = $formRelatedProjectReferences;
	$enedisRequestType = $formEnedisRequestType;
	$signataireNom = $formSignataireNom;
	$signataireFonction = $formSignataireFonction;
	$signataireEmail = $formSignataireEmail;
	$mandatAcceptance = $formMandatAcceptance;
	$signatureDataUrl = GETPOST('signature_data_url', 'restricthtml');
	$uploadErrors = array();
	$uploadedPieceIds = array();
	$signatureId = 0;

	if ($clientType === 'societe' && $signataireNom === '' && trim($representativeFirstname.' '.$representativeLastname) !== '') {
		$signataireNom = trim($representativeFirstname.' '.$representativeLastname);
	}

	foreach (procedurespvPublicGetPieceDefinitions($clientType, $pdlChoice, $siteAlreadyConnected) as $pieceDefinition) {
		if ((int) $pieceDefinition['company_only'] && $clientType !== 'societe') {
			continue;
		}
		if ((int) $pieceDefinition['pdl_other_only'] && ($siteAlreadyConnected !== 'yes' || $pdlChoice !== 'existing_other_legal_entity')) {
			continue;
		}
		$uploadedPieceId = procedurespvPublicStoreUploadedPiece($langs, $object, $pieceDefinition, $uploadErrors);
		if ($uploadedPieceId > 0) {
			$uploadedPieceIds[$pieceDefinition['code']] = $uploadedPieceId;
		}
		if ((int) $pieceDefinition['required'] && $uploadedPieceId === 0) {
			$uploadErrors[] = $langs->trans('RequiredDocumentMissing', $langs->trans($pieceDefinition['label']));
		}
	}

	$publicSummary = array(
		'client_type' => $clientType,
		'client_name' => $clientName,
		'client_siret' => $clientSiret,
		'client_email' => $clientEmail,
		'client_phone' => $clientPhone,
		'company' => array(
			'insee_code' => $companyInseeCode,
			'capital' => $companyCapital,
			'legal_form' => $companyLegalForm,
			'size' => $companySize,
			'nace_sector' => $companyNaceSector,
		),
		'representative' => array(
			'lastname' => $representativeLastname,
			'firstname' => $representativeFirstname,
			'mobile' => $representativeMobile,
			'authorized' => $representativeAuthorized,
			'headquarters_address' => $headquartersAddress,
			'headquarters_zip' => $headquartersZip,
			'headquarters_town' => $headquartersTown,
			'producer_is_building_owner' => $producerIsBuildingOwner,
			'building_owner_name' => $buildingOwnerName,
			'building_already_built' => $buildingAlreadyBuilt,
		),
		'production_site' => array(
			'siret' => $productionSiteSiret,
			'already_connected' => $siteAlreadyConnected,
			'existing_connection_type' => $existingConnectionType,
			'pdl_choice' => $pdlChoice,
			'subscribed_power' => $puissanceSouscrite,
			'pdl_contract_holder' => $pdlContractHolder,
		),
		'production' => array(
			'no_related_project_attestation' => $noRelatedProjectAttestation,
			'related_project_references' => $relatedProjectReferences,
			'enedis_request_type' => $enedisRequestType,
		),
		'uploaded_piece_ids' => $uploadedPieceIds,
	);

	$object->site_name_snapshot = $siteName;
	$object->site_address_snapshot = $siteAddress;
	$object->site_zip_snapshot = $siteZip;
	$object->site_town_snapshot = $siteTown;
	$object->prm = $prm;
	$object->type_reseau = $typeReseau;
	$object->puissance_souscrite = $puissanceSouscrite;
	$object->type_exploitation = $typeExploitation;
	$object->puissance_installee_kwc = $puissanceInstallee;
	$object->puissance_injection_kva = $puissanceInjection;
	$object->date_collecte_soumission = dol_now();
	$object->date_mandat_signature = dol_now();
	$object->status = 4;
	$object->context['trigger_reason'] = 'public_collecte_submitted';
	$object->context['changed_fields'] = array('status', 'date_collecte_soumission', 'date_mandat_signature', 'site_name_snapshot', 'site_address_snapshot', 'site_zip_snapshot', 'site_town_snapshot', 'prm', 'type_reseau', 'puissance_souscrite', 'type_exploitation', 'puissance_installee_kwc', 'puissance_injection_kva');
	if ($clientType === 'societe' && $clientSiret === '') {
		$uploadErrors[] = $langs->trans('BeneficiarySiretRequired');
	}
	if ($clientType === 'societe') {
		$requiredCompanyFields = array(
			'client_name' => array($clientName, 'NameOrCompany'),
			'company_insee_code' => array($companyInseeCode, 'CompanyInseeCode'),
			'company_capital' => array($companyCapital, 'CompanyCapital'),
			'company_legal_form' => array($companyLegalForm, 'CompanyLegalForm'),
			'company_nace_sector' => array($companyNaceSector, 'CompanyNaceSector'),
			'representative_lastname' => array($representativeLastname, 'RepresentativeLastname'),
			'representative_firstname' => array($representativeFirstname, 'RepresentativeFirstname'),
			'representative_authorized' => array($representativeAuthorized, 'RepresentativeAuthorized'),
			'signataire_fonction' => array($signataireFonction, 'SignerFunction'),
			'signataire_email' => array($signataireEmail, 'SignerEmail'),
			'client_phone' => array($clientPhone, 'Phone'),
			'headquarters_address' => array($headquartersAddress, 'HeadquartersAddress'),
			'headquarters_zip' => array($headquartersZip, 'HeadquartersZip'),
			'headquarters_town' => array($headquartersTown, 'HeadquartersTown'),
			'producer_is_building_owner' => array($producerIsBuildingOwner, 'ProducerIsBuildingOwner'),
			'building_already_built' => array($buildingAlreadyBuilt, 'BuildingAlreadyBuilt'),
			'site_name' => array($siteName, 'SiteName'),
			'production_site_siret' => array($productionSiteSiret, 'ProductionSiteSiret'),
			'site_address' => array($siteAddress, 'Address'),
			'site_zip' => array($siteZip, 'Zip'),
			'site_town' => array($siteTown, 'Town'),
			'site_already_connected' => array($siteAlreadyConnected, 'SiteAlreadyConnected'),
			'type_exploitation' => array($typeExploitation, 'ExploitationType'),
			'no_related_project_attestation' => array($noRelatedProjectAttestation, 'NoRelatedProjectAttestation'),
			'enedis_request_type' => array($enedisRequestType, 'EnedisRequestType'),
		);
		if ($producerIsBuildingOwner === 'no') {
			$requiredCompanyFields['building_owner_name'] = array($buildingOwnerName, 'BuildingOwnerName');
		}
		if ($siteAlreadyConnected === 'yes') {
			$requiredCompanyFields['existing_connection_type'] = array($existingConnectionType, 'ExistingConnectionType');
			$requiredCompanyFields['pdl_choice'] = array($pdlChoice, 'PdlChoice');
		}
		if ($siteAlreadyConnected === 'yes' && $pdlChoice === 'existing_same_legal_entity') {
			$requiredCompanyFields['puissance_souscrite'] = array($puissanceSouscrite, 'SubscribedPower');
			$requiredCompanyFields['prm'] = array($prm, 'PRM');
			$requiredCompanyFields['pdl_contract_holder'] = array($pdlContractHolder, 'PdlContractHolder');
		}
		if ($noRelatedProjectAttestation === 'no') {
			$requiredCompanyFields['related_project_references'] = array($relatedProjectReferences, 'RelatedProjectReferences');
		}
		foreach ($requiredCompanyFields as $requiredCompanyField) {
			if (trim((string) $requiredCompanyField[0]) === '') {
				$uploadErrors[] = $langs->trans('PublicRequiredFieldMissing', $langs->trans($requiredCompanyField[1]));
			}
		}
		if ($representativeAuthorized !== 'yes') {
			$uploadErrors[] = $langs->trans('RepresentativeAuthorizationRequired');
		}
		if ($productionSiteSiret !== '' && !preg_match('/^\d{14}$/', $productionSiteSiret)) {
			$uploadErrors[] = $langs->trans('ProductionSiteSiretInvalid');
		}
	}
	if ($clientSiret !== '' && !preg_match('/^\d{14}$/', $clientSiret)) {
		$uploadErrors[] = $langs->trans('BeneficiarySiretInvalid');
	}
	if ($mandatAcceptance !== 'yes') {
		$uploadErrors[] = $langs->trans('MandatAcceptanceRequired');
	}
	if ($signataireNom === '' || $signataireEmail === '') {
		$uploadErrors[] = $langs->trans('MandatSignerRequired');
	}
	if (strpos($signatureDataUrl, 'data:image/png;base64,') !== 0 || strlen($signatureDataUrl) > 1200000) {
		$uploadErrors[] = $langs->trans('MandatSignatureRequired');
	}

	if (empty($uploadErrors)) {
		$uploadDir = procedurespvGetRaccordementUploadDir($object);
		$pdfModel = procedurespvLoadMandatEnedisPdfModel($db);
		$ip = function_exists('getUserRemoteIP') ? getUserRemoteIP() : (isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : '');
		$userAgent = isset($_SERVER['HTTP_USER_AGENT']) ? (string) $_SERVER['HTTP_USER_AGENT'] : '';
		if (!is_object($pdfModel) || !method_exists($pdfModel, 'write_file')) {
			$uploadErrors[] = $langs->trans('ErrorPdfNotGenerated');
		} else {
			$pdfFilename = $pdfModel->write_file($object, $langs, $uploadDir, array(
				'client_type' => $clientType,
				'client_name' => $clientName,
				'client_siret' => $clientSiret,
				'client_email' => $clientEmail,
				'client_phone' => $clientPhone,
				'signataire_nom' => $signataireNom,
				'signataire_fonction' => $signataireFonction,
				'signataire_email' => $signataireEmail,
				'signature_data_url' => $signatureDataUrl,
				'signature_ip' => $ip,
				'signature_user_agent' => $userAgent,
			));
			if ($pdfFilename === '') {
				$pdfModelProperties = get_object_vars($pdfModel);
				$errorKey = (!empty($pdfModelProperties['error']) && is_scalar($pdfModelProperties['error'])) ? (string) $pdfModelProperties['error'] : 'ErrorPdfNotGenerated';
				$uploadErrors[] = $langs->trans($errorKey);
			} else {
				$pdfHash = hash_file('sha256', $uploadDir.'/'.$pdfFilename);
				$signature = new Signature($db);
				$signatureId = $signature->createSignedMandate($object, array(
					'signataire_nom' => $signataireNom,
					'signataire_fonction' => $signataireFonction,
					'signataire_email' => $signataireEmail,
					'signature_ip' => $ip,
					'signature_user_agent' => $userAgent,
					'filepath' => $uploadDir,
					'filename' => $pdfFilename,
					'pdf_hash' => is_string($pdfHash) ? $pdfHash : '',
				));
				if ($signatureId <= 0) {
					$uploadErrors[] = $signature->error;
				}
			}
		}
	}

	if (empty($uploadErrors)) {
		$publicSummary['signature_id'] = $signatureId;
		$publicSummaryJson = json_encode($publicSummary, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		$object->note_public = trim((string) $object->note_public."\n\n".$langs->trans('PublicCollecteSummary')."\n".(is_string($publicSummaryJson) ? $publicSummaryJson : ''));
		$result = $object->update($user);
		if ($result > 0) {
			$publicLink->markSubmitted();
			$linkUsable = false;
			$submissionDone = true;
			$submittedLinkAvailable = true;
			setEventMessages($langs->trans('PublicCollecteSubmitted'), null, 'mesgs');
		} else {
			setEventMessages($object->error, $object->errors, 'errors');
		}
	} else {
		setEventMessages('', $uploadErrors, 'errors');
	}
}

$companyEntity = (int) $conf->entity;
if (!empty($object->entity)) {
	$companyEntity = (int) $object->entity;
} elseif (!empty($publicLink->entity)) {
	$companyEntity = (int) $publicLink->entity;
}
$companyLogoUrl = procedurespvPublicGetCompanyLogoUrl($companyEntity);
$companyLegalData = procedurespvPublicGetCompanyLegalData($companyEntity);

llxHeader('', $langs->trans('PublicCollecteTitle'), '', '', 0, 0, '', '', '', 'mod-procedurespv page-public-collecte');

print <<<'HTML'
<style>
body.page-public-collecte #id-container,
body.page-public-collecte #id-right {
	display: block;
	width: 100%;
}
body.page-public-collecte #id-right {
	padding-top: 0;
	padding-bottom: 0;
}
body.page-public-collecte div.fiche {
	margin-left: 0 !important;
	margin-right: 0 !important;
}
.public-procedurespv {
	--public-steps-height: 60px;
	box-sizing: border-box;
	display: flex;
	justify-content: center;
	width: 100%;
	max-width: none;
	min-height: calc(100vh - 70px);
	margin: 0;
	padding: clamp(18px, 4vw, 46px);
	background: linear-gradient(180deg, #f7fbfb 0%, #eef6f2 100%);
	color: #1f2933;
}
.public-procedurespv * {
	box-sizing: border-box;
}
.public-shell {
	flex: 0 1 1120px;
	width: min(1120px, 100%);
	margin: 0 auto;
}
.public-brandbar {
	display: flex;
	justify-content: center;
	align-items: center;
	margin-bottom: 20px;
}
.public-entity-logo {
	display: block;
	max-width: min(260px, 80vw);
	max-height: 78px;
	object-fit: contain;
}
.public-brand-name {
	font-size: 1.05rem;
	font-weight: 800;
	color: #172033;
}
.public-hero {
	display: grid;
	grid-template-columns: minmax(0, 1fr) minmax(220px, 300px);
	gap: clamp(16px, 3vw, 34px);
	align-items: end;
	margin-bottom: 24px;
}
.public-eyebrow {
	margin-bottom: 8px;
	font-size: 0.78rem;
	font-weight: 700;
	text-transform: uppercase;
	letter-spacing: 0;
	color: #0f766e;
}
.public-hero h1 {
	margin: 0;
	font-size: clamp(1.75rem, 3.2vw, 2.65rem);
	line-height: 1.08;
	font-weight: 800;
	color: #172033;
}
.public-hero p {
	max-width: 760px;
	margin: 14px 0 0;
	font-size: 1.02rem;
	line-height: 1.55;
	color: #526071;
}
.public-hero-aside {
	border-left: 4px solid #0f766e;
	padding: 12px 0 12px 16px;
	color: #526071;
	font-weight: 600;
}
.public-steps {
	display: flex;
	flex-wrap: wrap;
	gap: 8px;
	position: sticky;
	top: 0;
	z-index: 10;
	margin: 0 0 22px;
	padding: 10px 0;
	list-style: none;
	background: #eef6f2;
	border-bottom: 1px solid transparent;
	transition: border-color 0.18s ease, box-shadow 0.18s ease;
}
.public-steps.is-stuck {
	border-bottom-color: #d7e3df;
	box-shadow: 0 10px 24px rgba(16, 24, 40, 0.08);
}
.public-steps li {
	margin: 0;
	padding: 0;
}
.public-steps a {
	display: block;
	border: 1px solid #d7e3df;
	border-radius: 999px;
	background: #fff;
	padding: 7px 12px;
	font-size: 0.88rem;
	font-weight: 700;
	color: #344054;
	text-decoration: none !important;
}
.public-steps a:hover,
.public-steps a:focus {
	border-color: #0f766e;
	color: #0f766e;
	box-shadow: 0 8px 18px rgba(16, 24, 40, 0.08);
	outline: none;
}
.public-steps a.is-active {
	border-color: #0f766e;
	background: #0f766e;
	color: #fff;
	box-shadow: 0 8px 18px rgba(15, 118, 110, 0.22);
}
.public-steps a.is-active:hover,
.public-steps a.is-active:focus {
	color: #fff;
}
.public-section {
	scroll-margin-top: calc(var(--public-steps-height, 60px) + 18px);
	margin: 16px 0;
	padding: clamp(16px, 2.2vw, 24px);
	border: 1px solid #dce7e3;
	border-radius: 8px;
	background: #fff;
	box-shadow: 0 12px 28px rgba(16, 24, 40, 0.07);
}
.public-section-header {
	display: flex;
	gap: 12px;
	align-items: center;
	margin-bottom: 14px;
}
.public-step {
	display: inline-flex;
	width: 34px;
	height: 34px;
	align-items: center;
	justify-content: center;
	border-radius: 999px;
	background: #0f766e;
	color: #fff;
	font-weight: 800;
}
.public-section h2 {
	margin: 0;
	font-size: 1.16rem;
	line-height: 1.3;
	color: #172033;
}
.public-subtitle {
	margin: 12px 0 4px;
	font-size: 0.92rem;
	font-weight: 800;
	color: #172033;
}
.public-form-table {
	width: 100%;
	border-collapse: separate;
	border-spacing: 0 10px;
}
.public-form-table td {
	border: 0;
	padding: 0;
	vertical-align: top;
}
.public-form-table td:first-child {
	width: 31%;
	padding-top: 9px;
	padding-right: 18px;
	font-weight: 700;
	color: #46525f;
}
.public-form-table input.flat,
.public-form-table select.flat {
	width: min(100%, 560px);
	min-height: 38px;
	border: 1px solid #cbd8d4;
	border-radius: 6px;
	background: #fff;
	padding: 8px 10px;
	box-shadow: inset 0 1px 2px rgba(16, 24, 40, 0.04);
}
.public-form-table input[type="file"].flat {
	padding: 7px;
}
.public-form-table tr[hidden] {
	display: none !important;
}
.public-form-table .select2-container {
	width: min(100%, 560px) !important;
}
.public-help {
	display: block;
	max-width: 620px;
	margin-top: 5px;
	color: #667085;
	font-size: 0.84rem;
	line-height: 1.4;
}
.public-unit-field {
	display: inline-flex;
	align-items: center;
	gap: 8px;
	width: min(100%, 560px);
}
.public-unit-field input.flat {
	width: min(160px, 100%);
}
.public-signature-pad {
	width: min(100%, 620px);
	height: 190px;
	border: 1px solid #98a8a3;
	border-radius: 8px;
	background: #fff;
	touch-action: none;
	box-shadow: inset 0 1px 3px rgba(16, 24, 40, 0.08);
}
.public-actions {
	position: sticky;
	bottom: 0;
	z-index: 2;
	margin-top: 18px;
	padding: 14px 0 0;
	text-align: right;
	background: linear-gradient(180deg, rgba(238, 246, 242, 0), #eef6f2 38%);
}
.public-actions .button-save {
	min-width: 220px;
	padding: 10px 18px;
	font-weight: 800;
}
.public-legal-footer {
	margin-top: 26px;
	padding-top: 18px;
	border-top: 1px solid #d7e3df;
	color: #526071;
	font-size: 0.82rem;
	line-height: 1.5;
	text-align: center;
}
.public-legal-title {
	margin-bottom: 4px;
	font-size: 0.74rem;
	font-weight: 800;
	text-transform: uppercase;
	letter-spacing: 0;
	color: #0f766e;
}
.public-legal-company {
	font-weight: 700;
	color: #344054;
}
.public-legal-lines {
	display: flex;
	flex-wrap: wrap;
	justify-content: center;
	gap: 4px 12px;
	margin-top: 5px;
}
.public-legal-label {
	font-weight: 700;
}
@media (max-width: 760px) {
	.public-procedurespv {
		padding: 16px;
	}
	.public-hero {
		grid-template-columns: 1fr;
	}
	.public-hero-aside {
		border-left: 0;
		border-top: 4px solid #0f766e;
		padding: 12px 0 0;
	}
	.public-form-table,
	.public-form-table tbody,
	.public-form-table tr,
	.public-form-table td {
		display: block;
		width: 100%;
	}
	.public-form-table {
		border-spacing: 0;
	}
	.public-form-table tr {
		margin-bottom: 13px;
	}
	.public-form-table td:first-child {
		width: 100%;
		padding: 0 0 5px;
	}
	.public-form-table input.flat,
	.public-form-table select.flat,
	.public-unit-field {
		width: 100%;
	}
	.public-steps {
		flex-wrap: nowrap;
		overflow-x: auto;
		padding-bottom: 12px;
	}
	.public-steps li {
		flex: 0 0 auto;
	}
	.public-actions {
		text-align: center;
	}
	.public-actions .button-save {
		width: 100%;
	}
	.public-legal-footer {
		text-align: left;
	}
	.public-legal-lines {
		display: block;
	}
	.public-legal-lines span {
		display: block;
	}
}
</style>
HTML;

print '<main class="public-procedurespv">';
print '<div class="public-shell">';
procedurespvPublicPrintBrand($langs, $companyLogoUrl, $companyLegalData['name']);
print '<header class="public-hero">';
print '<div>';
print '<div class="public-eyebrow">'.$langs->trans('PublicCollecteEyebrow').'</div>';
print '<h1>'.$langs->trans('PublicCollecteTitle').'</h1>';
print '<p>'.$langs->trans('PublicCollecteIntro').'</p>';
print '</div>';
print '<div class="public-hero-aside">'.$langs->trans('PublicCollecteAside').'</div>';
print '</header>';

if ($submissionDone) {
	print '<div class="ok">'.$langs->trans('PublicCollecteSubmitted').'</div>';
	if ($submittedLinkAvailable) {
		$downloadUrl = $_SERVER['PHP_SELF'].'?public_token='.urlencode($publicToken).'&action=download_mandat';
		print '<p class="center"><a class="button" href="'.dol_escape_htmltag($downloadUrl).'">'.$langs->trans('DownloadSignedMandatEnedis').'</a></p>';
	}
	procedurespvPublicPrintLegalFooter($langs, $companyLegalData);
	print '</div>';
	print '</main>';
	llxFooter('', 'public');
	$db->close();
	exit;
}

if (!$linkUsable) {
	print '<div class="warning">'.$langs->trans('PublicLinkUnavailable').'</div>';
	procedurespvPublicPrintLegalFooter($langs, $companyLegalData);
	print '</div>';
	print '</main>';
	llxFooter('', 'public');
	$db->close();
	exit;
}

print '<ul class="public-steps" aria-label="'.$langs->trans('PublicCollecteTitle').'">';
print '<li><a class="is-active" aria-current="step" href="#public-section-client">01 '.$langs->trans('PublicSectionClient').'</a></li>';
print '<li><a href="#public-section-site">02 '.$langs->trans('PublicSectionSite').'</a></li>';
print '<li><a href="#public-section-project">03 '.$langs->trans('PublicSectionProject').'</a></li>';
print '<li><a href="#public-section-pieces">04 '.$langs->trans('PublicSectionPieces').'</a></li>';
print '<li><a href="#public-section-mandat">05 '.$langs->trans('PublicSectionMandat').'</a></li>';
print '</ul>';

print '<form class="public-collecte-form" method="POST" enctype="multipart/form-data" action="'.dol_escape_htmltag($_SERVER['PHP_SELF']).'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="public_token" value="'.dol_escape_htmltag($publicToken).'">';
print '<input type="hidden" name="action" value="submit_collecte">';

print '<section class="public-section" id="public-section-client">';
print '<div class="public-section-header"><span class="public-step">1</span><h2>'.$langs->trans('PublicSectionClient').'</h2></div>';
print '<table class="public-form-table">';
print '<tr><td class="titlefield">'.$langs->trans('ClientType').'</td><td><select class="flat minwidth200" name="client_type" id="client_type">';
foreach (array('particulier' => 'ClientTypeIndividual', 'societe' => 'ClientTypeCompany', 'collectivite' => 'ClientTypePublicEntity', 'association' => 'ClientTypeAssociation') as $value => $labelKey) {
	print '<option value="'.dol_escape_htmltag($value).'"'.($formClientType === $value ? ' selected' : '').'>'.$langs->trans($labelKey).'</option>';
}
print '</select>'.ajax_combobox('client_type').'</td></tr>';
print '<tr><td>'.$langs->trans('NameOrCompany').'</td><td><input type="text" class="flat minwidth300" name="client_name" autocomplete="organization" data-company-required="1" value="'.dol_escape_htmltag($formClientName).'"></td></tr>';
print '<tr class="public-company-row" id="client-siret-row"'.($formClientType === 'societe' ? '' : ' hidden').'><td>'.$langs->trans('BeneficiarySiret').'</td><td><input type="text" class="flat minwidth200" name="client_siret" id="client_siret" inputmode="numeric" maxlength="14" pattern="[0-9]{14}" data-company-required="1"'.($formClientType === 'societe' ? ' required' : '').' value="'.dol_escape_htmltag($formClientSiret).'"><span class="public-help">'.$langs->trans('BeneficiarySiretHelp').'</span></td></tr>';
print '<tr><td>'.$langs->trans('Email').'</td><td><input type="email" class="flat minwidth300" name="client_email" autocomplete="email" value="'.dol_escape_htmltag($formClientEmail).'"></td></tr>';
print '<tr><td>'.$langs->trans('Phone').'</td><td><input type="text" class="flat minwidth200" name="client_phone" autocomplete="tel" data-company-required="1" value="'.dol_escape_htmltag($formClientPhone).'"></td></tr>';
print '<tr class="public-company-row"'.($formClientType === 'societe' ? '' : ' hidden').'><td colspan="2"><div class="public-subtitle">'.$langs->trans('BeneficiaryCompanyDetails').'</div></td></tr>';
print '<tr class="public-company-row"'.($formClientType === 'societe' ? '' : ' hidden').'><td>'.$langs->trans('CompanyInseeCode').'</td><td><input type="text" class="flat minwidth200" name="company_insee_code" data-company-required="1" value="'.dol_escape_htmltag($formCompanyInseeCode).'"></td></tr>';
print '<tr class="public-company-row"'.($formClientType === 'societe' ? '' : ' hidden').'><td>'.$langs->trans('CompanyCapital').'</td><td><span class="public-unit-field"><input type="text" class="flat width150 right" name="company_capital" data-company-required="1" value="'.dol_escape_htmltag($formCompanyCapital).'"><span class="opacitymedium">EUR</span></span></td></tr>';
print '<tr class="public-company-row"'.($formClientType === 'societe' ? '' : ' hidden').'><td>'.$langs->trans('CompanyLegalForm').'</td><td><input type="text" class="flat minwidth300" name="company_legal_form" data-company-required="1" value="'.dol_escape_htmltag($formCompanyLegalForm).'"></td></tr>';
print '<tr class="public-company-row"'.($formClientType === 'societe' ? '' : ' hidden').'><td>'.$langs->trans('CompanySize').'</td><td><select class="flat minwidth300" name="company_size" id="company_size" data-company-required="1">';
foreach (array('pme' => 'CompanySizePME', 'eti' => 'CompanySizeETI', 'ge' => 'CompanySizeGE') as $value => $labelKey) {
	print '<option value="'.dol_escape_htmltag($value).'"'.($formCompanySize === $value ? ' selected' : '').'>'.$langs->trans($labelKey).'</option>';
}
print '</select>'.ajax_combobox('company_size').'</td></tr>';
print '<tr class="public-company-row"'.($formClientType === 'societe' ? '' : ' hidden').'><td>'.$langs->trans('CompanyNaceSector').'</td><td><input type="text" class="flat minwidth500" name="company_nace_sector" data-company-required="1" value="'.dol_escape_htmltag($formCompanyNaceSector).'"></td></tr>';
print '<tr class="public-company-row"'.($formClientType === 'societe' ? '' : ' hidden').'><td colspan="2"><div class="public-subtitle">'.$langs->trans('CompanyRepresentativeDetails').'</div></td></tr>';
print '<tr class="public-company-row"'.($formClientType === 'societe' ? '' : ' hidden').'><td>'.$langs->trans('RepresentativeLastname').'</td><td><input type="text" class="flat minwidth300" name="representative_lastname" id="representative_lastname" autocomplete="family-name" data-company-required="1" value="'.dol_escape_htmltag($formRepresentativeLastname).'"></td></tr>';
print '<tr class="public-company-row"'.($formClientType === 'societe' ? '' : ' hidden').'><td>'.$langs->trans('RepresentativeFirstname').'</td><td><input type="text" class="flat minwidth300" name="representative_firstname" id="representative_firstname" autocomplete="given-name" data-company-required="1" value="'.dol_escape_htmltag($formRepresentativeFirstname).'"></td></tr>';
print '<tr class="public-company-row"'.($formClientType === 'societe' ? '' : ' hidden').'><td>'.$langs->trans('RepresentativeMobile').'</td><td><input type="text" class="flat minwidth200" name="representative_mobile" autocomplete="tel" value="'.dol_escape_htmltag($formRepresentativeMobile).'"></td></tr>';
print '<tr class="public-company-row"'.($formClientType === 'societe' ? '' : ' hidden').'><td>'.$langs->trans('RepresentativeAuthorized').'</td><td><select class="flat minwidth300" name="representative_authorized" id="representative_authorized" data-company-required="1"><option value="yes"'.($formRepresentativeAuthorized === 'yes' ? ' selected' : '').'>'.$langs->trans('Yes').'</option><option value="no"'.($formRepresentativeAuthorized === 'no' ? ' selected' : '').'>'.$langs->trans('No').'</option></select>'.ajax_combobox('representative_authorized').'<span class="public-help">'.$langs->trans('RepresentativeAuthorizedHelp').'</span></td></tr>';
print '<tr class="public-company-row"'.($formClientType === 'societe' ? '' : ' hidden').'><td>'.$langs->trans('HeadquartersAddress').'</td><td><input type="text" class="flat minwidth500" name="headquarters_address" autocomplete="street-address" data-company-required="1" value="'.dol_escape_htmltag($formHeadquartersAddress).'"></td></tr>';
print '<tr class="public-company-row"'.($formClientType === 'societe' ? '' : ' hidden').'><td>'.$langs->trans('HeadquartersZip').'</td><td><input type="text" class="flat maxwidth100" name="headquarters_zip" autocomplete="postal-code" data-company-required="1" value="'.dol_escape_htmltag($formHeadquartersZip).'"></td></tr>';
print '<tr class="public-company-row"'.($formClientType === 'societe' ? '' : ' hidden').'><td>'.$langs->trans('HeadquartersTown').'</td><td><input type="text" class="flat minwidth300" name="headquarters_town" autocomplete="address-level2" data-company-required="1" value="'.dol_escape_htmltag($formHeadquartersTown).'"></td></tr>';
print '<tr class="public-company-row"'.($formClientType === 'societe' ? '' : ' hidden').'><td>'.$langs->trans('ProducerIsBuildingOwner').'</td><td><select class="flat minwidth150" name="producer_is_building_owner" id="producer_is_building_owner" data-company-required="1"><option value="yes"'.($formProducerIsBuildingOwner === 'yes' ? ' selected' : '').'>'.$langs->trans('Yes').'</option><option value="no"'.($formProducerIsBuildingOwner === 'no' ? ' selected' : '').'>'.$langs->trans('No').'</option></select>'.ajax_combobox('producer_is_building_owner').'</td></tr>';
print '<tr class="public-company-row public-building-owner-row"'.($formClientType === 'societe' && $formProducerIsBuildingOwner === 'no' ? '' : ' hidden').'><td>'.$langs->trans('BuildingOwnerName').'</td><td><input type="text" class="flat minwidth300" name="building_owner_name" id="building_owner_name" value="'.dol_escape_htmltag($formBuildingOwnerName).'"></td></tr>';
print '<tr class="public-company-row"'.($formClientType === 'societe' ? '' : ' hidden').'><td>'.$langs->trans('BuildingAlreadyBuilt').'</td><td><select class="flat minwidth150" name="building_already_built" id="building_already_built" data-company-required="1"><option value="yes"'.($formBuildingAlreadyBuilt === 'yes' ? ' selected' : '').'>'.$langs->trans('Yes').'</option><option value="no"'.($formBuildingAlreadyBuilt === 'no' ? ' selected' : '').'>'.$langs->trans('No').'</option></select>'.ajax_combobox('building_already_built').'</td></tr>';
print '</table>';
print '</section>';

print '<section class="public-section" id="public-section-site">';
print '<div class="public-section-header"><span class="public-step">2</span><h2>'.$langs->trans('PublicSectionSite').'</h2></div>';
print '<table class="public-form-table">';
print '<tr><td class="titlefield">'.$langs->trans('SiteName').'</td><td><input type="text" class="flat minwidth300" name="site_name" data-company-required="1" value="'.dol_escape_htmltag($formSiteName).'"></td></tr>';
print '<tr class="public-company-row"'.($formClientType === 'societe' ? '' : ' hidden').'><td>'.$langs->trans('ProductionSiteSiret').'</td><td><input type="text" class="flat minwidth200" name="production_site_siret" id="production_site_siret" inputmode="numeric" maxlength="14" pattern="[0-9]{14}" data-company-required="1" value="'.dol_escape_htmltag($formProductionSiteSiret).'"><span class="public-help">'.$langs->trans('ProductionSiteSiretHelp').'</span></td></tr>';
print '<tr><td>'.$langs->trans('Address').'</td><td><input type="text" class="flat minwidth500" name="site_address" autocomplete="street-address" data-company-required="1" value="'.dol_escape_htmltag($formSiteAddress).'"></td></tr>';
print '<tr><td>'.$langs->trans('Zip').'</td><td><input type="text" class="flat maxwidth100" name="site_zip" autocomplete="postal-code" data-company-required="1" value="'.dol_escape_htmltag($formSiteZip).'"></td></tr>';
print '<tr><td>'.$langs->trans('Town').'</td><td><input type="text" class="flat minwidth300" name="site_town" autocomplete="address-level2" data-company-required="1" value="'.dol_escape_htmltag($formSiteTown).'"></td></tr>';
print '<tr><td>'.$langs->trans('PRM').'</td><td><input type="text" class="flat minwidth200" name="prm" value="'.dol_escape_htmltag($formPrm).'"></td></tr>';
print '<tr><td>'.$langs->trans('NetworkType').'</td><td><select class="flat minwidth200" name="type_reseau" id="type_reseau">';
foreach (array('monophase' => 'NetworkMonophase', 'triphase' => 'NetworkTriphase', 'unknown' => 'Unknown') as $value => $labelKey) {
	print '<option value="'.dol_escape_htmltag($value).'"'.($formTypeReseau === $value ? ' selected' : '').'>'.$langs->trans($labelKey).'</option>';
}
print '</select>'.ajax_combobox('type_reseau').'</td></tr>';
print '<tr class="public-company-row"'.($formClientType === 'societe' ? '' : ' hidden').'><td colspan="2"><div class="public-subtitle">'.$langs->trans('ExistingGridConnectionDetails').'</div></td></tr>';
print '<tr class="public-company-row"'.($formClientType === 'societe' ? '' : ' hidden').'><td>'.$langs->trans('SiteAlreadyConnected').'</td><td><select class="flat minwidth150" name="site_already_connected" id="site_already_connected" data-company-required="1"><option value="yes"'.($formSiteAlreadyConnected === 'yes' ? ' selected' : '').'>'.$langs->trans('Yes').'</option><option value="no"'.($formSiteAlreadyConnected === 'no' ? ' selected' : '').'>'.$langs->trans('No').'</option></select>'.ajax_combobox('site_already_connected').'</td></tr>';
print '<tr class="public-company-row public-existing-connection-row"'.($formClientType === 'societe' && $formSiteAlreadyConnected === 'yes' ? '' : ' hidden').'><td>'.$langs->trans('ExistingConnectionType').'</td><td><select class="flat minwidth300" name="existing_connection_type" id="existing_connection_type" data-company-required="1">';
foreach (array('bt_soutirage' => 'ExistingConnectionBTSoutirage', 'hta_soutirage' => 'ExistingConnectionHTASoutirage', 'bt_injection' => 'ExistingConnectionBTInjection', 'hta_injection' => 'ExistingConnectionHTAInjection') as $value => $labelKey) {
	print '<option value="'.dol_escape_htmltag($value).'"'.($formExistingConnectionType === $value ? ' selected' : '').'>'.$langs->trans($labelKey).'</option>';
}
print '</select>'.ajax_combobox('existing_connection_type').'</td></tr>';
print '<tr class="public-company-row public-existing-connection-row"'.($formClientType === 'societe' && $formSiteAlreadyConnected === 'yes' ? '' : ' hidden').'><td>'.$langs->trans('PdlChoice').'</td><td><select class="flat minwidth500" name="pdl_choice" id="pdl_choice" data-company-required="1">';
foreach (array('new_pdl' => 'PdlChoiceNew', 'existing_same_legal_entity' => 'PdlChoiceExistingSameLegalEntity', 'existing_other_legal_entity' => 'PdlChoiceExistingOtherLegalEntity') as $value => $labelKey) {
	print '<option value="'.dol_escape_htmltag($value).'"'.($formPdlChoice === $value ? ' selected' : '').'>'.$langs->trans($labelKey).'</option>';
}
print '</select>'.ajax_combobox('pdl_choice').'</td></tr>';
print '<tr class="public-company-row public-existing-pdl-detail-row"'.($formClientType === 'societe' && $formSiteAlreadyConnected === 'yes' && $formPdlChoice === 'existing_same_legal_entity' ? '' : ' hidden').'><td>'.$langs->trans('SubscribedPower').'</td><td><span class="public-unit-field"><input type="text" class="flat width150 right" name="puissance_souscrite" data-company-required="1" value="'.dol_escape_htmltag($formPuissanceSouscrite).'"><span class="opacitymedium">kVA</span></span></td></tr>';
print '<tr class="public-company-row public-existing-pdl-detail-row"'.($formClientType === 'societe' && $formSiteAlreadyConnected === 'yes' && $formPdlChoice === 'existing_same_legal_entity' ? '' : ' hidden').'><td>'.$langs->trans('PdlContractHolder').'</td><td><input type="text" class="flat minwidth300" name="pdl_contract_holder" data-company-required="1" value="'.dol_escape_htmltag($formPdlContractHolder).'"></td></tr>';
print '</table>';
print '</section>';

print '<section class="public-section" id="public-section-project">';
print '<div class="public-section-header"><span class="public-step">3</span><h2>'.$langs->trans('PublicSectionProject').'</h2></div>';
print '<table class="public-form-table">';
print '<tr><td class="titlefield">'.$langs->trans('ExploitationType').'</td><td><select class="flat minwidth300" name="type_exploitation" id="type_exploitation" data-company-required="1">';
foreach (array('autoconsommation_totale' => 'ExploitationAutoconsommationTotale', 'autoconsommation_surplus' => 'ExploitationAutoconsommationSurplus', 'injection_totale' => 'ExploitationInjectionTotale', 'autoconsommation_collective' => 'ExploitationAutoconsommationCollective') as $value => $labelKey) {
	print '<option value="'.dol_escape_htmltag($value).'"'.($formTypeExploitation === $value ? ' selected' : '').'>'.$langs->trans($labelKey).'</option>';
}
print '</select>'.ajax_combobox('type_exploitation').'</td></tr>';
print '<tr><td>'.$langs->trans('InstalledPowerKwc').'</td><td><span class="public-unit-field"><input type="text" class="flat width100 right" name="puissance_installee_kwc" value="'.dol_escape_htmltag($formPuissanceInstallee).'"><span class="opacitymedium">kWc</span></span></td></tr>';
print '<tr><td>'.$langs->trans('InjectionPowerKva').'</td><td><span class="public-unit-field"><input type="text" class="flat width100 right" name="puissance_injection_kva" value="'.dol_escape_htmltag($formPuissanceInjection).'"><span class="opacitymedium">kVA</span></span></td></tr>';
print '<tr class="public-company-row"'.($formClientType === 'societe' ? '' : ' hidden').'><td>'.$langs->trans('NoRelatedProjectAttestation').'</td><td><select class="flat minwidth300" name="no_related_project_attestation" id="no_related_project_attestation" data-company-required="1"><option value="yes"'.($formNoRelatedProjectAttestation === 'yes' ? ' selected' : '').'>'.$langs->trans('NoRelatedProjectYes').'</option><option value="no"'.($formNoRelatedProjectAttestation === 'no' ? ' selected' : '').'>'.$langs->trans('NoRelatedProjectNo').'</option></select>'.ajax_combobox('no_related_project_attestation').'<span class="public-help">'.$langs->trans('NoRelatedProjectAttestationHelp').'</span></td></tr>';
print '<tr class="public-company-row public-related-project-row"'.($formClientType === 'societe' && $formNoRelatedProjectAttestation === 'no' ? '' : ' hidden').'><td>'.$langs->trans('RelatedProjectReferences').'</td><td><input type="text" class="flat minwidth500" name="related_project_references" data-company-required="1" value="'.dol_escape_htmltag($formRelatedProjectReferences).'"></td></tr>';
print '<tr class="public-company-row"'.($formClientType === 'societe' ? '' : ' hidden').'><td>'.$langs->trans('EnedisRequestType').'</td><td><select class="flat minwidth500" name="enedis_request_type" id="enedis_request_type" data-company-required="1">';
foreach (array('enedis_full' => 'EnedisRequestTypeFullEnedis', 'l342_2' => 'EnedisRequestTypeL3422', 'prac' => 'EnedisRequestTypePrac') as $value => $labelKey) {
	print '<option value="'.dol_escape_htmltag($value).'"'.($formEnedisRequestType === $value ? ' selected' : '').'>'.$langs->trans($labelKey).'</option>';
}
print '</select>'.ajax_combobox('enedis_request_type').'</td></tr>';
print '</table>';
print '</section>';

print '<section class="public-section" id="public-section-pieces">';
print '<div class="public-section-header"><span class="public-step">4</span><h2>'.$langs->trans('PublicSectionPieces').'</h2></div>';
print '<table class="public-form-table">';
foreach (procedurespvPublicGetPieceDefinitions($formClientType, $formPdlChoice, $formSiteAlreadyConnected) as $pieceDefinition) {
	$rowClasses = array();
	if ((int) $pieceDefinition['company_only']) {
		$rowClasses[] = 'public-company-row';
	}
	if ((int) $pieceDefinition['pdl_other_only']) {
		$rowClasses[] = 'public-pdl-other-piece-row';
	}
	$rowClass = !empty($rowClasses) ? ' class="'.implode(' ', $rowClasses).'"' : '';
	$rowHidden = ((int) $pieceDefinition['company_only'] && $formClientType !== 'societe') || ((int) $pieceDefinition['pdl_other_only'] && ($formSiteAlreadyConnected !== 'yes' || $formPdlChoice !== 'existing_other_legal_entity')) ? ' hidden' : '';
	$requiredAttribute = (int) $pieceDefinition['required'] ? ' required' : '';
	$companyRequiredAttribute = (int) $pieceDefinition['company_only'] ? ' data-company-required="1"' : '';
	print '<tr'.$rowClass.$rowHidden.'><td class="titlefield">'.$langs->trans($pieceDefinition['label']).'</td><td><input type="file" class="flat" name="'.dol_escape_htmltag($pieceDefinition['input']).'" accept=".pdf,.jpg,.jpeg,.png"'.$requiredAttribute.$companyRequiredAttribute.'><span class="public-help">'.$langs->trans($pieceDefinition['help']).'</span></td></tr>';
}
print '</table>';
print '</section>';

print '<section class="public-section" id="public-section-mandat">';
print '<div class="public-section-header"><span class="public-step">5</span><h2>'.$langs->trans('PublicSectionMandat').'</h2></div>';
print '<table class="public-form-table">';
print '<tr><td class="titlefield">'.$langs->trans('SignerName').'</td><td><input type="text" class="flat minwidth300" name="signataire_nom" id="signataire_nom" autocomplete="name" value="'.dol_escape_htmltag($formSignataireNom).'"></td></tr>';
print '<tr><td>'.$langs->trans('SignerFunction').'</td><td><input type="text" class="flat minwidth300" name="signataire_fonction" id="signataire_fonction" data-company-required="1" value="'.dol_escape_htmltag($formSignataireFonction).'"></td></tr>';
print '<tr><td>'.$langs->trans('SignerEmail').'</td><td><input type="email" class="flat minwidth300" name="signataire_email" id="signataire_email" autocomplete="email" data-company-required="1" value="'.dol_escape_htmltag($formSignataireEmail).'"></td></tr>';
print '<tr><td>'.$langs->trans('MandatAcceptance').'</td><td><select class="flat minwidth300" name="mandat_acceptance" id="mandat_acceptance"><option value="no"'.($formMandatAcceptance === 'no' ? ' selected' : '').'>'.$langs->trans('No').'</option><option value="yes"'.($formMandatAcceptance === 'yes' ? ' selected' : '').'>'.$langs->trans('Yes').'</option></select>'.ajax_combobox('mandat_acceptance').'</td></tr>';
print '<tr><td>'.$langs->trans('Signature').'</td><td>';
print '<canvas class="public-signature-pad" id="mandat-signature-pad" width="620" height="190"></canvas>';
print '<input type="hidden" name="signature_data_url" id="signature_data_url">';
print '<span class="public-help">'.$langs->trans('PublicSignatureHelp').'</span>';
print '<br><button type="button" class="button" id="clear-signature">'.$langs->trans('ClearSignature').'</button>';
print '</td></tr>';
print '</table>';
print '</section>';

print '<script>
(function () {
	var clientType = document.getElementById("client_type");
	var siretInput = document.getElementById("client_siret");
	var productionSiretInput = document.getElementById("production_site_siret");
	var representativeLastname = document.getElementById("representative_lastname");
	var representativeFirstname = document.getElementById("representative_firstname");
	var signerName = document.getElementById("signataire_nom");
	var producerIsBuildingOwner = document.getElementById("producer_is_building_owner");
	var siteAlreadyConnected = document.getElementById("site_already_connected");
	var pdlChoice = document.getElementById("pdl_choice");
	var noRelatedProjectAttestation = document.getElementById("no_related_project_attestation");
	var canvas = document.getElementById("mandat-signature-pad");
	var hidden = document.getElementById("signature_data_url");
	var clearButton = document.getElementById("clear-signature");
	function setRowsHidden(selector, hiddenState) {
		document.querySelectorAll(selector).forEach(function (row) {
			row.hidden = hiddenState;
		});
	}
	function syncSignerName() {
		if (!clientType || clientType.value !== "societe" || !signerName || signerName.dataset.userEdited === "1") return;
		var fullname = "";
		if (representativeFirstname && representativeFirstname.value) fullname += representativeFirstname.value.trim();
		if (representativeLastname && representativeLastname.value) fullname += (fullname ? " " : "") + representativeLastname.value.trim();
		if (fullname) signerName.value = fullname;
	}
	function refreshPublicForm() {
		if (!clientType) return;
		var isCompany = clientType.value === "societe";
		setRowsHidden(".public-company-row", !isCompany);
		setRowsHidden(".public-building-owner-row", !(isCompany && producerIsBuildingOwner && producerIsBuildingOwner.value === "no"));
		setRowsHidden(".public-existing-connection-row", !(isCompany && siteAlreadyConnected && siteAlreadyConnected.value === "yes"));
		setRowsHidden(".public-existing-pdl-detail-row", !(isCompany && siteAlreadyConnected && siteAlreadyConnected.value === "yes" && pdlChoice && pdlChoice.value === "existing_same_legal_entity"));
		setRowsHidden(".public-pdl-other-piece-row", !(isCompany && siteAlreadyConnected && siteAlreadyConnected.value === "yes" && pdlChoice && pdlChoice.value === "existing_other_legal_entity"));
		setRowsHidden(".public-related-project-row", !(isCompany && noRelatedProjectAttestation && noRelatedProjectAttestation.value === "no"));
		document.querySelectorAll("[data-company-required]").forEach(function (field) {
			var row = field.closest("tr");
			field.required = isCompany && (!row || !row.hidden);
		});
		if (!isCompany && siretInput) {
			siretInput.value = "";
		}
		syncSignerName();
	}
	function bindDynamicSelect(field) {
		if (!field) return;
		field.addEventListener("change", refreshPublicForm);
		if (window.jQuery) {
			window.jQuery(field).on("change select2:select select2:clear select2:unselect", refreshPublicForm);
		}
	}
	[clientType, producerIsBuildingOwner, siteAlreadyConnected, pdlChoice, noRelatedProjectAttestation].forEach(bindDynamicSelect);
	[representativeLastname, representativeFirstname].forEach(function (field) {
		if (field) field.addEventListener("input", function () {
			syncSignerName();
		});
	});
	if (signerName) {
		signerName.addEventListener("input", function () {
			signerName.dataset.userEdited = "1";
		});
	}
	[siretInput, productionSiretInput].forEach(function (field) {
		if (!field) return;
		field.addEventListener("input", function () {
			this.value = this.value.replace(/\\D/g, "").slice(0, 14);
		});
	});
	refreshPublicForm();
	var stepsNav = document.querySelector(".public-steps");
	var stepLinks = stepsNav ? Array.prototype.slice.call(stepsNav.querySelectorAll("a[href^=\"#public-section-\"]")) : [];
	var stepSections = stepLinks.map(function (link) {
		var sectionId = link.getAttribute("href");
		return sectionId ? document.querySelector(sectionId) : null;
	});
	var stepsCssTarget = stepsNav ? stepsNav.closest(".public-procedurespv") : null;
	function setActiveStep(activeIndex) {
		stepLinks.forEach(function (link, index) {
			if (index === activeIndex) {
				link.classList.add("is-active");
				link.setAttribute("aria-current", "step");
			} else {
				link.classList.remove("is-active");
				link.removeAttribute("aria-current");
			}
		});
	}
	function refreshStepsMetrics() {
		if (!stepsNav) return;
		(stepsCssTarget || document.documentElement).style.setProperty("--public-steps-height", stepsNav.offsetHeight + "px");
	}
	function refreshActiveStep() {
		if (!stepsNav || stepSections.length === 0) return;
		var readingLine = stepsNav.getBoundingClientRect().bottom + 18;
		var activeIndex = 0;
		stepSections.forEach(function (section, index) {
			if (section && section.getBoundingClientRect().top <= readingLine) {
				activeIndex = index;
			}
		});
		stepsNav.classList.toggle("is-stuck", stepsNav.getBoundingClientRect().top <= 0 && window.pageYOffset > 0);
		setActiveStep(activeIndex);
	}
	function refreshStepsState() {
		refreshStepsMetrics();
		refreshActiveStep();
	}
	refreshStepsState();
	stepLinks.forEach(function (link) {
		link.addEventListener("click", function () {
			window.setTimeout(refreshStepsState, 0);
		});
	});
	window.addEventListener("scroll", refreshActiveStep, {passive:true});
	window.addEventListener("resize", refreshStepsState);
	if (!canvas || !hidden) return;
	var ctx = canvas.getContext("2d");
	var drawing = false;
	var hasInk = false;
	ctx.lineWidth = 2;
	ctx.lineCap = "round";
	ctx.strokeStyle = "#111";
	function position(evt) {
		var rect = canvas.getBoundingClientRect();
		var point = evt.touches && evt.touches.length ? evt.touches[0] : evt;
		return {
			x: (point.clientX - rect.left) * (canvas.width / rect.width),
			y: (point.clientY - rect.top) * (canvas.height / rect.height)
		};
	}
	function start(evt) {
		drawing = true;
		var pos = position(evt);
		ctx.beginPath();
		ctx.moveTo(pos.x, pos.y);
		evt.preventDefault();
	}
	function move(evt) {
		if (!drawing) return;
		var pos = position(evt);
		ctx.lineTo(pos.x, pos.y);
		ctx.stroke();
		hasInk = true;
		hidden.value = canvas.toDataURL("image/png");
		evt.preventDefault();
	}
	function stop() {
		drawing = false;
		if (hasInk) hidden.value = canvas.toDataURL("image/png");
	}
	canvas.addEventListener("mousedown", start);
	canvas.addEventListener("mousemove", move);
	canvas.addEventListener("mouseup", stop);
	canvas.addEventListener("mouseleave", stop);
	canvas.addEventListener("touchstart", start, {passive:false});
	canvas.addEventListener("touchmove", move, {passive:false});
	canvas.addEventListener("touchend", stop);
	if (clearButton) {
		clearButton.addEventListener("click", function () {
			ctx.clearRect(0, 0, canvas.width, canvas.height);
			hasInk = false;
			hidden.value = "";
		});
	}
}());
</script>';

print '<div class="public-actions"><input type="submit" class="button button-save" value="'.$langs->trans('SubmitCollecte').'"></div>';
print '</form>';
procedurespvPublicPrintLegalFooter($langs, $companyLegalData);
print '</div>';
print '</main>';

llxFooter('', 'public');
$db->close();
