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
	 * Write signed mandate PDF.
	 *
	 * @param Raccordement $object Raccordement
	 * @param Translate $outputlangs Output language
	 * @param string $outputDir Output directory
	 * @param array{signataire_nom:string, signataire_fonction:string, signataire_email:string, signature_data_url:string, signature_ip:string, signature_user_agent:string} $data Signature data
	 * @return string Generated filename, empty string on error
	 */
	public function write_file($object, $outputlangs, $outputDir, array $data)
	{
		global $conf;

		require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
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

		$pdf->SetCreator('Dolibarr');
		$pdf->SetAuthor('Procedures PV');
		$pdf->SetTitle($outputlangs->convToOutputCharset($outputlangs->transnoentitiesnoconv('MandatEnedisSignedPdfTitle')));
		$pdf->SetMargins(15, 15, 15);
		$pdf->SetAutoPageBreak(true, 22);
		$pdf->AddPage();
		$pdf->SetFont('helvetica', 'B', 15);
		$pdf->MultiCell(0, 8, $outputlangs->convToOutputCharset($outputlangs->transnoentitiesnoconv('MandatEnedisSignedPdfTitle')), 0, 'L');
		$pdf->Ln(4);

		$pdf->SetFont('helvetica', '', 10);
		$lines = array(
			$outputlangs->transnoentitiesnoconv('Ref').': '.$object->ref,
			$outputlangs->transnoentitiesnoconv('SiteName').': '.$object->site_name_snapshot,
			$outputlangs->transnoentitiesnoconv('Address').': '.$object->site_address_snapshot,
			$outputlangs->transnoentitiesnoconv('Zip').': '.$object->site_zip_snapshot,
			$outputlangs->transnoentitiesnoconv('Town').': '.$object->site_town_snapshot,
			$outputlangs->transnoentitiesnoconv('PRM').': '.$object->prm,
			$outputlangs->transnoentitiesnoconv('ExploitationType').': '.$object->type_exploitation,
			$outputlangs->transnoentitiesnoconv('InstalledPowerKwc').': '.$object->puissance_installee_kwc,
			$outputlangs->transnoentitiesnoconv('InjectionPowerKva').': '.$object->puissance_injection_kva,
		);

		foreach ($lines as $line) {
			$pdf->MultiCell(0, 6, $outputlangs->convToOutputCharset((string) $line), 0, 'L');
		}

		$pdf->Ln(4);
		$pdf->MultiCell(0, 6, $outputlangs->convToOutputCharset($outputlangs->transnoentitiesnoconv('MandatEnedisPdfBody')), 0, 'L');
		$pdf->Ln(5);

		$pdf->SetFont('helvetica', 'B', 11);
		$pdf->MultiCell(0, 7, $outputlangs->convToOutputCharset($outputlangs->transnoentitiesnoconv('Signer')), 0, 'L');
		$pdf->SetFont('helvetica', '', 10);
		$pdf->MultiCell(0, 6, $outputlangs->convToOutputCharset($outputlangs->transnoentitiesnoconv('Name').': '.$data['signataire_nom']), 0, 'L');
		$pdf->MultiCell(0, 6, $outputlangs->convToOutputCharset($outputlangs->transnoentitiesnoconv('Function').': '.$data['signataire_fonction']), 0, 'L');
		$pdf->MultiCell(0, 6, $outputlangs->convToOutputCharset($outputlangs->transnoentitiesnoconv('Email').': '.$data['signataire_email']), 0, 'L');
		$pdf->MultiCell(0, 6, $outputlangs->convToOutputCharset($outputlangs->transnoentitiesnoconv('SignatureDate').': '.dol_print_date(dol_now(), 'dayhour')), 0, 'L');
		$pdf->Ln(4);

		$signatureBinary = $this->extractPngData($data['signature_data_url']);
		if ($signatureBinary !== '') {
			$pdf->Image('@'.$signatureBinary, '', '', 70, 28, 'PNG');
		}

		$pdf->Ln(32);
		$pdf->SetFont('helvetica', '', 8);
		$pdf->MultiCell(0, 5, $outputlangs->convToOutputCharset($outputlangs->transnoentitiesnoconv('MandatEnedisPdfTrace', $data['signature_ip'], dol_trunc($data['signature_user_agent'], 120))), 0, 'L');

		$pdf->Output($outputFile, 'F');

		if (!is_readable($outputFile)) {
			$this->error = 'ErrorPdfNotGenerated';
			return '';
		}

		return $filename;
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

