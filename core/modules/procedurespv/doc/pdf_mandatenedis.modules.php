<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * ENEDIS representation mandate PDF model.
 */
class pdf_mandatenedis
{
	/** @var DoliDB */
	public $db;

	/** @var string */
	public $name = 'mandatenedis';

	/** @var string */
	public $description = 'MandatPdfModelDescription';

	/** @var string */
	public $version = 'dolibarr';

	/** @var string */
	public $type = 'pdf';

	/** @var string */
	public $scandir = 'procedurespv/core/modules/procedurespv/doc';

	/** @var string */
	public $document_model_type = 'procedurespv_mandatenedis';

	/** @var float */
	public $page_largeur = 210;

	/** @var float */
	public $page_hauteur = 297;

	/** @var array{0:float,1:float} */
	public $format = array(210, 297);

	/** @var int */
	public $option_logo = 1;

	/** @var int */
	public $option_multilang = 1;

	/** @var int */
	public $option_freetext = 0;

	/** @var int */
	public $option_draft_watermark = 0;

	/** @var array{0:int,1:int} */
	public $phpmin = array(8, 0);

	/** @var string */
	public $error = '';

	/** @var float */
	private $marginLeft = 11;

	/** @var float */
	private $marginRight = 11;

	/** @var float */
	private $marginTop = 10;

	/** @var float */
	private $contentBottom = 278;

	/** @var string */
	private $pdfFont = 'helvetica';

	/** @var Translate|null */
	private $currentOutputLangs;

	/** @var array{name:string,address:string,siret:string,managers:string,town:string,logo:string} */
	private $currentCompanyData = array(
		'name' => '',
		'address' => '',
		'siret' => '',
		'managers' => '',
		'town' => '',
		'logo' => '',
	);

	/**
	 * Constructor.
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		$this->db = $db;
	}

	/**
	 * Return model information for native Dolibarr setup tables.
	 *
	 * @param Translate $langs Translation handler
	 * @return string
	 */
	public function info($langs)
	{
		return $langs->trans('MandatPdfModelDescription');
	}

	/**
	 * Write the signed mandate PDF.
	 *
	 * @param Raccordement $object Raccordement
	 * @param Translate $outputlangs Output language
	 * @param string $outputDir Output directory
	 * @param array{
	 *     client_type:string,
	 *     client_name:string,
	 *     client_siret:string,
	 *     mandant_representative:string,
	 *     mandant_address:string,
	 *     signataire_nom:string,
	 *     signature_town:string,
	 *     mandate_power_sign_contracts:int,
	 *     mandate_power_pay_connection_costs:int,
	 *     mandate_power_execute_l342_6:int,
	 *     mandate_power_sign_access_contract:int,
	 *     signature_data_url:string
	 * } $data Mandate data
	 * @return string Generated filename, empty string on error
	 */
	public function write_file($object, $outputlangs, $outputDir, array $data)
	{
		global $conf;

		require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
		require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
		require_once DOL_DOCUMENT_ROOT.'/core/lib/company.lib.php';
		require_once dol_buildpath('/procedurespv/lib/procedurespv.lib.php', 0);
		if (file_exists(DOL_DOCUMENT_ROOT.'/core/lib/pdf.lib.php')) {
			require_once DOL_DOCUMENT_ROOT.'/core/lib/pdf.lib.php';
		}

		if (!$this->allPowersAreGranted($data)) {
			$this->error = 'MandatAllPowersRequired';
			return '';
		}
		if ($outputDir === '' || dol_mkdir($outputDir) < 0) {
			$this->error = 'ErrorOutputDirectoryUnavailable';
			return '';
		}

		$ref = dol_sanitizeFileName((string) $object->ref);
		$filename = $ref.'_mandat_enedis_signe_'.dol_print_date(dol_now(), '%Y%m%d%H%M%S').'.pdf';
		$outputFile = $outputDir.'/'.$filename;
		$pdf = $this->createPdfInstance();
		if (!is_object($pdf)) {
			$this->error = 'ErrorPdfEngineUnavailable';
			return '';
		}

		$outputlangs->loadLangs(array('main', 'companies', 'procedurespv@procedurespv'));
		$entity = !empty($object->entity) ? (int) $object->entity : (int) $conf->entity;
		$companyData = $this->getCompanyData($object, $entity);
		$stampPath = procedurespvGetMandatStampPath($entity);
		$this->configurePdf($pdf, $outputlangs);

		$pdf->AddPage();
		$this->writeDocumentHeader($pdf, $outputlangs, $companyData);
		$this->writeParties($pdf, $outputlangs, $data, $companyData);
		$this->writeMandateFirstPage($pdf, $outputlangs);
		$this->writeFooter($pdf, $outputlangs, $companyData);

		$pdf->AddPage();
		$this->writeDocumentHeader($pdf, $outputlangs, $companyData);
		$this->writeMandateSecondPage($pdf, $outputlangs, $object, $data, $companyData, $stampPath);
		$this->writeFooter($pdf, $outputlangs, $companyData);

		$pdf->Output($outputFile, 'F');
		if (!is_readable($outputFile)) {
			$this->error = 'ErrorPdfNotGenerated';
			return '';
		}

		return $filename;
	}

