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

$publicToken = GETPOST('public_token', 'alphanohtml');
if ($publicToken === '') {
	$publicToken = GETPOST('token', 'alphanohtml');
}
$action = GETPOST('action', 'aZ09');
$submissionDone = false;

if (!isModEnabled('procedurespv')) {
	$publicToken = '';
}

$publicLink = new PublicLink($db);
$linkLoaded = $publicToken !== '' ? $publicLink->fetchByToken($publicToken, PublicLink::TYPE_COLLECTE_RACCORDEMENT) : 0;
$linkUsable = $linkLoaded > 0 && $publicLink->isUsable();

$object = new Raccordement($db);
if ($linkUsable) {
	$result = $object->fetch((int) $publicLink->fk_raccordement, null, 0);
	if ($result <= 0 || (int) $object->entity !== (int) $publicLink->entity) {
		$linkUsable = false;
	}
}

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
	if (!GETPOST('token', 'alphanohtml') && !GETPOST('public_token', 'alphanohtml')) {
		accessforbidden($langs->trans('ErrorBadToken'));
	}

	$clientType = GETPOST('client_type', 'alphanohtml');
	$clientName = GETPOST('client_name', 'restricthtml');
	$clientEmail = GETPOST('client_email', 'restricthtml');
	$clientPhone = GETPOST('client_phone', 'alphanohtml');
	$siteName = GETPOST('site_name', 'restricthtml');
	$siteAddress = GETPOST('site_address', 'restricthtml');
	$siteZip = GETPOST('site_zip', 'alphanohtml');
	$siteTown = GETPOST('site_town', 'restricthtml');
	$prm = GETPOST('prm', 'alphanohtml');
	$typeReseau = GETPOST('type_reseau', 'alphanohtml');
	$typeExploitation = GETPOST('type_exploitation', 'alphanohtml');
	$puissanceInstallee = (float) price2num(GETPOST('puissance_installee_kwc', 'alphanohtml'));
	$puissanceInjection = (float) price2num(GETPOST('puissance_injection_kva', 'alphanohtml'));
	$signataireNom = GETPOST('signataire_nom', 'restricthtml');
	$signataireFonction = GETPOST('signataire_fonction', 'restricthtml');
	$signataireEmail = GETPOST('signataire_email', 'restricthtml');
	$mandatAcceptance = GETPOST('mandat_acceptance', 'alphanohtml');
	$signatureDataUrl = GETPOST('signature_data_url', 'restricthtml');
	$uploadErrors = array();
	$uploadedPieceId = 0;
	$signatureId = 0;

	$uploadedFile = null;
	if (isset($_FILES['piece_facture_electricite']) && is_array($_FILES['piece_facture_electricite'])) {
		$uploadedFile = $_FILES['piece_facture_electricite'];
	}

	if (is_array($uploadedFile) && !empty($uploadedFile['name'])) {
		$uploadErrorCode = isset($uploadedFile['error']) ? (int) $uploadedFile['error'] : UPLOAD_ERR_NO_FILE;
		$originalName = isset($uploadedFile['name']) ? (string) $uploadedFile['name'] : '';
		$tmpName = isset($uploadedFile['tmp_name']) ? (string) $uploadedFile['tmp_name'] : '';
		$fileSize = isset($uploadedFile['size']) ? (int) $uploadedFile['size'] : 0;
		$extension = strtolower((string) pathinfo($originalName, PATHINFO_EXTENSION));
		$allowedExtensions = array_filter(array_map('trim', explode(',', strtolower(getDolGlobalString('PROCEDURESPV_PUBLIC_UPLOAD_ALLOWED_EXTENSIONS', 'pdf,jpg,jpeg,png')))));
		$maxSize = getDolGlobalInt('PROCEDURESPV_PUBLIC_UPLOAD_MAX_SIZE', 10 * 1024 * 1024);

		if ($uploadErrorCode !== UPLOAD_ERR_OK) {
			$uploadErrors[] = $langs->trans('UploadError');
		}
		if ($fileSize <= 0 || $fileSize > $maxSize) {
			$uploadErrors[] = $langs->trans('UploadInvalidSize');
		}
		if ($extension === '' || !in_array($extension, $allowedExtensions, true)) {
			$uploadErrors[] = $langs->trans('UploadInvalidExtension');
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
			$uploadErrors[] = $langs->trans('UploadInvalidMime');
		}

		if (empty($uploadErrors)) {
			$uploadDir = procedurespvGetRaccordementUploadDir($object);
			if ($uploadDir === '' || dol_mkdir($uploadDir) < 0) {
				$uploadErrors[] = $langs->trans('UploadDirectoryUnavailable');
			} else {
				$storedFilename = 'facture_electricite_'.dol_print_date(dol_now(), '%Y%m%d%H%M%S').'_'.dol_sanitizeFileName($originalName);
				$destPath = $uploadDir.'/'.$storedFilename;
				$moveResult = dol_move_uploaded_file($tmpName, $destPath, 1, 0, $uploadErrorCode);
				if ($moveResult <= 0) {
					$uploadErrors[] = $langs->trans('UploadMoveFailed');
				} else {
					$piece = new Piece($db);
					$uploadedPieceId = $piece->createOrUpdateUploaded($object, 'facture_electricite', $langs->transnoentitiesnoconv('PieceFactureElectricite'), 'client', $uploadDir, $storedFilename, 1);
					if ($uploadedPieceId <= 0) {
						$uploadErrors[] = $piece->error;
					}
				}
			}
		}
	}

	$publicSummary = array(
		'client_type' => $clientType,
		'client_name' => $clientName,
		'client_email' => $clientEmail,
		'client_phone' => $clientPhone,
		'uploaded_piece_id' => $uploadedPieceId,
	);

	$object->site_name_snapshot = $siteName;
	$object->site_address_snapshot = $siteAddress;
	$object->site_zip_snapshot = $siteZip;
	$object->site_town_snapshot = $siteTown;
	$object->prm = $prm;
	$object->type_reseau = $typeReseau;
	$object->type_exploitation = $typeExploitation;
	$object->puissance_installee_kwc = $puissanceInstallee;
	$object->puissance_injection_kva = $puissanceInjection;
	$object->date_collecte_soumission = dol_now();
	$object->date_mandat_signature = dol_now();
	$object->status = 4;
	$object->context['trigger_reason'] = 'public_collecte_submitted';
	$object->context['changed_fields'] = array('status', 'date_collecte_soumission', 'date_mandat_signature', 'site_name_snapshot', 'site_address_snapshot', 'site_zip_snapshot', 'site_town_snapshot', 'prm', 'type_reseau', 'type_exploitation', 'puissance_installee_kwc', 'puissance_injection_kva');
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
				'signataire_nom' => $signataireNom,
				'signataire_fonction' => $signataireFonction,
				'signataire_email' => $signataireEmail,
				'signature_data_url' => $signatureDataUrl,
				'signature_ip' => $ip,
				'signature_user_agent' => $userAgent,
			));
			if ($pdfFilename === '') {
				$errorKey = !empty($pdfModel->error) ? (string) $pdfModel->error : 'ErrorPdfNotGenerated';
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
		$object->note_public = trim((string) $object->note_public."\n\n".$langs->trans('PublicCollecteSummary')."\n".json_encode($publicSummary));
		$result = $object->update($user);
		if ($result > 0) {
			$publicLink->markSubmitted();
			$linkUsable = false;
			$submissionDone = true;
			setEventMessages($langs->trans('PublicCollecteSubmitted'), null, 'mesgs');
		} else {
			setEventMessages($object->error, $object->errors, 'errors');
		}
	} else {
		setEventMessages('', $uploadErrors, 'errors');
	}
}

