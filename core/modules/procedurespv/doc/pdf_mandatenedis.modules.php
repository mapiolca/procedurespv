<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * Simple ENEDIS mandate PDF model.
 */
class pdf_mandatenedis
{
	/**
	 * Database handler.
	 *
	 * @var DoliDB
	 */
	public $db;

	/**
	 * Model name.
	 *
	 * @var string
	 */
	public $name = 'mandatenedis';

	/**
	 * Model description translation key.
	 *
	 * @var string
	 */
	public $description = 'MandatPdfModelDescription';

	/**
	 * Model version.
	 *
	 * @var string
	 */
	public $version = 'dolibarr';

	/**
	 * Output type.
	 *
	 * @var string
	 */
	public $type = 'pdf';

	/**
	 * Directory scanned by Dolibarr document model administration.
	 *
	 * @var string
	 */
	public $scandir = 'procedurespv/core/modules/procedurespv/doc';

	/**
	 * Native Dolibarr document model type.
	 *
	 * @var string
	 */
	public $document_model_type = 'procedurespv_mandatenedis';

	/**
	 * PDF page width.
	 *
	 * @var int
	 */
	public $page_largeur = 210;

	/**
	 * PDF page height.
	 *
	 * @var int
	 */
	public $page_hauteur = 297;

	/**
	 * PDF format.
	 *
	 * @var array{0:int,1:int}
	 */
	public $format = array(210, 297);

	/**
	 * Logo support flag.
	 *
	 * @var int
	 */
	public $option_logo = 0;

	/**
	 * Multilanguage support flag.
	 *
	 * @var int
	 */
	public $option_multilang = 1;

	/**
	 * Free text support flag.
	 *
	 * @var int
	 */
	public $option_freetext = 0;

	/**
	 * Draft watermark support flag.
	 *
	 * @var int
	 */
	public $option_draft_watermark = 0;

	/**
	 * Minimum PHP version.
	 *
	 * @var array{0:int,1:int}
	 */
	public $phpmin = array(8, 0);

	/**
	 * Error message.
	 *
	 * @var string
	 */
	public $error = '';

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
	 * Write signed mandate PDF.
	 *
	 * @param Raccordement $object Raccordement
	 * @param Translate $outputlangs Output language
	 * @param string $outputDir Output directory
	 * @param array{client_type:string, client_name:string, client_siret:string, client_email:string, client_phone:string, signataire_nom:string, signataire_fonction:string, signataire_email:string, signature_data_url:string, signature_ip:string, signature_user_agent:string} $data Signature data
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

		if ($outputDir === '') {
			$this->error = 'ErrorOutputDirectoryUnavailable';
			return '';
		}

		if (dol_mkdir($outputDir) < 0) {
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

		$outputlangs->loadLangs(array('main', 'procedurespv@procedurespv'));
		$entity = !empty($object->entity) ? (int) $object->entity : (int) $conf->entity;
		$companyData = $this->getCompanyData($entity);
		$stampPath = procedurespvGetMandatStampPath($entity);

		$pdf->SetCreator('Dolibarr');
		$pdf->SetAuthor('Procedures PV');
		$pdf->SetTitle($outputlangs->convToOutputCharset($outputlangs->transnoentitiesnoconv('MandatEnedisSignedPdfTitle')));
		$pdf->SetMargins(14, 14, 14);
		$pdf->SetAutoPageBreak(true, 18);
		$pdf->AddPage();

		$this->writeDocumentHeader($pdf, $outputlangs, $object);
		$this->writeParties($pdf, $outputlangs, $data, $companyData);
		$this->writeMandateSection($pdf, $outputlangs);
		$this->writeLocationSection($pdf, $outputlangs, $object);
		$this->writeSignatureSection($pdf, $outputlangs, $data, $companyData, $stampPath);
		$this->writeTrace($pdf, $outputlangs, $data);

		$pdf->Output($outputFile, 'F');

		if (!is_readable($outputFile)) {
			$this->error = 'ErrorPdfNotGenerated';
			return '';
		}

		return $filename;
	}