	/**
	 * Configure the native PDF instance using Dolibarr PDF settings.
	 *
	 * @param object $pdf PDF handler
	 * @param Translate $outputlangs Output language
	 * @return void
	 */
	private function configurePdf($pdf, $outputlangs)
	{
		$this->marginLeft = (float) getDolGlobalInt('MAIN_PDF_MARGIN_LEFT', 11);
		$this->marginRight = (float) getDolGlobalInt('MAIN_PDF_MARGIN_RIGHT', 11);
		$this->marginTop = (float) getDolGlobalInt('MAIN_PDF_MARGIN_TOP', 10);
		$this->contentBottom = $this->page_hauteur - max(19, (float) getDolGlobalInt('MAIN_PDF_MARGIN_BOTTOM', 10) + 9);
		$this->pdfFont = function_exists('pdf_getPDFFont') ? (string) pdf_getPDFFont($outputlangs) : 'helvetica';

		if (method_exists($pdf, 'setPrintHeader')) {
			$pdf->setPrintHeader(false);
			$pdf->setPrintFooter(false);
		}
		$pdf->SetCreator('Dolibarr '.(defined('DOL_VERSION') ? DOL_VERSION : ''));
		$pdf->SetAuthor('Procedures PV');
		$pdf->SetTitle($this->toPdf($outputlangs, $outputlangs->transnoentitiesnoconv('MandatEnedisSignedPdfTitle')));
		$pdf->SetMargins($this->marginLeft, $this->marginTop, $this->marginRight);
		$pdf->SetAutoPageBreak(false);
		$pdf->SetFont($this->pdfFont, '', 8);
	}

	/**
	 * Write the PowerPlantPV-attestation-style document header.
	 *
	 * @param object $pdf PDF handler
	 * @param Translate $outputlangs Output language
	 * @param array{name:string,address:string,siret:string,managers:string,town:string,logo:string} $companyData Company data
	 * @return void
	 */
	private function writeDocumentHeader($pdf, $outputlangs, array $companyData)
	{
		$this->currentOutputLangs = $outputlangs;
		$this->currentCompanyData = $companyData;
		$top = $this->marginTop;
		$logoBottom = $top;
		if ($companyData['logo'] !== '' && is_readable($companyData['logo'])) {
			$logoHeight = function_exists('pdf_getHeightForLogo') ? (float) pdf_getHeightForLogo($companyData['logo']) : 18.0;
			$logoHeight = min(20, max(10, $logoHeight));
			$pdf->Image($companyData['logo'], $this->marginLeft, $top, 0, $logoHeight, $this->getImageType($companyData['logo']));
			$logoBottom = $top + $logoHeight;
		} elseif ($companyData['name'] !== '') {
			$pdf->SetTextColor(0, 0, 60);
			$pdf->SetFont($this->pdfFont, 'B', 10);
			$pdf->SetXY($this->marginLeft, $top + 2);
			$pdf->MultiCell(42, 5, $this->toPdf($outputlangs, $companyData['name']), 0, 'L');
			$logoBottom = $pdf->GetY();
		}

		$titleX = $this->marginLeft + 44;
		$pdf->SetTextColor(0, 0, 60);
		$pdf->SetXY($titleX, $top);
		$pdf->SetFont($this->pdfFont, 'B', 12);
		$pdf->MultiCell($this->page_largeur - $this->marginRight - $titleX, 5.5, $this->toPdf($outputlangs, $outputlangs->transnoentitiesnoconv('MandatEnedisOfficialTitle')), 0, 'R');
		$titleBottom = $pdf->GetY();
		$pdf->SetX($titleX);
		$pdf->SetFont($this->pdfFont, '', 7.5);
		$pdf->MultiCell($this->page_largeur - $this->marginRight - $titleX, 4, $this->toPdf($outputlangs, $outputlangs->transnoentitiesnoconv('MandatEnedisOfficialReference')), 0, 'R');
		$titleBottom = max($titleBottom, $pdf->GetY());

		$pdf->SetDrawColor(0, 0, 60);
		$lineY = max($logoBottom, $titleBottom) + 3;
		$pdf->Line($this->marginLeft, $lineY, $this->page_largeur - $this->marginRight, $lineY);
		$pdf->SetTextColor(0, 0, 0);
		$pdf->SetY($lineY + 5);
	}

