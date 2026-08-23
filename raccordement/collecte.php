<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

require '../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
require_once dol_buildpath('/procedurespv/class/raccordement.class.php', 0);
require_once dol_buildpath('/procedurespv/class/publiclink.class.php', 0);
require_once dol_buildpath('/procedurespv/class/piece.class.php', 0);
require_once dol_buildpath('/procedurespv/class/signature.class.php', 0);
require_once dol_buildpath('/procedurespv/class/collectionservice.class.php', 0);
require_once dol_buildpath('/procedurespv/class/centralepvadapter.class.php', 0);
require_once dol_buildpath('/procedurespv/lib/procedurespv.lib.php', 0);

$langs->loadLangs(array('companies', 'documents', 'procedurespv@procedurespv'));

$id = GETPOSTINT('id');
$action = GETPOST('action', 'aZ09');
$historyPage = max(0, GETPOSTINT('page'));
$historyLimit = GETPOSTINT('limit');
if ($historyLimit <= 0) {
	$historyLimit = 20;
}
$historyLimit = min($historyLimit, 5000);

if (!isModEnabled('procedurespv')) {
	accessforbidden();
}

$object = new Raccordement($db);
if ($object->fetch($id) <= 0) {
	accessforbidden($langs->trans('ErrorRecordNotFound'));
}

$permissiontoread = procedurespvCanDo($user, 'raccordement', 'read');
$permissiontowrite = procedurespvCanDo($user, 'raccordement', 'write');
$permissiontosend = procedurespvCanDo($user, 'raccordement', 'send_collecte');
$permissiontovalidate = procedurespvCanDo($user, 'raccordement', 'validate_collecte');
$permissiontovalidatemandat = procedurespvCanDo($user, 'raccordement', 'validate_mandat');
if (!$permissiontoread) {
	accessforbidden();
}

$mutatingActions = array('generate_link', 'revoke_link', 'upload_piece', 'validate_piece', 'refuse_piece', 'validate_mandat', 'refuse_mandat', 'validate_collection');
if (in_array($action, $mutatingActions, true) && (!GETPOST('token', 'alpha') || (function_exists('checkToken') && !checkToken()))) {
	accessforbidden($langs->trans('ErrorBadToken'));
}

$collectionService = new CollectionService($db);
$generatedPublicUrl = '';
$latestLink = new PublicLink($db);
$latestLink->fetchLatestForRaccordement((int) $object->id, PublicLink::TYPE_COLLECTE_RACCORDEMENT);

if ($action === 'generate_link') {
	if (!$permissiontosend || ((int) $latestLink->id > 0 && (int) $latestLink->status === PublicLink::STATUS_ACTIVE)) {
		accessforbidden($langs->trans('InvalidStatusTransition'));
	}
	$email = GETPOST('email_destinataire', 'restricthtml');
	$newLink = new PublicLink($db);
	$centralePVAdapter = new CentralePVAdapter($db);
	$db->begin();
	$centraleChangedFields = $centralePVAdapter->prefillRaccordementSiteData($object);
	$rawToken = $newLink->createForRaccordement($object, PublicLink::TYPE_COLLECTE_RACCORDEMENT, $email, getDolGlobalInt('PROCEDURESPV_PUBLICLINK_VALIDITY_DAYS', 30));
	if ($rawToken !== '') {
		$result = 1;
		$prefillPayload = array();
		$previousLink = new PublicLink($db);
		if ($previousLink->fetchLatestSubmittedForRaccordement((int) $object->id, (int) $newLink->id) > 0) {
			$prefillPayload = $previousLink->getPayloadArray();
			$result = $newLink->savePayload($prefillPayload);
		}
		if ($result > 0) {
			$result = $collectionService->prelistRevision($object, $newLink, $prefillPayload, $langs, false);
		}
		$object->date_collecte_envoi = dol_now();
		$object->status = 2;
		$object->context['trigger_reason'] = empty($prefillPayload) ? 'collection_link_created' : 'collection_reopened';
		$object->context['changed_fields'] = array_values(array_unique(array_merge(array('status', 'date_collecte_envoi'), $centraleChangedFields)));
		if ($result > 0) {
			$result = $object->update($user);
		}
		if ($result > 0) {
			$db->commit();
			$generatedPublicUrl = $newLink->getPublicUrl($rawToken);
			setEventMessages($langs->trans('PublicLinkGenerated'), null, 'mesgs');
			$latestLink = $newLink;
		} else {
			$db->rollback();
			$errorMessage = $collectionService->error !== '' ? $collectionService->error : ($newLink->error !== '' ? $newLink->error : $object->error);
			setEventMessages($errorMessage, !empty($object->errors) ? $object->errors : $collectionService->errors, 'errors');
		}
	} else {
		$db->rollback();
		setEventMessages($newLink->error, $newLink->errors, 'errors');
	}
}