	/**
	 * Write document heading.
	 *
	 * @param object $pdf PDF handler
	 * @param Translate $outputlangs Output language
	 * @param Raccordement $object Raccordement
	 * @return void
	 */
	private function writeDocumentHeader($pdf, $outputlangs, $object)
	{
		$pdf->SetFont('helvetica', 'B', 14);
		$pdf->MultiCell(0, 7, $this->toPdf($outputlangs, $outputlangs->transnoentitiesnoconv('MandatEnedisOfficialTitle')), 0, 'C');
		$pdf->SetFont('helvetica', '', 8);
		$pdf->MultiCell(0, 5, $this->toPdf($outputlangs, $outputlangs->transnoentitiesnoconv('MandatEnedisOfficialReference')), 0, 'C');
		$pdf->Ln(2);
		$this->writeKeyValue($pdf, $outputlangs, $outputlangs->transnoentitiesnoconv('Ref'), (string) $object->ref);
		$pdf->Ln(2);
	}

	/**
	 * Write involved parties.
	 *
	 * @param object $pdf PDF handler
	 * @param Translate $outputlangs Output language
	 * @param array<string,string> $data Signature data
	 * @param array<string,string> $companyData Company data
	 * @return void
	 */
	private function writeParties($pdf, $outputlangs, array $data, array $companyData)
	{
		$this->writeSectionTitle($pdf, $outputlangs, '1 - '.$outputlangs->transnoentitiesnoconv('MandatEnedisParties'));
		$this->writeSubSectionTitle($pdf, $outputlangs, $outputlangs->transnoentitiesnoconv('MandatEnedisMandant'));
		$this->writeKeyValue($pdf, $outputlangs, $outputlangs->transnoentitiesnoconv('ClientType'), $this->getClientTypeLabel($data['client_type'], $outputlangs));
		$this->writeKeyValue($pdf, $outputlangs, $outputlangs->transnoentitiesnoconv('NameOrCompany'), $data['client_name']);
		if ($data['client_siret'] !== '') {
			$this->writeKeyValue($pdf, $outputlangs, $outputlangs->transnoentitiesnoconv('BeneficiarySiret'), $data['client_siret']);
		}
		$this->writeKeyValue($pdf, $outputlangs, $outputlangs->transnoentitiesnoconv('SignerName'), $data['signataire_nom']);
		$this->writeKeyValue($pdf, $outputlangs, $outputlangs->transnoentitiesnoconv('SignerFunction'), $data['signataire_fonction']);
		$this->writeKeyValue($pdf, $outputlangs, $outputlangs->transnoentitiesnoconv('Email'), $data['client_email']);
		if ($data['client_phone'] !== '') {
			$this->writeKeyValue($pdf, $outputlangs, $outputlangs->transnoentitiesnoconv('Phone'), $data['client_phone']);
		}

		$pdf->Ln(2);
		$this->writeSubSectionTitle($pdf, $outputlangs, $outputlangs->transnoentitiesnoconv('MandatEnedisMandataire'));
		$this->writeKeyValue($pdf, $outputlangs, $outputlangs->transnoentitiesnoconv('NameOrCompany'), $companyData['name']);
		if ($companyData['address'] !== '') {
			$this->writeKeyValue($pdf, $outputlangs, $outputlangs->transnoentitiesnoconv('Address'), $companyData['address']);
		}
		if ($companyData['siret'] !== '') {
			$this->writeKeyValue($pdf, $outputlangs, $outputlangs->transnoentitiesnoconv('BeneficiarySiret'), $companyData['siret']);
		}
		if ($companyData['managers'] !== '') {
			$this->writeKeyValue($pdf, $outputlangs, $outputlangs->transnoentitiesnoconv('MandatEnedisRepresentedBy'), $companyData['managers']);
		}
		$pdf->Ln(2);
	}

	/**
	 * Write mandate scope.
	 *
	 * @param object $pdf PDF handler
	 * @param Translate $outputlangs Output language
	 * @return void
	 */
	private function writeMandateSection($pdf, $outputlangs)
	{
		$this->writeSectionTitle($pdf, $outputlangs, '2 - '.$outputlangs->transnoentitiesnoconv('MandatEnedisMandate'));
		$this->writeParagraph($pdf, $outputlangs, $outputlangs->transnoentitiesnoconv('MandatEnedisSpecialMandateText'));
		$this->writeParagraph($pdf, $outputlangs, $outputlangs->transnoentitiesnoconv('MandatEnedisContractDocumentsText'));
		$this->writeBullets($pdf, $outputlangs, array(
			$outputlangs->transnoentitiesnoconv('MandatEnedisPowerConfidentialInfo'),
			$outputlangs->transnoentitiesnoconv('MandatEnedisPowerSubmitAndFollow'),
			$outputlangs->transnoentitiesnoconv('MandatEnedisPowerSignDocuments'),
			$outputlangs->transnoentitiesnoconv('MandatEnedisPowerCardi'),
			$outputlangs->transnoentitiesnoconv('MandatEnedisPowerTerminate'),
		));
		$this->writeParagraph($pdf, $outputlangs, $outputlangs->transnoentitiesnoconv('MandatEnedisValidityText'));
	}