	/**
	 * Write the identity of the principal and representative.
	 *
	 * @param object $pdf PDF handler
	 * @param Translate $outputlangs Output language
	 * @param array<string,mixed> $data Mandate data
	 * @param array{name:string,address:string,siret:string,managers:string,town:string,logo:string} $companyData Company data
	 * @return void
	 */
	private function writeParties($pdf, $outputlangs, array $data, array $companyData)
	{
		$this->writeHeading($pdf, $outputlangs, 'MandatEnedisPartiesIntro', 10);
		$isCompany = (string) $data['client_type'] === 'societe';
		$isPublicEntity = in_array((string) $data['client_type'], array('collectivite', 'administration'), true);
		$y = $pdf->GetY();
		$this->writeInlineCheckbox($pdf, $outputlangs, $this->marginLeft, $y, 56, $outputlangs->transnoentitiesnoconv('MandatEnedisCompanyStatus'), $isCompany);
		$this->writeInlineCheckbox($pdf, $outputlangs, $this->marginLeft + 62, $y, 105, $outputlangs->transnoentitiesnoconv('MandatEnedisPublicEntityStatus'), $isPublicEntity);
		$pdf->SetY($y + 7);

		$this->writeKeyValue($pdf, $outputlangs, $outputlangs->transnoentitiesnoconv('MandatEnedisCompanyOrAuthorityName'), (string) $data['client_name']);
		$this->writeKeyValue($pdf, $outputlangs, $outputlangs->transnoentitiesnoconv('SIRET'), (string) $data['client_siret']);
		$this->writeKeyValue($pdf, $outputlangs, $outputlangs->transnoentitiesnoconv('MandatEnedisMandantRepresentedBy'), (string) $data['mandant_representative']);
		$this->writeKeyValue($pdf, $outputlangs, $outputlangs->transnoentitiesnoconv('Address'), (string) $data['mandant_address']);
		$pdf->Ln(1);

		$companyName = $this->valueOrNotAvailable($companyData['name'], $outputlangs);
		$companySiret = $this->valueOrNotAvailable($companyData['siret'], $outputlangs);
		$companyManagers = $this->valueOrNotAvailable($companyData['managers'], $outputlangs);
		$this->writeParagraph($pdf, $outputlangs, $outputlangs->transnoentitiesnoconv('MandatEnedisPartiesMandataireText', $companyName, $companySiret, $companyManagers));
		$this->writeParagraph($pdf, $outputlangs, $outputlangs->transnoentitiesnoconv('MandatEnedisMandataireDesignationText'));
		$this->writeHeading($pdf, $outputlangs, 'MandatEnedisAgreementTitle', 9.5);
	}

	/**
	 * Write the first legal page and the four granted powers.
	 *
	 * @param object $pdf PDF handler
	 * @param Translate $outputlangs Output language
	 * @return void
	 */
	private function writeMandateFirstPage($pdf, $outputlangs)
	{
		$this->writeParagraph($pdf, $outputlangs, $outputlangs->transnoentitiesnoconv('MandatEnedisSpecialMandateText'));
		$this->writeParagraph($pdf, $outputlangs, $outputlangs->transnoentitiesnoconv('MandatEnedisContractDocumentsText'));
		$this->writeParagraph($pdf, $outputlangs, $outputlangs->transnoentitiesnoconv('MandatEnedisInterlocutorText'));
		$this->writeParagraph($pdf, $outputlangs, $outputlangs->transnoentitiesnoconv('MandatEnedisPowersIntro'));

		$this->writeCheckedPower($pdf, $outputlangs, 'MandatEnedisPowerSignDocuments', array(
			'MandatEnedisDocumentPrac',
			'MandatEnedisDocumentPtf',
			'MandatEnedisDocumentConnectionAgreement',
			'MandatEnedisDocumentDirectConnectionAgreement',
			'MandatEnedisDocumentOperatingAgreement',
			'MandatEnedisDocumentL3422',
			'MandatEnedisDocumentAmendment',
		), 'MandatEnedisPowerDocumentsInformation');
		$this->writeCheckedPower($pdf, $outputlangs, 'MandatEnedisPowerPayConnectionCosts');
		$this->writeCheckedPower($pdf, $outputlangs, 'MandatEnedisPowerExecuteL3426');
		$this->writeCheckedPower($pdf, $outputlangs, 'MandatEnedisPowerSignAccessContract', array(), 'MandatEnedisPowerDocumentsInformation');

		$pdf->Ln(1);
		$pdf->SetFont($this->pdfFont, 'I', 6.2);
		$pdf->MultiCell(0, 3.2, $this->toPdf($outputlangs, '1. '.$outputlangs->transnoentitiesnoconv('MandatEnedisFootnotePowers')), 0, 'L');
	}