if ($action === 'revoke_link') {
	if (!$permissiontosend || (int) $latestLink->id !== GETPOSTINT('linkid') || (int) $latestLink->status !== PublicLink::STATUS_ACTIVE) {
		accessforbidden($langs->trans('InvalidStatusTransition'));
	}
	$db->begin();
	$result = $latestLink->revoke();
	if ($result > 0) {
		$triggerResult = $object->triggerUserAction($user, 'collection_link_revoked', array('public_links'));
		if ($triggerResult < 0) {
			$result = -1;
		}
	}
	if ($result > 0) {
		$db->commit();
		setEventMessages($langs->trans('PublicLinkRevoked'), null, 'mesgs');
	} else {
		$db->rollback();
		setEventMessages($result < 0 && $object->error !== '' ? $object->error : $latestLink->error, !empty($object->errors) ? $object->errors : $latestLink->errors, 'errors');
	}
}

if ($action === 'upload_piece') {
	if (!$permissiontowrite || (int) $object->status >= 6) {
		accessforbidden();
	}
	$piece = new Piece($db);
	if ($piece->fetch(GETPOSTINT('pieceid')) <= 0 || (int) $piece->fk_raccordement !== (int) $object->id || (int) $piece->fk_publiclink !== (int) $latestLink->id || (int) $piece->status === Piece::STATUS_VALID) {
		accessforbidden($langs->trans('InvalidStatusTransition'));
	}
	$uploadResult = procedurespvStoreRaccordementUpload('piece_file', $object, (string) $piece->code_piece, (string) $piece->label, (string) $piece->origin, (int) $piece->required, (int) $piece->fk_publiclink);
	if ($uploadResult['result'] > 0) {
		$result = $object->triggerUserAction($user, 'collection_document_uploaded', array('pieces', 'documents'), (string) $piece->label);
		if ($result >= 0) {
			setEventMessages($langs->trans('FileUploaded'), null, 'mesgs');
		} else {
			setEventMessages($object->error, $object->errors, 'errors');
		}
	} else {
		setEventMessages($langs->trans($uploadResult['error']), null, 'errors');
	}
}

if ($action === 'validate_piece' || $action === 'refuse_piece') {
	if (!$permissiontovalidate || (int) $object->status >= 6) {
		accessforbidden();
	}
	$piece = new Piece($db);
	if ($piece->fetch(GETPOSTINT('pieceid')) <= 0 || (int) $piece->fk_raccordement !== (int) $object->id || (int) $piece->fk_publiclink !== (int) $latestLink->id || (int) $piece->status !== Piece::STATUS_TO_CONTROL) {
		accessforbidden($langs->trans('InvalidStatusTransition'));
	}
	$newStatus = $action === 'validate_piece' ? Piece::STATUS_VALID : Piece::STATUS_INVALID;
	$db->begin();
	$result = $piece->setValidationStatus($newStatus, $user, GETPOST('motif_refus', 'restricthtml'));
	if ($result > 0) {
		$triggerResult = $object->triggerUserAction(
			$user,
			$action === 'validate_piece' ? 'collection_document_validated' : 'collection_document_refused',
			array('pieces'),
			(string) $piece->label
		);
		if ($triggerResult < 0) {
			$result = -1;
		}
	}
	if ($result > 0) {
		$db->commit();
		setEventMessages($langs->trans($action === 'validate_piece' ? 'PieceValidated' : 'PieceRefused'), null, 'mesgs');
	} else {
		$db->rollback();
		setEventMessages($object->error !== '' ? $object->error : $piece->error, !empty($object->errors) ? $object->errors : $piece->errors, 'errors');
	}
}