	/**
	 * Write site location.
	 *
	 * @param object $pdf PDF handler
	 * @param Translate $outputlangs Output language
	 * @param Raccordement $object Raccordement
	 * @return void
	 */
	private function writeLocationSection($pdf, $outputlangs, $object)
	{
		$this->writeSectionTitle($pdf, $outputlangs, '3 - '.$outputlangs->transnoentitiesnoconv('MandatEnedisLocation'));
		$this->writeKeyValue($pdf, $outputlangs, $outputlangs->transnoentitiesnoconv('SiteName'), (string) $object->site_name_snapshot);
		$this->writeKeyValue($pdf, $outputlangs, $outputlangs->transnoentitiesnoconv('Address'), (string) $object->site_address_snapshot);
		$this->writeKeyValue($pdf, $outputlangs, $outputlangs->transnoentitiesnoconv('Zip').'/'.$outputlangs->transnoentitiesnoconv('Town'), trim((string) $object->site_zip_snapshot.' '.(string) $object->site_town_snapshot));
		$this->writeKeyValue($pdf, $outputlangs, $outputlangs->transnoentitiesnoconv('PRM'), (string) $object->prm);
		$this->writeKeyValue($pdf, $outputlangs, $outputlangs->transnoentitiesnoconv('MandatEnedisConnectionNature'), $outputlangs->transnoentitiesnoconv('MandatEnedisProductionConnection'));
		$this->writeKeyValue($pdf, $outputlangs, $outputlangs->transnoentitiesnoconv('ExploitationType'), (string) $object->type_exploitation);
		$this->writeKeyValue($pdf, $outputlangs, $outputlangs->transnoentitiesnoconv('InstalledPowerKwc'), price((float) $object->puissance_installee_kwc, 0, $outputlangs, 0, 0, -1).' kWc');
		$this->writeKeyValue($pdf, $outputlangs, $outputlangs->transnoentitiesnoconv('InjectionPowerKva'), price((float) $object->puissance_injection_kva, 0, $outputlangs, 0, 0, -1).' kVA');
	}

	/**
	 * Write signature boxes.
	 *
	 * @param object $pdf PDF handler
	 * @param Translate $outputlangs Output language
	 * @param array<string,string> $data Signature data
	 * @param array<string,string> $companyData Company data
	 * @param string $stampPath Company stamp path
	 * @return void
	 */
	private function writeSignatureSection($pdf, $outputlangs, array $data, array $companyData, $stampPath)
	{
		$this->checkPageBreak($pdf, 70);
		$this->writeSectionTitle($pdf, $outputlangs, '4 - '.$outputlangs->transnoentitiesnoconv('Signatures'));
		$this->writeParagraph($pdf, $outputlangs, $outputlangs->transnoentitiesnoconv('MandatEnedisSignatureOriginals'));

		$left = $pdf->GetX();
		$top = $pdf->GetY() + 1;
		$pageWidth = $pdf->getPageWidth();
		$pdfMargins = method_exists($pdf, 'getMargins') ? $pdf->getMargins() : array();
		$rightMargin = (is_array($pdfMargins) && isset($pdfMargins['right'])) ? (float) $pdfMargins['right'] : 14.0;
		$availableWidth = $pageWidth - $left - $rightMargin;
		$columnGap = 8;
		$columnWidth = ($availableWidth - $columnGap) / 2;
		$boxHeight = 56;
		$rightX = $left + $columnWidth + $columnGap;

		$pdf->Rect($left, $top, $columnWidth, $boxHeight);
		$pdf->Rect($rightX, $top, $columnWidth, $boxHeight);

		$pdf->SetXY($left + 3, $top + 3);
		$pdf->SetFont('helvetica', 'B', 9);
		$pdf->MultiCell($columnWidth - 6, 5, $this->toPdf($outputlangs, $outputlangs->transnoentitiesnoconv('MandatEnedisMandant')), 0, 'L');
		$pdf->SetFont('helvetica', '', 8);
		$pdf->MultiCell($columnWidth - 6, 4, $this->toPdf($outputlangs, $outputlangs->transnoentitiesnoconv('Name').': '.$data['signataire_nom']), 0, 'L');
		$pdf->MultiCell($columnWidth - 6, 4, $this->toPdf($outputlangs, $outputlangs->transnoentitiesnoconv('Date').': '.dol_print_date(dol_now(), 'day')), 0, 'L');
		$signatureBinary = $this->extractPngData($data['signature_data_url']);
		if ($signatureBinary !== '') {
			$pdf->Image('@'.$signatureBinary, $left + 6, $top + 28, min(64, $columnWidth - 12), 20, 'PNG');
		}

		$pdf->SetXY($rightX + 3, $top + 3);
		$pdf->SetFont('helvetica', 'B', 9);
		$pdf->MultiCell($columnWidth - 6, 5, $this->toPdf($outputlangs, $outputlangs->transnoentitiesnoconv('MandatEnedisMandataire')), 0, 'L');
		$pdf->SetFont('helvetica', '', 8);
		$pdf->MultiCell($columnWidth - 6, 4, $this->toPdf($outputlangs, $companyData['name']), 0, 'L');
		$pdf->MultiCell($columnWidth - 6, 4, $this->toPdf($outputlangs, $outputlangs->transnoentitiesnoconv('Date').': '.dol_print_date(dol_now(), 'day')), 0, 'L');
		if ($stampPath !== '' && is_readable($stampPath)) {
			$pdf->Image($stampPath, $rightX + 6, $top + 26, min(52, $columnWidth - 12), 24, $this->getImageType($stampPath));
		}

		$pdf->SetY($top + $boxHeight + 3);
	}