llxHeader('', $langs->trans('PublicCollecteTitle'), '', '', 0, 0, '', '', '', 'mod-procedurespv page-public-collecte');

print '<main class="public-procedurespv">';
print '<h1>'.$langs->trans('PublicCollecteTitle').'</h1>';

if ($submissionDone) {
	print '<div class="ok">'.$langs->trans('PublicCollecteSubmitted').'</div>';
	print '</main>';
	llxFooter('', 'public');
	$db->close();
	exit;
}

if (!$linkUsable) {
	print '<div class="warning">'.$langs->trans('PublicLinkUnavailable').'</div>';
	print '</main>';
	llxFooter('', 'public');
	$db->close();
	exit;
}

print '<p class="opacitymedium">'.$langs->trans('PublicCollecteIntro').'</p>';
print '<form method="POST" enctype="multipart/form-data" action="'.dol_escape_htmltag($_SERVER['PHP_SELF']).'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="public_token" value="'.dol_escape_htmltag($publicToken).'">';
print '<input type="hidden" name="action" value="submit_collecte">';

print '<h2>'.$langs->trans('PublicSectionClient').'</h2>';
print '<table class="border centpercent">';
print '<tr><td class="titlefield">'.$langs->trans('ClientType').'</td><td><select class="flat minwidth200" name="client_type" id="client_type">';
foreach (array('particulier' => 'ClientTypeIndividual', 'societe' => 'ClientTypeCompany', 'collectivite' => 'ClientTypePublicEntity', 'association' => 'ClientTypeAssociation') as $value => $labelKey) {
	print '<option value="'.dol_escape_htmltag($value).'">'.$langs->trans($labelKey).'</option>';
}
print '</select>'.ajax_combobox('client_type').'</td></tr>';
print '<tr><td>'.$langs->trans('NameOrCompany').'</td><td><input type="text" class="flat minwidth300" name="client_name"></td></tr>';
print '<tr><td>'.$langs->trans('Email').'</td><td><input type="email" class="flat minwidth300" name="client_email" value="'.dol_escape_htmltag((string) $publicLink->email_destinataire).'"></td></tr>';
print '<tr><td>'.$langs->trans('Phone').'</td><td><input type="text" class="flat minwidth200" name="client_phone"></td></tr>';
print '</table>';