	/**
	 * Write the second legal page, site and signatures.
	 *
	 * @param object $pdf PDF handler
	 * @param Translate $outputlangs Output language
	 * @param Raccordement $object Raccordement
	 * @param array<string,mixed> $data Mandate data
	 * @param array{name:string,address:string,siret:string,managers:string,town:string,logo:string} $companyData Company data
	 * @param string $stampPath Company stamp path
	 * @return void
	 */
	private function writeMandateSecondPage($pdf, $outputlangs, $object, array $data, array $companyData, $stampPath)
	{
		$this->writeParagraph($pdf, $outputlangs, $outputlangs->transnoentitiesnoconv('MandatEnedisRepresentativeMayIntro'));
		$this->writeBullet($pdf, $outputlangs, $outputlangs->transnoentitiesnoconv('MandatEnedisPowerConfidentialInfo'));
		$this->writeBullet($pdf, $outputlangs, $outputlangs->transnoentitiesnoconv('MandatEnedisPowerTerminate'));
		$this->writeHeading($pdf, $outputlangs, 'MandatEnedisNatureDurationTitle', 9.5);
		$this->writeParagraph($pdf, $outputlangs, $outputlangs->transnoentitiesnoconv('MandatEnedisValidityText'));
		$this->writeBullet($pdf, $outputlangs, $outputlangs->transnoentitiesnoconv('MandatEnedisEndCommissioning'));
		$this->writeBullet($pdf, $outputlangs, $outputlangs->transnoentitiesnoconv('MandatEnedisEndRevocation'));
		$this->writeNestedBullet($pdf, $outputlangs, $outputlangs->transnoentitiesnoconv('MandatEnedisEndRevocationDate'));
		$this->writeNestedBullet($pdf, $outputlangs, $outputlangs->transnoentitiesnoconv('MandatEnedisEndRevocationNotification'));

		$this->writeHeading($pdf, $outputlangs, 'MandatEnedisSiteDesignationTitle', 9.5);
		$siteAddress = implode(', ', array_filter(array(
			trim((string) $object->site_address_snapshot),
			trim((string) $object->site_zip_snapshot.' '.(string) $object->site_town_snapshot),
		)));
		$this->writeKeyValue($pdf, $outputlangs, $outputlangs->transnoentitiesnoconv('MandatEnedisSiteAddress'), $siteAddress);
		$this->writeKeyValue($pdf, $outputlangs, $outputlangs->transnoentitiesnoconv('MandatEnedisConnectionNature'), $outputlangs->transnoentitiesnoconv('MandatEnedisProductionConnection'));

		$pdf->Ln(4);
		$this->writeParagraph($pdf, $outputlangs, $outputlangs->transnoentitiesnoconv('MandatEnedisSignatureOriginals'));
		$this->writeParagraph($pdf, $outputlangs, $outputlangs->transnoentitiesnoconv('MandatEnedisSignatureLegalText'));
		$this->writeSignatureBoxes($pdf, $outputlangs, $data, $companyData, $stampPath);

		$pdf->Ln(3);
		$pdf->SetFont($this->pdfFont, 'I', 6.2);
		$pdf->MultiCell(0, 3.2, $this->toPdf($outputlangs, '2. '.$outputlangs->transnoentitiesnoconv('MandatEnedisFootnoteConfidentiality')), 0, 'L');
	}

	/**
	 * Write the principal and representative signature boxes.
	 *
	 * @param object $pdf PDF handler
	 * @param Translate $outputlangs Output language
	 * @param array<string,mixed> $data Mandate data
	 * @param array{name:string,address:string,siret:string,managers:string,town:string,logo:string} $companyData Company data
	 * @param string $stampPath Company stamp path
	 * @return void
	 */
	private function writeSignatureBoxes($pdf, $outputlangs, array $data, array $companyData, $stampPath)
	{
		$gap = 9.0;
		$boxWidth = ($this->page_largeur - $this->marginLeft - $this->marginRight - $gap) / 2;
		$leftX = $this->marginLeft;
		$rightX = $leftX + $boxWidth + $gap;
		$boxTop = $pdf->GetY();
		$boxHeight = 42.0;
		$date = dol_print_date(dol_now(), 'day');

		$pdf->SetFont($this->pdfFont, 'B', 8);
		$pdf->SetXY($leftX, $boxTop);
		$pdf->MultiCell($boxWidth, 4, $this->toPdf($outputlangs, $outputlangs->transnoentitiesnoconv('MandatEnedisMandant').' : '.(string) $data['signataire_nom']), 0, 'L');
		$pdf->SetXY($rightX, $boxTop);
		$representative = trim($companyData['managers'].' / '.$companyData['name'], ' /');
		$pdf->MultiCell($boxWidth, 4, $this->toPdf($outputlangs, $outputlangs->transnoentitiesnoconv('MandatEnedisMandataire').' : '.$representative), 0, 'L');
		$detailsTop = max($pdf->GetY(), $boxTop + 8);
		$pdf->SetFont($this->pdfFont, '', 7.5);
		$pdf->SetXY($leftX, $detailsTop);
		$pdf->MultiCell($boxWidth, 4, $this->toPdf($outputlangs, $outputlangs->transnoentitiesnoconv('MandatEnedisSignedAtOn', (string) $data['signature_town'], $date)), 0, 'L');
		$pdf->SetXY($rightX, $detailsTop);
		$pdf->MultiCell($boxWidth, 4, $this->toPdf($outputlangs, $outputlangs->transnoentitiesnoconv('MandatEnedisSignedAtOn', $companyData['town'], $date)), 0, 'L');

		$frameTop = $detailsTop + 8;
		$this->drawNativeFrame($pdf, $leftX, $frameTop, $boxWidth, $boxHeight);
		$this->drawNativeFrame($pdf, $rightX, $frameTop, $boxWidth, $boxHeight);

		$signatureBinary = $this->extractPngData((string) $data['signature_data_url']);
		if ($signatureBinary !== '') {
			$pdf->Image('@'.$signatureBinary, $leftX + 5, $frameTop + 7, min(68, $boxWidth - 10), 21, 'PNG');
		}
		$this->writeImageInBox($pdf, $stampPath, $rightX, $frameTop, $boxWidth, $boxHeight);

		$pdf->SetFont($this->pdfFont, 'I', 7);
		$pdf->SetXY($leftX + 3, $frameTop + $boxHeight - 7);
		$pdf->MultiCell($boxWidth - 6, 4, $this->toPdf($outputlangs, $outputlangs->transnoentitiesnoconv('Signature')), 0, 'L');
		$pdf->SetY($frameTop + $boxHeight + 2);
	}

