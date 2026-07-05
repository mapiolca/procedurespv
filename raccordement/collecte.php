<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

require '../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once dol_buildpath('/procedurespv/class/raccordement.class.php', 0);
require_once dol_buildpath('/procedurespv/class/publiclink.class.php', 0);
require_once dol_buildpath('/procedurespv/class/piece.class.php', 0);
require_once dol_buildpath('/procedurespv/class/signature.class.php', 0);
require_once dol_buildpath('/procedurespv/lib/procedurespv.lib.php', 0);

$langs->loadLangs(array('companies', 'procedurespv@procedurespv'));

$id = GETPOSTINT('id');
$action = GETPOST('action', 'aZ09');

if (!isModEnabled('procedurespv')) {
	accessforbidden();
}

$object = new Raccordement($db);
$result = $object->fetch($id);
if ($result <= 0) {
	accessforbidden($langs->trans('ErrorRecordNotFound'));
}

$permissiontoread = procedurespvCanDo($user, 'raccordement', 'read');
$permissiontosend = procedurespvCanDo($user, 'raccordement', 'send_collecte');
$permissiontovalidate = procedurespvCanDo($user, 'raccordement', 'validate_collecte');
$permissiontovalidatemandat = procedurespvCanDo($user, 'raccordement', 'validate_mandat');
if (!$permissiontoread) {
	accessforbidden();
}

$generatedPublicUrl = '';
$publicLink = new PublicLink($db);

$pieceActions = array('validate_piece', 'refuse_piece');
$signatureActions = array('validate_mandat', 'refuse_mandat');
if (($action === 'generate_link' || $action === 'revoke_link' || in_array($action, $pieceActions, true) || in_array($action, $signatureActions, true)) && !GETPOST('token', 'alpha')) {
	accessforbidden($langs->trans('ErrorBadToken'));
}

if ($action === 'generate_link') {
	if (!$permissiontosend) {
		accessforbidden();
	}

	$email = GETPOST('email_destinataire', 'restricthtml');
	$validityDays = getDolGlobalInt('PROCEDURESPV_PUBLICLINK_VALIDITY_DAYS', 30);
	$rawToken = $publicLink->createForRaccordement($object, PublicLink::TYPE_COLLECTE_RACCORDEMENT, $email, $validityDays);
	if ($rawToken !== '') {
		$generatedPublicUrl = $publicLink->getPublicUrl($rawToken);
		setEventMessages($langs->trans('PublicLinkGenerated'), null, 'mesgs');
	} else {
		setEventMessages($publicLink->error, $publicLink->errors, 'errors');
	}
}

if ($action === 'revoke_link') {
	if (!$permissiontosend) {
		accessforbidden();
	}

	$linkid = GETPOSTINT('linkid');
	$result = $publicLink->fetchLatestForRaccordement((int) $object->id, PublicLink::TYPE_COLLECTE_RACCORDEMENT);
	if ($result > 0 && (int) $publicLink->id === $linkid) {
		$result = $publicLink->revoke();
		if ($result > 0) {
			setEventMessages($langs->trans('PublicLinkRevoked'), null, 'mesgs');
		} else {
			setEventMessages($publicLink->error, $publicLink->errors, 'errors');
		}
	}
}

if (in_array($action, $pieceActions, true)) {
	if (!$permissiontovalidate) {
		accessforbidden();
	}

	$piece = new Piece($db);
	$result = $piece->fetch(GETPOSTINT('pieceid'));
	if ($result > 0 && (int) $piece->fk_raccordement === (int) $object->id) {
		$newStatus = $action === 'validate_piece' ? Piece::STATUS_VALIDATED : Piece::STATUS_NON_COMPLIANT;
		$motif = GETPOST('motif_refus', 'restricthtml');
		$result = $piece->setValidationStatus($newStatus, $user, $motif);
		if ($result > 0) {
			setEventMessages($langs->trans($action === 'validate_piece' ? 'PieceValidated' : 'PieceRefused'), null, 'mesgs');
		} else {
			setEventMessages($piece->error, $piece->errors, 'errors');
		}
	}
}

if (in_array($action, $signatureActions, true)) {
	if (!$permissiontovalidatemandat) {
		accessforbidden();
	}

	$signature = new Signature($db);
	$result = $signature->fetch(GETPOSTINT('signatureid'));
	if ($result > 0 && (int) $signature->fk_raccordement === (int) $object->id) {
		$newStatus = $action === 'validate_mandat' ? Signature::STATUS_VALIDATED : Signature::STATUS_NON_COMPLIANT;
		$motif = GETPOST('motif_non_conformite', 'restricthtml');
		$result = $signature->setValidationStatus($newStatus, $user, $motif);
		if ($result > 0) {
			if ($newStatus === Signature::STATUS_VALIDATED) {
				$object->date_mandat_validation = dol_now();
				$object->context['trigger_reason'] = 'mandat_validation';
				$object->context['changed_fields'] = array('date_mandat_validation');
				$object->update($user);
			}
			setEventMessages($langs->trans($action === 'validate_mandat' ? 'MandatValidated' : 'MandatRefused'), null, 'mesgs');
		} else {
			setEventMessages($signature->error, $signature->errors, 'errors');
		}
	}
}