if ($action === 'validate_mandat' || $action === 'refuse_mandat') {
	if (!$permissiontovalidatemandat || (int) $object->status >= 6) {
		accessforbidden();
	}
	$signature = new Signature($db);
	if ($signature->fetch(GETPOSTINT('signatureid')) <= 0 || (int) $signature->fk_raccordement !== (int) $object->id || (int) $signature->fk_publiclink !== (int) $latestLink->id || (int) $signature->status !== Signature::STATUS_TO_CONTROL) {
		accessforbidden($langs->trans('InvalidStatusTransition'));
	}
	$newStatus = $action === 'validate_mandat' ? Signature::STATUS_VALIDATED : Signature::STATUS_NON_COMPLIANT;
	$db->begin();
	$result = $signature->setValidationStatus($newStatus, $user, GETPOST('motif_non_conformite', 'restricthtml'));
	if ($result > 0) {
		if ($newStatus === Signature::STATUS_VALIDATED) {
			$object->date_mandat_validation = dol_now();
			$object->context['trigger_reason'] = 'mandate_validated';
			$object->context['changed_fields'] = array('date_mandat_validation');
			$result = $object->update($user);
		} else {
			$triggerResult = $object->triggerUserAction($user, 'mandate_refused', array('signatures'));
			if ($triggerResult < 0) {
				$result = -1;
			}
		}
	}
	if ($result > 0) {
		$db->commit();
		setEventMessages($langs->trans($action === 'validate_mandat' ? 'MandatValidated' : 'MandatRefused'), null, 'mesgs');
	} else {
		$db->rollback();
		setEventMessages($object->error !== '' ? $object->error : $signature->error, !empty($object->errors) ? $object->errors : $signature->errors, 'errors');
	}
}

if ($action === 'validate_collection') {
	if (!$permissiontovalidate || !in_array((int) $object->status, array(4, 5), true) || (int) $latestLink->status !== PublicLink::STATUS_SUBMITTED) {
		accessforbidden($langs->trans('InvalidStatusTransition'));
	}
	if (!$collectionService->canValidateCollection((int) $object->id, (int) $latestLink->id)) {
		setEventMessages($langs->trans($collectionService->error), null, 'errors');
	} else {
		$object->status = 6;
		$object->context['trigger_reason'] = 'collection_validated';
		$object->context['changed_fields'] = array('status');
		if ($object->update($user) > 0) {
			setEventMessages($langs->trans('CollecteValidated'), null, 'mesgs');
		} else {
			setEventMessages($object->error, $object->errors, 'errors');
		}
	}
}

// Reload current state after mutations.
$object->fetch($id);
$latestLink = new PublicLink($db);
$latestLink->fetchLatestForRaccordement((int) $object->id, PublicLink::TYPE_COLLECTE_RACCORDEMENT);
$historyFetcher = new PublicLink($db);
$historyTotal = $historyFetcher->countForRaccordement((int) $object->id, PublicLink::TYPE_COLLECTE_RACCORDEMENT);
$historyLinks = array();
$historyNum = 0;
if ($historyTotal >= 0) {
	$historyLastPage = $historyTotal > 0 ? (int) ceil($historyTotal / $historyLimit) - 1 : 0;
	$historyPage = min($historyPage, $historyLastPage);
	$historyLinks = $historyFetcher->fetchAllForRaccordement(
		(int) $object->id,
		PublicLink::TYPE_COLLECTE_RACCORDEMENT,
		$historyLimit + 1,
		$historyPage * $historyLimit
	);
	$historyNum = count($historyLinks);
	if ($historyFetcher->error !== '') {
		setEventMessages($historyFetcher->error, $historyFetcher->errors, 'errors');
	}
} else {
	setEventMessages($historyFetcher->error, $historyFetcher->errors, 'errors');
	$historyTotal = 0;
}
$pieceFetcher = new Piece($db);
$pieces = (int) $latestLink->id > 0 ? $pieceFetcher->fetchAllByRaccordement((int) $object->id, (int) $latestLink->id) : $pieceFetcher->fetchAllByRaccordement((int) $object->id);
$latestSignature = new Signature($db);
if ((int) $latestLink->id > 0) {
	$latestSignature->fetchForRevision((int) $object->id, (int) $latestLink->id);
} else {
	$latestSignature->fetchLatestForRaccordement((int) $object->id, Signature::TYPE_MANDAT_ENEDIS);
}

$pieceActionColumn = false;
if ((int) $object->status < 6) {
	foreach ($pieces as $piece) {
		if (($permissiontovalidate && (int) $piece->status === Piece::STATUS_TO_CONTROL) || ($permissiontowrite && (int) $piece->status !== Piece::STATUS_VALID)) {
			$pieceActionColumn = true;
			break;
		}
	}
}
$mandateActionColumn = (int) $object->status < 6 && $permissiontovalidatemandat && (int) $latestSignature->id > 0 && (int) $latestSignature->status === Signature::STATUS_TO_CONTROL;