	/**
	 * Draw a compact native-looking frame.
	 *
	 * @param object $pdf PDF handler
	 * @param float $x X position
	 * @param float $y Y position
	 * @param float $width Width
	 * @param float $height Height
	 * @return void
	 */
	private function drawNativeFrame($pdf, $x, $y, $width, $height)
	{
		$pdf->SetDrawColor(190, 190, 190);
		$radius = (float) getDolGlobalString('MAIN_PDF_FRAME_CORNER_RADIUS', '0');
		if ($radius > 0 && method_exists($pdf, 'RoundedRect')) {
			$pdf->RoundedRect($x, $y, $width, $height, $radius, '1111', 'D');
		} else {
			$pdf->Rect($x, $y, $width, $height);
		}
		$pdf->SetDrawColor(0, 0, 0);
	}

	/**
	 * Render one image centered in a signature box.
	 *
	 * @param object $pdf PDF handler
	 * @param string $path Image path
	 * @param float $x Box X
	 * @param float $y Box Y
	 * @param float $width Box width
	 * @param float $height Box height
	 * @return void
	 */
	private function writeImageInBox($pdf, $path, $x, $y, $width, $height)
	{
		if ($path === '' || !is_readable($path)) {
			return;
		}

		$padding = 4.0;
		$maxWidth = $width - (2 * $padding);
		$maxHeight = $height - (2 * $padding);
		$imageWidth = 0.0;
		$imageHeight = $maxHeight;
		$size = @getimagesize($path);
		if (is_array($size) && !empty($size[0]) && !empty($size[1])) {
			$scale = min($maxWidth / (float) $size[0], $maxHeight / (float) $size[1]);
			$imageWidth = (float) $size[0] * $scale;
			$imageHeight = (float) $size[1] * $scale;
		}
		$imageX = $x + $padding + ($imageWidth > 0 ? (($maxWidth - $imageWidth) / 2) : 0);
		$imageY = $y + $padding + max(0, ($maxHeight - $imageHeight) / 2);
		$pdf->Image($path, $imageX, $imageY, $imageWidth, $imageHeight, $this->getImageType($path));
	}

	/**
	 * Write one checked mandate power.
	 *
	 * @param object $pdf PDF handler
	 * @param Translate $outputlangs Output language
	 * @param string $labelKey Power translation key
	 * @param list<string> $detailKeys Detail translation keys
	 * @param string $informationKey Optional information translation key
	 * @return void
	 */
	private function writeCheckedPower($pdf, $outputlangs, $labelKey, array $detailKeys = array(), $informationKey = '')
	{
		$this->ensureSpace($pdf, 12);
		$x = $this->marginLeft + 5;
		$y = $pdf->GetY();
		$this->drawCheckbox($pdf, $x, $y + 0.7, true);
		$pdf->SetXY($x + 7, $y);
		$pdf->SetFont($this->pdfFont, '', 7.6);
		$pdf->MultiCell($this->page_largeur - $this->marginRight - $x - 7, 3.8, $this->toPdf($outputlangs, $outputlangs->transnoentitiesnoconv($labelKey)), 0, 'L');
		foreach ($detailKeys as $detailKey) {
			$this->writeNestedBullet($pdf, $outputlangs, $outputlangs->transnoentitiesnoconv($detailKey), 14);
		}
		if ($informationKey !== '') {
			$pdf->SetX($x + 14);
			$pdf->SetFont($this->pdfFont, 'I', 7.2);
			$pdf->MultiCell($this->page_largeur - $this->marginRight - $x - 14, 3.6, $this->toPdf($outputlangs, $outputlangs->transnoentitiesnoconv($informationKey)), 0, 'L');
		}
		$pdf->Ln(0.8);
	}