$latestLink = new PublicLink($db);
$latestLink->fetchLatestForRaccordement((int) $object->id, PublicLink::TYPE_COLLECTE_RACCORDEMENT);
$pieceFetcher = new Piece($db);
$pieces = $pieceFetcher->fetchAllByRaccordement((int) $object->id);
$latestSignature = new Signature($db);
$latestSignature->fetchLatestForRaccordement((int) $object->id, Signature::TYPE_MANDAT_ENEDIS);

llxHeader('', $langs->trans('CollecteClient'), '', '', 0, 0, '', '', '', 'mod-procedurespv page-raccordement-collecte');

$head = procedurespvRaccordementPrepareHead($object);
print dol_get_fiche_head($head, 'collecte', $langs->trans('Raccordement'), -1, $object->picto);

$linkback = '<a href="'.dol_buildpath('/procedurespv/raccordement/list.php', 1).'">'.$langs->trans('BackToList').'</a>';
dol_banner_tab($object, 'ref', $linkback, 1, 'ref', 'ref');

print '<div class="fichecenter">';
print '<div class="fichehalfleft">';
print '<table class="border centpercent">';
print '<tr><td class="titlefield">'.$langs->trans('Status').'</td><td>'.$object->getLibStatut(5).'</td></tr>';
print '<tr><td>'.$langs->trans('CollecteSentDate').'</td><td>'.(!empty($object->date_collecte_envoi) ? dol_print_date((int) $object->date_collecte_envoi, 'dayhour') : '').'</td></tr>';
print '<tr><td>'.$langs->trans('CollecteOpenedDate').'</td><td>'.(!empty($object->date_collecte_ouverture) ? dol_print_date((int) $object->date_collecte_ouverture, 'dayhour') : '').'</td></tr>';
print '<tr><td>'.$langs->trans('CollecteSubmittedDate').'</td><td>'.(!empty($object->date_collecte_soumission) ? dol_print_date((int) $object->date_collecte_soumission, 'dayhour') : '').'</td></tr>';
print '</table>';
print '</div>';

print '<div class="fichehalfright">';
print '<table class="border centpercent">';
print '<tr class="liste_titre"><td colspan="2">'.$langs->trans('LatestPublicLink').'</td></tr>';
if ((int) $latestLink->id > 0) {
	$linkStatusKey = (int) $latestLink->status < 0 ? 'PublicLinkStatusRevoked' : 'PublicLinkStatus'.((int) $latestLink->status);
	print '<tr><td class="titlefield">'.$langs->trans('Status').'</td><td>'.$langs->trans($linkStatusKey).'</td></tr>';
	print '<tr><td>'.$langs->trans('Email').'</td><td>'.dol_escape_htmltag((string) $latestLink->email_destinataire).'</td></tr>';
	print '<tr><td>'.$langs->trans('ExpirationDate').'</td><td>'.(!empty($latestLink->date_expiration) ? dol_print_date((int) $latestLink->date_expiration, 'dayhour') : '').'</td></tr>';
	print '<tr><td>'.$langs->trans('AccessCount').'</td><td>'.((int) $latestLink->nb_access).'</td></tr>';
	print '<tr><td>'.$langs->trans('LastAccess').'</td><td>'.(!empty($latestLink->date_last_access) ? dol_print_date((int) $latestLink->date_last_access, 'dayhour') : '').'</td></tr>';
} else {
	print '<tr class="oddeven"><td colspan="2"><span class="opacitymedium">'.$langs->trans('NoRecordFound').'</span></td></tr>';
}
print '</table>';
print '</div>';
print '</div>';

if ($generatedPublicUrl !== '') {
	print '<br>';
	print '<div class="info">';
	print $langs->trans('GeneratedPublicLinkWarning').'<br>';
	print '<input type="text" class="flat centpercent" readonly value="'.dol_escape_htmltag($generatedPublicUrl).'">';
	print '</div>';
}