	/**
	 * Write traceability text.
	 *
	 * @param object $pdf PDF handler
	 * @param Translate $outputlangs Output language
	 * @param array<string,string> $data Signature data
	 * @return void
	 */
	private function writeTrace($pdf, $outputlangs, array $data)
	{
		$this->checkPageBreak($pdf, 20);
		$pdf->SetFont('helvetica', '', 7);
		$pdf->MultiCell(0, 4, $this->toPdf($outputlangs, $outputlangs->transnoentitiesnoconv('MandatEnedisPdfTrace', $data['signature_ip'], dol_trunc($data['signature_user_agent'], 120))), 0, 'L');
	}

	/**
	 * Write a section title.
	 *
	 * @param object $pdf PDF handler
	 * @param Translate $outputlangs Output language
	 * @param string $title Title
	 * @return void
	 */
	private function writeSectionTitle($pdf, $outputlangs, $title)
	{
		$this->checkPageBreak($pdf, 16);
		$pdf->Ln(2);
		$pdf->SetFont('helvetica', 'B', 11);
		$pdf->SetFillColor(238, 244, 247);
		$pdf->MultiCell(0, 7, $this->toPdf($outputlangs, $title), 0, 'L', true);
		$pdf->Ln(1);
	}

	/**
	 * Write a subsection title.
	 *
	 * @param object $pdf PDF handler
	 * @param Translate $outputlangs Output language
	 * @param string $title Title
	 * @return void
	 */
	private function writeSubSectionTitle($pdf, $outputlangs, $title)
	{
		$pdf->SetFont('helvetica', 'B', 9);
		$pdf->MultiCell(0, 5, $this->toPdf($outputlangs, $title), 0, 'L');
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
		$this->checkPageBreak($pdf, 18);
		$pdf->SetFont('helvetica', '', 8.5);
		$pdf->MultiCell(0, 4.5, $this->toPdf($outputlangs, $text), 0, 'L');
		$pdf->Ln(1);
	}

	/**
	 * Write bullet list.
	 *
	 * @param object $pdf PDF handler
	 * @param Translate $outputlangs Output language
	 * @param array<int,string> $items Items
	 * @return void
	 */
	private function writeBullets($pdf, $outputlangs, array $items)
	{
		$pdf->SetFont('helvetica', '', 8.5);
		foreach ($items as $item) {
			$this->checkPageBreak($pdf, 10);
			$pdf->MultiCell(0, 4.5, $this->toPdf($outputlangs, '- '.$item), 0, 'L');
		}
		$pdf->Ln(1);
	}