print '<h2>'.$langs->trans('PublicSectionSite').'</h2>';
print '<table class="border centpercent">';
print '<tr><td class="titlefield">'.$langs->trans('SiteName').'</td><td><input type="text" class="flat minwidth300" name="site_name" value="'.dol_escape_htmltag((string) $object->site_name_snapshot).'"></td></tr>';
print '<tr><td>'.$langs->trans('Address').'</td><td><input type="text" class="flat minwidth500" name="site_address" value="'.dol_escape_htmltag((string) $object->site_address_snapshot).'"></td></tr>';
print '<tr><td>'.$langs->trans('Zip').'</td><td><input type="text" class="flat maxwidth100" name="site_zip" value="'.dol_escape_htmltag((string) $object->site_zip_snapshot).'"></td></tr>';
print '<tr><td>'.$langs->trans('Town').'</td><td><input type="text" class="flat minwidth300" name="site_town" value="'.dol_escape_htmltag((string) $object->site_town_snapshot).'"></td></tr>';
print '<tr><td>'.$langs->trans('PRM').'</td><td><input type="text" class="flat minwidth200" name="prm" value="'.dol_escape_htmltag((string) $object->prm).'"></td></tr>';
print '<tr><td>'.$langs->trans('NetworkType').'</td><td><select class="flat minwidth200" name="type_reseau" id="type_reseau">';
foreach (array('monophase' => 'NetworkMonophase', 'triphase' => 'NetworkTriphase', 'unknown' => 'Unknown') as $value => $labelKey) {
	print '<option value="'.dol_escape_htmltag($value).'"'.($object->type_reseau === $value ? ' selected' : '').'>'.$langs->trans($labelKey).'</option>';
}
print '</select>'.ajax_combobox('type_reseau').'</td></tr>';
print '</table>';

print '<h2>'.$langs->trans('PublicSectionProject').'</h2>';
print '<table class="border centpercent">';
print '<tr><td class="titlefield">'.$langs->trans('ExploitationType').'</td><td><select class="flat minwidth300" name="type_exploitation" id="type_exploitation">';
foreach (array('autoconsommation_totale' => 'ExploitationAutoconsommationTotale', 'autoconsommation_surplus' => 'ExploitationAutoconsommationSurplus', 'injection_totale' => 'ExploitationInjectionTotale', 'autoconsommation_collective' => 'ExploitationAutoconsommationCollective') as $value => $labelKey) {
	print '<option value="'.dol_escape_htmltag($value).'"'.($object->type_exploitation === $value ? ' selected' : '').'>'.$langs->trans($labelKey).'</option>';
}
print '</select>'.ajax_combobox('type_exploitation').'</td></tr>';
print '<tr><td>'.$langs->trans('InstalledPowerKwc').'</td><td><input type="text" class="flat width100 right" name="puissance_installee_kwc" value="'.dol_escape_htmltag((string) $object->puissance_installee_kwc).'"></td></tr>';
print '<tr><td>'.$langs->trans('InjectionPowerKva').'</td><td><input type="text" class="flat width100 right" name="puissance_injection_kva" value="'.dol_escape_htmltag((string) $object->puissance_injection_kva).'"></td></tr>';
print '</table>';

print '<h2>'.$langs->trans('PublicSectionPieces').'</h2>';
print '<table class="border centpercent">';
print '<tr><td class="titlefield">'.$langs->trans('PieceFactureElectricite').'</td><td><input type="file" class="flat" name="piece_facture_electricite"></td></tr>';
print '</table>';

print '<h2>'.$langs->trans('PublicSectionMandat').'</h2>';
print '<table class="border centpercent">';
print '<tr><td class="titlefield">'.$langs->trans('SignerName').'</td><td><input type="text" class="flat minwidth300" name="signataire_nom"></td></tr>';
print '<tr><td>'.$langs->trans('SignerFunction').'</td><td><input type="text" class="flat minwidth300" name="signataire_fonction"></td></tr>';
print '<tr><td>'.$langs->trans('SignerEmail').'</td><td><input type="email" class="flat minwidth300" name="signataire_email" value="'.dol_escape_htmltag((string) $publicLink->email_destinataire).'"></td></tr>';
print '<tr><td>'.$langs->trans('MandatAcceptance').'</td><td><select class="flat minwidth300" name="mandat_acceptance" id="mandat_acceptance"><option value="no">'.$langs->trans('No').'</option><option value="yes">'.$langs->trans('Yes').'</option></select>'.ajax_combobox('mandat_acceptance').'</td></tr>';
print '<tr><td>'.$langs->trans('Signature').'</td><td>';
print '<canvas id="mandat-signature-pad" width="520" height="180" style="border:1px solid #888;max-width:100%;touch-action:none;background:#fff"></canvas>';
print '<input type="hidden" name="signature_data_url" id="signature_data_url">';
print '<br><button type="button" class="button" id="clear-signature">'.$langs->trans('ClearSignature').'</button>';
print '</td></tr>';
print '</table>';

print '<script>
(function () {
	var canvas = document.getElementById("mandat-signature-pad");
	var hidden = document.getElementById("signature_data_url");
	var clearButton = document.getElementById("clear-signature");
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

print '<div class="center"><input type="submit" class="button button-save" value="'.$langs->trans('SubmitCollecte').'"></div>';
print '</form>';
print '</main>';

llxFooter('', 'public');
$db->close();