llxHeader('', $langs->trans('CollecteClient'), '', '', 0, 0, '', '', '', 'classforhorizontalscrolloftabs mod-procedurespv page-raccordement-collecte');
$head = procedurespvRaccordementPrepareHead($object);
print dol_get_fiche_head($head, 'collecte', $langs->trans('Raccordement'), -1, $object->picto);
$linkback = '<a href="'.dol_buildpath('/procedurespv/raccordement/list.php', 1).'">'.$langs->trans('BackToList').'</a>';
dol_banner_tab($object, 'ref', $linkback, 1, 'ref', 'ref');

print '<div class="fichecenter">';
print '<div class="fichehalfleft"><div class="div-table-responsive-no-min"><table class="border centpercent">';
print '<tr><td>'.$langs->trans('CollecteSentDate').'</td><td>'.(!empty($object->date_collecte_envoi) ? dol_print_date((int) $object->date_collecte_envoi, 'dayhour') : '').'</td></tr>';
print '<tr><td>'.$langs->trans('CollecteOpenedDate').'</td><td>'.(!empty($object->date_collecte_ouverture) ? dol_print_date((int) $object->date_collecte_ouverture, 'dayhour') : '').'</td></tr>';
print '<tr><td>'.$langs->trans('CollecteSubmittedDate').'</td><td>'.(!empty($object->date_collecte_soumission) ? dol_print_date((int) $object->date_collecte_soumission, 'dayhour') : '').'</td></tr>';
print '</table></div></div>';
print '<div class="fichehalfright"><div class="div-table-responsive-no-min"><table class="border centpercent">';
print '<tr class="liste_titre"><td colspan="2">'.$langs->trans('LatestPublicLink').'</td></tr>';
if ((int) $latestLink->id > 0) {
	print '<tr><td class="titlefield">'.$langs->trans('Status').'</td><td>'.$latestLink->getLibStatut(5).'</td></tr>';
	print '<tr><td>'.$langs->trans('Email').'</td><td>'.dol_escape_htmltag((string) $latestLink->email_destinataire).'</td></tr>';
	print '<tr><td>'.$langs->trans('ExpirationDate').'</td><td>'.(!empty($latestLink->date_expiration) ? dol_print_date((int) $latestLink->date_expiration, 'dayhour') : '').'</td></tr>';
	print '<tr><td>'.$langs->trans('AccessCount').'</td><td>'.((int) $latestLink->nb_access).'</td></tr>';
	print '<tr><td>'.$langs->trans('LastAccess').'</td><td>'.(!empty($latestLink->date_last_access) ? dol_print_date((int) $latestLink->date_last_access, 'dayhour') : '').'</td></tr>';
} else {
	print '<tr class="oddeven"><td colspan="2"><span class="opacitymedium">'.$langs->trans('NoRecordFound').'</span></td></tr>';
}
print '</table></div></div><div class="clearboth"></div></div>';