	/**
	 * Write one regular bullet.
	 *
	 * @param object $pdf PDF handler
	 * @param Translate $outputlangs Output language
	 * @param string $text Text
	 * @return void
	 */
	private function writeBullet($pdf, $outputlangs, $text)
	{
		$this->ensureSpace($pdf, 9);
		$x = $pdf->GetX();
		$y = $pdf->GetY();
		$pdf->SetFont($this->pdfFont, 'B', 8);
		$pdf->SetXY($x + 4, $y);
		$pdf->Cell(4, 4, $this->toPdf($outputlangs, '•'), 0, 0, 'L');
		$pdf->SetFont($this->pdfFont, '', 8);
		$pdf->SetXY($x + 10, $y);
		$pdf->MultiCell($this->page_largeur - $this->marginRight - $x - 10, 4, $this->toPdf($outputlangs, $text), 0, 'L');
	}

	/**
	 * Write one nested bullet.
	 *
	 * @param object $pdf PDF handler
	 * @param Translate $outputlangs Output language
	 * @param string $text Text
	 * @param float $indent Indent from left margin
	 * @return void
	 */
	private function writeNestedBullet($pdf, $outputlangs, $text, $indent = 18)
	{
		$this->ensureSpace($pdf, 6);
		$y = $pdf->GetY();
		$pdf->SetXY($this->marginLeft + $indent, $y);
		$pdf->SetFont($this->pdfFont, '', 7.4);
		$pdf->MultiCell($this->page_largeur - $this->marginRight - $this->marginLeft - $indent, 3.6, $this->toPdf($outputlangs, '○  '.$text), 0, 'L');
	}

	/**
	 * Write a heading.
	 *
	 * @param object $pdf PDF handler
	 * @param Translate $outputlangs Output language
	 * @param string $key Translation key
	 * @param float $fontSize Font size
	 * @return void
	 */
	private function writeHeading($pdf, $outputlangs, $key, $fontSize)
	{
		$this->ensureSpace($pdf, 8);
		$pdf->SetFont($this->pdfFont, 'B', $fontSize);
		$pdf->MultiCell(0, 5, $this->toPdf($outputlangs, $outputlangs->transnoentitiesnoconv($key)), 0, 'L');
		$pdf->Ln(0.5);
	}

	/**
	 * Write a paragraph.
	 *
	 * @param object $pdf PDF handler
	 * @param Translate $outputlangs Output language
	 * @param string $text Text
	 * @return void
	 */
	private function writeParagraph($pdf, $outputlangs, $text)
	{
		$this->ensureSpace($pdf, 12);
		$pdf->SetFont($this->pdfFont, '', 8);
		$pdf->MultiCell(0, 4, $this->toPdf($outputlangs, $text), 0, 'L');
		$pdf->Ln(1);
	}

	/**
	 * Write one label/value line.
	 *
	 * @param object $pdf PDF handler
	 * @param Translate $outputlangs Output language
	 * @param string $label Label
	 * @param string $value Value
	 * @return void
	 */
	private function writeKeyValue($pdf, $outputlangs, $label, $value)
	{
		$this->ensureSpace($pdf, 7);
		$value = $this->valueOrNotAvailable($value, $outputlangs);
		$x = $this->marginLeft;
		$y = $pdf->GetY();
		$labelWidth = 52.0;
		$pdf->SetFont($this->pdfFont, 'B', 7.7);
		$pdf->SetXY($x, $y);
		$pdf->MultiCell($labelWidth, 4, $this->toPdf($outputlangs, $label), 0, 'L');
		$labelBottom = $pdf->GetY();
		$pdf->SetXY($x + $labelWidth, $y);
		$pdf->SetFont($this->pdfFont, '', 7.7);
		$pdf->MultiCell($this->page_largeur - $this->marginRight - $x - $labelWidth, 4, $this->toPdf($outputlangs, $value), 0, 'L');
		$pdf->SetY(max($labelBottom, $pdf->GetY()));
	}

	/**
	 * Write a checkbox and its label on one line.
	 *
	 * @param object $pdf PDF handler
	 * @param Translate $outputlangs Output language
	 * @param float $x X position
	 * @param float $y Y position
	 * @param float $width Label width
	 * @param string $label Label
	 * @param bool $checked Checked state
	 * @return void
	 */
	private function writeInlineCheckbox($pdf, $outputlangs, $x, $y, $width, $label, $checked)
	{
		$this->drawCheckbox($pdf, $x, $y + 0.6, $checked);
		$pdf->SetXY($x + 6, $y);
		$pdf->SetFont($this->pdfFont, '', 8);
		$pdf->MultiCell($width - 6, 4, $this->toPdf($outputlangs, $label), 0, 'L');
	}