if ($permissiontosend) {
	print '<br>';
	print '<form method="POST" action="'.dol_escape_htmltag($_SERVER['PHP_SELF']).'?id='.(int) $object->id.'">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="generate_link">';
	print '<table class="border centpercent">';
	print '<tr><td class="titlefield">'.$langs->trans('RecipientEmail').'</td><td><input type="email" class="flat minwidth300" name="email_destinataire" value="'.dol_escape_htmltag((string) $latestLink->email_destinataire).'"></td></tr>';
	print '</table>';
	print '<div class="center"><input type="submit" class="button" value="'.$langs->trans('GeneratePublicLink').'"></div>';
	print '</form>';

	if ((int) $latestLink->id > 0 && (int) $latestLink->status === PublicLink::STATUS_ACTIVE) {
		print '<div class="tabsAction">';
		print '<a class="butActionDelete" href="'.dol_buildpath('/procedurespv/raccordement/collecte.php', 1).'?id='.(int) $object->id.'&action=revoke_link&linkid='.(int) $latestLink->id.'&token='.newToken().'">'.$langs->trans('RevokePublicLink').'</a>';
		print '</div>';
	}
}

print '<br>';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<td>'.$langs->trans('Piece').'</td>';
print '<td>'.$langs->trans('Origin').'</td>';
print '<td class="center">'.$langs->trans('Status').'</td>';
print '<td>'.$langs->trans('File').'</td>';
print '<td class="center">'.$langs->trans('Action').'</td>';
print '</tr>';
if (!empty($pieces)) {
	foreach ($pieces as $piece) {
		print '<tr class="oddeven">';
		print '<td>'.dol_escape_htmltag((string) $piece->label).'</td>';
		print '<td>'.dol_escape_htmltag((string) $piece->origin).'</td>';
		print '<td class="center"><span class="badge">'.$langs->trans($piece->getStatusLabelKey()).'</span></td>';
		print '<td>'.dol_escape_htmltag((string) $piece->filename).'</td>';
		print '<td class="center">';
		if ($permissiontovalidate) {
			print '<a class="button small" href="'.dol_buildpath('/procedurespv/raccordement/collecte.php', 1).'?id='.(int) $object->id.'&action=validate_piece&pieceid='.(int) $piece->id.'&token='.newToken().'">'.$langs->trans('Validate').'</a> ';
			print '<a class="button small" href="'.dol_buildpath('/procedurespv/raccordement/collecte.php', 1).'?id='.(int) $object->id.'&action=refuse_piece&pieceid='.(int) $piece->id.'&token='.newToken().'">'.$langs->trans('Refuse').'</a>';
		}
		print '</td>';
		print '</tr>';
	}
} else {
	print '<tr class="oddeven"><td colspan="5"><span class="opacitymedium">'.$langs->trans('NoRecordFound').'</span></td></tr>';
}
print '</table>';

print '<br>';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<td>'.$langs->trans('MandatEnedis').'</td>';
print '<td class="center">'.$langs->trans('Status').'</td>';
print '<td>'.$langs->trans('Signer').'</td>';
print '<td>'.$langs->trans('SignatureDate').'</td>';
print '<td>'.$langs->trans('PdfHash').'</td>';
print '<td class="center">'.$langs->trans('Action').'</td>';
print '</tr>';
if ((int) $latestSignature->id > 0) {
	print '<tr class="oddeven">';
	print '<td>'.dol_escape_htmltag((string) $latestSignature->filename).'</td>';
	print '<td class="center"><span class="badge">'.$langs->trans($latestSignature->getStatusLabelKey()).'</span></td>';
	print '<td>'.dol_escape_htmltag((string) $latestSignature->signataire_nom).'<br><span class="opacitymedium">'.dol_escape_htmltag((string) $latestSignature->signataire_email).'</span></td>';
	print '<td>'.(!empty($latestSignature->signature_date) ? dol_print_date((int) $latestSignature->signature_date, 'dayhour') : '').'</td>';
	print '<td><span class="opacitymedium">'.dol_escape_htmltag((string) $latestSignature->pdf_hash).'</span></td>';
	print '<td class="center">';
	if ($permissiontovalidatemandat) {
		print '<a class="button small" href="'.dol_buildpath('/procedurespv/raccordement/collecte.php', 1).'?id='.(int) $object->id.'&action=validate_mandat&signatureid='.(int) $latestSignature->id.'&token='.newToken().'">'.$langs->trans('ValidateMandat').'</a> ';
		print '<a class="button small" href="'.dol_buildpath('/procedurespv/raccordement/collecte.php', 1).'?id='.(int) $object->id.'&action=refuse_mandat&signatureid='.(int) $latestSignature->id.'&token='.newToken().'">'.$langs->trans('RefuseMandat').'</a>';
	}
	print '</td>';
	print '</tr>';
} else {
	print '<tr class="oddeven"><td colspan="6"><span class="opacitymedium">'.$langs->trans('NoRecordFound').'</span></td></tr>';
}
print '</table>';

print dol_get_fiche_end();

llxFooter();
$db->close();