$historyParam = '&id='.(int) $object->id.'&limit='.(int) $historyLimit;
print '<br><form method="GET" id="public-link-history-form" action="'.dol_escape_htmltag($_SERVER['PHP_SELF']).'">';
print '<input type="hidden" name="id" value="'.(int) $object->id.'">';
print_barre_liste($langs->trans('PublicLinkHistory'), $historyPage, $_SERVER['PHP_SELF'], $historyParam, '', '', '', $historyNum, $historyTotal, 'link', 0, '', '', $historyLimit, 0, 0, 0);
print '<div class="div-table-responsive"><table class="tagtable nobottomiftotal liste centpercent">';
print '<thead><tr class="liste_titre">';
print '<td>'.$langs->trans('PublicLinkGeneratedDate').'</td>';
print '<td>'.$langs->trans('Email').'</td>';
print '<td class="center">'.$langs->trans('Status').'</td>';
print '<td>'.$langs->trans('ExpirationDate').'</td>';
print '<td>'.$langs->trans('FirstAccess').'</td>';
print '<td>'.$langs->trans('LastAccess').'</td>';
print '<td class="center">'.$langs->trans('AccessCount').'</td>';
print '<td>'.$langs->trans('SubmissionDate').'</td>';
print '</tr></thead><tbody>';
if (!empty($historyLinks)) {
	$historyRow = 0;
	foreach ($historyLinks as $historyLink) {
		if ($historyRow >= $historyLimit) {
			break;
		}
		print '<tr class="oddeven">';
		print '<td>'.(!empty($historyLink->date_creation) ? dol_print_date((int) $historyLink->date_creation, 'dayhour') : '-').'</td>';
		print '<td>'.($historyLink->email_destinataire !== '' ? dol_escape_htmltag((string) $historyLink->email_destinataire) : '-').'</td>';
		print '<td class="center">'.$historyLink->getLibStatut(5).'</td>';
		print '<td>'.(!empty($historyLink->date_expiration) ? dol_print_date((int) $historyLink->date_expiration, 'dayhour') : '-').'</td>';
		print '<td>'.(!empty($historyLink->date_first_access) ? dol_print_date((int) $historyLink->date_first_access, 'dayhour') : '-').'</td>';
		print '<td>'.(!empty($historyLink->date_last_access) ? dol_print_date((int) $historyLink->date_last_access, 'dayhour') : '-').'</td>';
		print '<td class="center">'.((int) $historyLink->nb_access).'</td>';
		print '<td>'.(!empty($historyLink->date_submit) ? dol_print_date((int) $historyLink->date_submit, 'dayhour') : '-').'</td>';
		print '</tr>';
		$historyRow++;
	}
} else {
	print '<tr class="oddeven"><td colspan="8"><span class="opacitymedium">'.$langs->trans('NoRecordFound').'</span></td></tr>';
}
print '</tbody></table></div></form>';

print '<div class="clearboth"></div>';
print load_fiche_titre($langs->trans('PublicSectionPieces'), '', 'file-upload');
print '<div class="div-table-responsive"><table class="noborder centpercent">';
print '<tr class="liste_titre"><td>'.$langs->trans('Piece').'</td><td>'.$langs->trans('Origin').'</td><td class="center">'.$langs->trans('Status').'</td><td>'.$langs->trans('File').'</td>'.($pieceActionColumn ? '<td class="center">'.$langs->trans('Action').'</td>' : '').'</tr>';
if (!empty($pieces)) {
	foreach ($pieces as $piece) {
		$relativePath = $piece->filename !== '' ? procedurespvGetRaccordementDocumentRelativePath($object, (string) $piece->filename) : '';
		$previewData = $relativePath !== '' ? getAdvancedPreviewUrl('procedurespv', $relativePath, 1, '&entity='.(int) $object->entity) : array();
		$previewUrl = is_array($previewData) && !empty($previewData['url']) ? $previewData['url'] : ($relativePath !== '' ? procedurespvGetRaccordementDocumentUrl($object, (string) $piece->filename) : '');
		$previewMime = is_array($previewData) && !empty($previewData['mime']) ? (string) $previewData['mime'] : dol_mimetype((string) $piece->filename);
		print '<tr class="oddeven"><td>'.dol_escape_htmltag((string) $piece->label).'</td><td>'.dol_escape_htmltag(dol_ucfirst((string) $piece->origin)).'</td><td class="center">'.$piece->getLibStatut(5).'</td><td>';
		if ($piece->filename !== '') {
			print '<a class="documentpreview" mime="'.dol_escape_htmltag($previewMime).'" target="_blank" rel="noopener" href="'.dol_escape_htmltag($previewUrl).'">'.dol_escape_htmltag((string) $piece->filename).'</a> ';
			print '<button type="button" class="reposition pvproc-piece-preview" data-preview-url="'.dol_escape_htmltag($previewUrl).'" data-piece-id="'.(int) $piece->id.'" data-can-validate="'.(($permissiontovalidate && (int) $object->status < 6 && (int) $piece->status === Piece::STATUS_TO_CONTROL) ? '1' : '0').'" title="'.dol_escape_htmltag($langs->trans('Preview')).'">'.img_picto($langs->trans('Preview'), 'search').'</button> ';
			print '<a href="'.dol_escape_htmltag(procedurespvGetRaccordementDocumentUrl($object, (string) $piece->filename, true)).'">'.img_picto($langs->trans('Download'), 'download').'</a>';
		} else {
			print '<span class="opacitymedium">'.$langs->trans('NoFileFound').'</span>';
		}
		print '</td>';
		if ($pieceActionColumn) {
			print '<td class="center nowrap">';
			if ($permissiontovalidate && (int) $piece->status === Piece::STATUS_TO_CONTROL) {
				foreach (array('validate_piece' => 'Validate', 'refuse_piece' => 'Refuse') as $pieceAction => $labelKey) {
					print '<form class="inline-block" method="POST" action="'.dol_escape_htmltag($_SERVER['PHP_SELF']).'?id='.(int) $object->id.'"><input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="action" value="'.$pieceAction.'"><input type="hidden" name="pieceid" value="'.(int) $piece->id.'"><button class="button small" type="submit">'.$langs->trans($labelKey).'</button></form> ';
				}
			}
			if ($permissiontowrite && (int) $piece->status !== Piece::STATUS_VALID) {
				print '<form class="inline-block" method="POST" enctype="multipart/form-data" action="'.dol_escape_htmltag($_SERVER['PHP_SELF']).'?id='.(int) $object->id.'"><input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="action" value="upload_piece"><input type="hidden" name="pieceid" value="'.(int) $piece->id.'"><input type="file" name="piece_file" required><button class="button small" type="submit">'.$langs->trans('Upload').'</button></form>';
			}
			print '</td>';
		}
		print '</tr>';
	}
} else {
	print '<tr class="oddeven"><td colspan="'.($pieceActionColumn ? 5 : 4).'"><span class="opacitymedium">'.$langs->trans('NoRecordFound').'</span></td></tr>';
}
print '</table></div>';