	/**
	 * Draw one square checkbox.
	 *
	 * @param object $pdf PDF handler
	 * @param float $x X position
	 * @param float $y Y position
	 * @param bool $checked Checked state
	 * @return void
	 */
	private function drawCheckbox($pdf, $x, $y, $checked)
	{
		$size = 3.6;
		$pdf->Rect($x, $y, $size, $size);
		if ($checked) {
			$pdf->SetLineWidth(0.45);
			$pdf->Line($x + 0.7, $y + 1.9, $x + 1.5, $y + 2.9);
			$pdf->Line($x + 1.5, $y + 2.9, $x + 3.0, $y + 0.8);
			$pdf->SetLineWidth(0.2);
		}
	}

	/**
	 * Write the compact legal footer on the current page.
	 *
	 * @param object $pdf PDF handler
	 * @param Translate $outputlangs Output language
	 * @param array{name:string,address:string,siret:string,managers:string,town:string,logo:string} $companyData Company data
	 * @return void
	 */
	private function writeFooter($pdf, $outputlangs, array $companyData)
	{
		$footerY = $this->page_hauteur - 14;
		$pdf->SetDrawColor(0, 0, 60);
		$pdf->Line($this->marginLeft, $footerY - 2, $this->page_largeur - $this->marginRight, $footerY - 2);
		$pdf->SetTextColor(0, 0, 60);
		$pdf->SetFont($this->pdfFont, 'B', 6.2);
		$pdf->SetXY($this->marginLeft, $footerY);
		$pdf->MultiCell(0, 3, $this->toPdf($outputlangs, $companyData['name']), 0, 'C');
		$pdf->SetFont($this->pdfFont, '', 5.8);
		$legalLine = implode(' - ', array_filter(array($companyData['address'], $companyData['siret'] !== '' ? 'SIRET : '.$companyData['siret'] : '')));
		$pdf->MultiCell(0, 3, $this->toPdf($outputlangs, $legalLine), 0, 'C');
		$pdf->SetXY($this->marginLeft, $this->page_hauteur - 6);
		$pdf->MultiCell(0, 3, $this->toPdf($outputlangs, $outputlangs->transnoentitiesnoconv('MandatEnedisOfficialReference').' - '.$pdf->getAliasNumPage().'/'.$pdf->getAliasNbPages()), 0, 'R');
		$pdf->SetTextColor(0, 0, 0);
	}

	/**
	 * Return company data and logo for the object's entity.
	 *
	 * @param Raccordement $object Raccordement
	 * @param int $entity Entity id
	 * @return array{name:string,address:string,siret:string,managers:string,town:string,logo:string}
	 */
	private function getCompanyData($object, $entity)
	{
		global $conf, $mysoc;

		$isCurrentEntity = (int) $entity === (int) $conf->entity;
		$nameFallback = ($isCurrentEntity && is_object($mysoc) && !empty($mysoc->name)) ? (string) $mysoc->name : '';
		$addressFallback = ($isCurrentEntity && is_object($mysoc) && !empty($mysoc->address)) ? (string) $mysoc->address : '';
		$zipFallback = ($isCurrentEntity && is_object($mysoc) && !empty($mysoc->zip)) ? (string) $mysoc->zip : '';
		$townFallback = ($isCurrentEntity && is_object($mysoc) && !empty($mysoc->town)) ? (string) $mysoc->town : '';
		$siretFallback = ($isCurrentEntity && is_object($mysoc) && !empty($mysoc->idprof2)) ? (string) $mysoc->idprof2 : '';
		$managersFallback = ($isCurrentEntity && is_object($mysoc) && !empty($mysoc->managers)) ? (string) $mysoc->managers : '';
		$logoFallback = ($isCurrentEntity && is_object($mysoc) && !empty($mysoc->logo)) ? (string) $mysoc->logo : '';
		$smallLogoFallback = ($isCurrentEntity && is_object($mysoc) && !empty($mysoc->logo_small)) ? (string) $mysoc->logo_small : '';

		$name = $this->getCompanyConst('MAIN_INFO_SOCIETE_NOM', $nameFallback, $entity);
		if ($name === '') {
			$name = $this->getCompanyConst('MAIN_INFO_SOCIETE_NAME', $nameFallback, $entity);
		}
		$address = $this->getCompanyConst('MAIN_INFO_SOCIETE_ADDRESS', $addressFallback, $entity);
		$zip = $this->getCompanyConst('MAIN_INFO_SOCIETE_ZIP', $zipFallback, $entity);
		$town = $this->getCompanyConst('MAIN_INFO_SOCIETE_TOWN', $townFallback, $entity);
		$logo = $this->getCompanyConst('MAIN_INFO_SOCIETE_LOGO', $logoFallback, $entity);
		$smallLogo = $this->getCompanyConst('MAIN_INFO_SOCIETE_LOGO_SMALL', $smallLogoFallback, $entity);

		return array(
			'name' => $name,
			'address' => trim($address.' '.trim($zip.' '.$town)),
			'siret' => $this->getCompanyConst('MAIN_INFO_SIRET', $siretFallback, $entity),
			'managers' => $this->getCompanyConst('MAIN_INFO_SOCIETE_MANAGERS', $managersFallback, $entity),
			'town' => $town,
			'logo' => $this->resolveCompanyLogoPath($object, $logo, $smallLogo),
		);
	}