	/**
	 * Write key/value row.
	 *
	 * @param object $pdf PDF handler
	 * @param Translate $outputlangs Output language
	 * @param string $label Label
	 * @param string $value Value
	 * @return void
	 */
	private function writeKeyValue($pdf, $outputlangs, $label, $value)
	{
		$this->checkPageBreak($pdf, 9);
		$value = trim((string) $value);
		if ($value === '') {
			$value = $outputlangs->transnoentitiesnoconv('NotAvailable');
		}
		$x = $pdf->GetX();
		$y = $pdf->GetY();
		$labelWidth = 48;
		$pdf->SetFont('helvetica', 'B', 8.5);
		$pdf->MultiCell($labelWidth, 4.5, $this->toPdf($outputlangs, $label), 0, 'L');
		$pdf->SetXY($x + $labelWidth + 2, $y);
		$pdf->SetFont('helvetica', '', 8.5);
		$pdf->MultiCell(0, 4.5, $this->toPdf($outputlangs, $value), 0, 'L');
	}

	/**
	 * Return company data for the mandate.
	 *
	 * @param int $entity Entity id
	 * @return array{name:string,address:string,siret:string,managers:string}
	 */
	private function getCompanyData($entity)
	{
		global $conf, $mysoc;

		$isCurrentEntity = (int) $entity === (int) $conf->entity;
		$nameFallback = ($isCurrentEntity && is_object($mysoc) && !empty($mysoc->name)) ? (string) $mysoc->name : '';
		$addressFallback = ($isCurrentEntity && is_object($mysoc) && !empty($mysoc->address)) ? (string) $mysoc->address : '';
		$zipFallback = ($isCurrentEntity && is_object($mysoc) && !empty($mysoc->zip)) ? (string) $mysoc->zip : '';
		$townFallback = ($isCurrentEntity && is_object($mysoc) && !empty($mysoc->town)) ? (string) $mysoc->town : '';
		$siretFallback = ($isCurrentEntity && is_object($mysoc) && !empty($mysoc->idprof2)) ? (string) $mysoc->idprof2 : '';
		$managersFallback = ($isCurrentEntity && is_object($mysoc) && !empty($mysoc->managers)) ? (string) $mysoc->managers : '';

		$name = $this->getCompanyConst('MAIN_INFO_SOCIETE_NOM', $nameFallback, $entity);
		if ($name === '') {
			$name = $this->getCompanyConst('MAIN_INFO_SOCIETE_NAME', $nameFallback, $entity);
		}
		$address = $this->getCompanyConst('MAIN_INFO_SOCIETE_ADDRESS', $addressFallback, $entity);
		$zip = $this->getCompanyConst('MAIN_INFO_SOCIETE_ZIP', $zipFallback, $entity);
		$town = $this->getCompanyConst('MAIN_INFO_SOCIETE_TOWN', $townFallback, $entity);

		return array(
			'name' => $name,
			'address' => trim($address.' '.trim($zip.' '.$town)),
			'siret' => $this->getCompanyConst('MAIN_INFO_SIRET', $siretFallback, $entity),
			'managers' => $this->getCompanyConst('MAIN_INFO_SOCIETE_MANAGERS', $managersFallback, $entity),
		);
	}

	/**
	 * Return company constant for an entity.
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
	 * Return translated client type label.
	 *
	 * @param string $clientType Client type
	 * @param Translate $outputlangs Output language
	 * @return string
	 */
	private function getClientTypeLabel($clientType, $outputlangs)
	{
		$labels = array(
			'particulier' => 'ClientTypeIndividual',
			'societe' => 'ClientTypeCompany',
			'collectivite' => 'ClientTypePublicEntity',
			'association' => 'ClientTypeAssociation',
		);
		$key = isset($labels[$clientType]) ? $labels[$clientType] : 'Unknown';

		return $outputlangs->transnoentitiesnoconv($key);
	}

	/**
	 * Check page break.
	 *
	 * @param object $pdf PDF handler
	 * @param float $height Needed height
	 * @return void
	 */
	private function checkPageBreak($pdf, $height)
	{
		$bottom = method_exists($pdf, 'getBreakMargin') ? (float) $pdf->getBreakMargin() : 18.0;
		if ($pdf->GetY() + $height > $pdf->getPageHeight() - $bottom) {
			$pdf->AddPage();
		}
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
		if ($extension === 'JPEG') {
			return 'JPG';
		}

		return $extension;
	}

	/**
	 * Create PDF instance.
	 *
	 * @return object|null
	 */
	private function createPdfInstance()
	{
		if (function_exists('pdf_getInstance')) {
			return pdf_getInstance(array(210, 297));
		}

		if (class_exists('TCPDF')) {
			return new TCPDF('P', 'mm', 'A4');
		}

		return null;
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