print '<div id="pvproc-piece-preview-dialog" title="'.dol_escape_htmltag($langs->trans('DocumentPreview')).'" style="display:none">';
print '<iframe id="pvproc-piece-preview-frame" title="'.dol_escape_htmltag($langs->trans('DocumentPreview')).'" style="width:100%;height:65vh;border:0"></iframe>';
print '<div id="pvproc-piece-preview-actions" class="center" style="margin-top:10px">';
foreach (array('validate_piece' => 'Validate', 'refuse_piece' => 'Refuse') as $previewAction => $labelKey) {
	print '<form class="inline-block pvproc-preview-validation-form" method="POST" action="'.dol_escape_htmltag($_SERVER['PHP_SELF']).'?id='.(int) $object->id.'"><input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="action" value="'.$previewAction.'"><input class="pvproc-preview-piece-id" type="hidden" name="pieceid" value="0"><button class="button" type="submit">'.$langs->trans($labelKey).'</button></form> ';
}
print '</div></div>';
print '<script>
jQuery(function ($) {
	$(".pvproc-piece-preview").on("click", function () {
		var trigger = $(this);
		$("#pvproc-piece-preview-frame").attr("src", trigger.data("preview-url"));
		$(".pvproc-preview-piece-id").val(trigger.data("piece-id"));
		$(".pvproc-preview-validation-form").toggle(String(trigger.data("can-validate")) === "1");
		if ($.fn.dialog) {
			$("#pvproc-piece-preview-dialog").dialog({modal: true, width: Math.min(window.innerWidth * 0.9, 1100), height: Math.min(window.innerHeight * 0.9, 850)});
		} else {
			window.open(trigger.data("preview-url"), "_blank", "noopener");
		}
	});
});
</script>';