	/**
	 * Resolve the entity-aware company logo path.
	 *
	 * @param Raccordement $object Raccordement
	 * @param string $logo Regular logo filename
	 * @param string $smallLogo Small logo filename
	 * @return string
	 */
	private function resolveCompanyLogoPath($object, $logo, $smallLogo)
	{
		$logoDir = function_exists('getMultidirOutput') ? (string) getMultidirOutput($object, 'mycompany') : '';
		if ($logoDir === '') {
			return '';
		}
		if (!getDolGlobalInt('MAIN_PDF_USE_LARGE_LOGO') && $smallLogo !== '') {
			$smallPath = $logoDir.'/logos/thumbs/'.$smallLogo;
			if (is_readable($smallPath)) {
				return $smallPath;
			}
		}
		if ($logo !== '') {
			$logoPath = $logoDir.'/logos/'.$logo;
			if (is_readable($logoPath)) {
				return $logoPath;
			}
		}

		return '';
	}

	/**
	 * Return one entity-aware company constant.
	 *
	 * @param string $name Constant name
	 * @param string $fallback Fallback
	 * @param int $entity Entity id
	 * @return string
	 */
	private function getCompanyConst($name, $fallback, $entity)
	{
		global $conf;

		$value = dolibarr_get_const($this->db, $name, $entity);
		if ($value === '' && (int) $entity === (int) $conf->entity) {
			$value = getDolGlobalString($name, '');
		}
		if ($value === '') {
			$value = $fallback;
		}

		return trim((string) $value);
	}

	/**
	 * Ensure a small block remains above the footer.
	 *
	 * The legal template is intentionally split over two explicit pages. This
	 * guard prevents a variable identity/address from entering the footer area.
	 *
	 * @param object $pdf PDF handler
	 * @param float $height Required height
	 * @return void
	 */
	private function ensureSpace($pdf, $height)
	{
		if ($pdf->GetY() + $height <= $this->contentBottom) {
			return;
		}

		if (is_object($this->currentOutputLangs)) {
			$this->writeFooter($pdf, $this->currentOutputLangs, $this->currentCompanyData);
		}
		$pdf->AddPage();
		if (is_object($this->currentOutputLangs)) {
			$this->writeDocumentHeader($pdf, $this->currentOutputLangs, $this->currentCompanyData);
		} else {
			$pdf->SetXY($this->marginLeft, $this->marginTop);
		}
	}

	/**
	 * Return a printable value or the native missing-value label.
	 *
	 * @param mixed $value Value
	 * @param Translate $outputlangs Output language
	 * @return string
	 */
	private function valueOrNotAvailable($value, $outputlangs)
	{
		$value = trim((string) $value);

		return $value !== '' ? $value : $outputlangs->transnoentitiesnoconv('NotAvailable');
	}

	/**
	 * Check that the four legal powers were granted.
	 *
	 * @param array<string,mixed> $data Mandate data
	 * @return bool
	 */
	private function allPowersAreGranted(array $data)
	{
		foreach (array(
			'mandate_power_sign_contracts',
			'mandate_power_pay_connection_costs',
			'mandate_power_execute_l342_6',
			'mandate_power_sign_access_contract',
		) as $key) {
			if (empty($data[$key])) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Create PDF instance.
	 *
	 * @return object|null
	 */
	private function createPdfInstance()
	{
		if (function_exists('pdf_getInstance')) {
			return pdf_getInstance($this->format);
		}
		if (class_exists('TCPDF')) {
			return new TCPDF('P', 'mm', 'A4');
		}

		return null;
	}

	/**
	 * Convert text to PDF charset.
	 *
	 * @param Translate $outputlangs Output language
	 * @param string $text Text
	 * @return string
	 */
	private function toPdf($outputlangs, $text)
	{
		return $outputlangs->convToOutputCharset((string) $text);
	}

	/**
	 * Return image type for TCPDF.
	 *
	 * @param string $path Image path
	 * @return string
	 */
	private function getImageType($path)
	{
		$extension = strtoupper((string) pathinfo($path, PATHINFO_EXTENSION));

		return $extension === 'JPEG' ? 'JPG' : $extension;
	}

	/**
	 * Extract PNG binary data from a data URL.
	 *
	 * @param string $dataUrl Data URL
	 * @return string
	 */
	private function extractPngData($dataUrl)
	{
		if (strpos($dataUrl, 'data:image/png;base64,') !== 0) {
			return '';
		}
		$payload = substr($dataUrl, strlen('data:image/png;base64,'));
		$decoded = base64_decode($payload, true);

		return is_string($decoded) ? $decoded : '';
	}
}