print '<br><div class="div-table-responsive"><table class="noborder centpercent">';
print '<tr class="liste_titre"><td>'.$langs->trans('MandatEnedis').'</td><td class="center">'.$langs->trans('Status').'</td><td>'.$langs->trans('Signer').'</td><td>'.$langs->trans('SignatureDate').'</td><td>'.$langs->trans('PdfHash').'</td>'.($mandateActionColumn ? '<td class="center">'.$langs->trans('Action').'</td>' : '').'</tr>';
if ((int) $latestSignature->id > 0) {
	$relativePath = procedurespvGetRaccordementDocumentRelativePath($object, (string) $latestSignature->filename);
	$previewData = getAdvancedPreviewUrl('procedurespv', $relativePath, 1, '&entity='.(int) $object->entity);
	$previewUrl = is_array($previewData) && !empty($previewData['url']) ? $previewData['url'] : procedurespvGetRaccordementDocumentUrl($object, (string) $latestSignature->filename);
	$previewMime = is_array($previewData) && !empty($previewData['mime']) ? (string) $previewData['mime'] : dol_mimetype((string) $latestSignature->filename);
	$pdfHash = (string) $latestSignature->pdf_hash;
	$pdfHashDisplay = dol_strlen($pdfHash) > 15 ? dol_substr($pdfHash, 0, 15).'...' : $pdfHash;
	print '<tr class="oddeven"><td><a class="documentpreview" mime="'.dol_escape_htmltag($previewMime).'" target="_blank" rel="noopener" href="'.dol_escape_htmltag($previewUrl).'">'.dol_escape_htmltag((string) $latestSignature->filename).'</a> <a href="'.dol_escape_htmltag(procedurespvGetRaccordementDocumentUrl($object, (string) $latestSignature->filename, true)).'">'.img_picto($langs->trans('Download'), 'download').'</a></td>';
	print '<td class="center">'.$latestSignature->getLibStatut(5).'</td><td>'.dol_escape_htmltag((string) $latestSignature->signataire_nom).'<br><span class="opacitymedium">'.dol_escape_htmltag((string) $latestSignature->signataire_email).'</span></td><td>'.(!empty($latestSignature->signature_date) ? dol_print_date((int) $latestSignature->signature_date, 'dayhour') : '').'</td><td><span class="opacitymedium" title="'.dol_escape_htmltag($pdfHash).'">'.dol_escape_htmltag($pdfHashDisplay).'</span></td>';
	if ($mandateActionColumn) {
		print '<td class="center nowrap">';
		foreach (array('validate_mandat' => 'ValidateMandat', 'refuse_mandat' => 'RefuseMandat') as $mandateAction => $labelKey) {
			print '<form class="inline-block" method="POST" action="'.dol_escape_htmltag($_SERVER['PHP_SELF']).'?id='.(int) $object->id.'"><input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="action" value="'.$mandateAction.'"><input type="hidden" name="signatureid" value="'.(int) $latestSignature->id.'"><button class="button small" type="submit">'.$langs->trans($labelKey).'</button></form> ';
		}
		print '</td>';
	}
	print '</tr>';
} else {
	print '<tr class="oddeven"><td colspan="'.($mandateActionColumn ? 6 : 5).'"><span class="opacitymedium">'.$langs->trans('NoRecordFound').'</span></td></tr>';
}
print '</table></div>';

// Public-link messages intentionally live below the tables so they cannot overlap them.
if ($generatedPublicUrl !== '') {
	print '<br><div class="info clearboth">'.$langs->trans('GeneratedPublicLinkWarning').'<br><input type="text" class="flat centpercent" readonly value="'.dol_escape_htmltag($generatedPublicUrl).'"></div>';
}

if ($permissiontosend && !((int) $latestLink->id > 0 && (int) $latestLink->status === PublicLink::STATUS_ACTIVE)) {
	print '<br><form method="POST" action="'.dol_escape_htmltag($_SERVER['PHP_SELF']).'?id='.(int) $object->id.'"><input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="action" value="generate_link"><table class="border centpercent"><tr><td class="titlefield">'.$langs->trans('RecipientEmail').'</td><td><input type="email" class="flat minwidth300" name="email_destinataire" value="'.dol_escape_htmltag((string) $latestLink->email_destinataire).'"></td></tr></table><div class="center"><input type="submit" class="button" value="'.$langs->trans((int) $latestLink->status === PublicLink::STATUS_SUBMITTED ? 'GenerateNewCollectionRevision' : 'GeneratePublicLink').'"></div></form>';
}

print '<div class="tabsAction">';
if ($permissiontosend && (int) $latestLink->id > 0 && (int) $latestLink->status === PublicLink::STATUS_ACTIVE) {
	print '<a class="butActionDelete" href="'.dol_buildpath('/procedurespv/raccordement/collecte.php', 1).'?id='.(int) $object->id.'&action=revoke_link&linkid='.(int) $latestLink->id.'&token='.newToken().'">'.$langs->trans('RevokePublicLink').'</a>';
}
if ($permissiontovalidate && in_array((int) $object->status, array(4, 5), true) && (int) $latestLink->status === PublicLink::STATUS_SUBMITTED && $collectionService->canValidateCollection((int) $object->id, (int) $latestLink->id)) {
	print '<a class="butAction" href="'.dol_buildpath('/procedurespv/raccordement/collecte.php', 1).'?id='.(int) $object->id.'&action=validate_collection&token='.newToken().'">'.$langs->trans('ValidateCollecte').'</a>';
}
print '</div>';

print dol_get_fiche_end();
llxFooter();
$db->close();
