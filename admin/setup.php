<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

require '../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once dol_buildpath('/procedurespv/lib/procedurespv.lib.php', 0);

$langs->loadLangs(array('admin', 'procedurespv@procedurespv'));

$action = GETPOST('action', 'aZ09');

if (!isModEnabled('procedurespv')) {
	accessforbidden();
}

$permissiontosetup = !empty($user->admin) || $user->hasRight('procedurespv', 'setup', 'write');
if (!$permissiontosetup) {
	accessforbidden();
}

if ($action === 'save') {
	if (!GETPOST('token', 'alpha')) {
		accessforbidden($langs->trans('ErrorBadToken'));
	}

	$error = 0;
	$intSettings = array(
		'PROCEDURESPV_PUBLICLINK_VALIDITY_DAYS',
		'PROCEDURESPV_RELANCE_COLLECTE_DAYS',
		'PROCEDURESPV_RELANCE_MANDAT_DAYS',
		'PROCEDURESPV_RELANCE_ENEDIS_IDLE_DAYS',
		'PROCEDURESPV_PUBLIC_UPLOAD_MAX_SIZE',
	);

	foreach ($intSettings as $constName) {
		$value = GETPOSTINT($constName);
		$result = dolibarr_set_const($db, $constName, (string) $value, 'chaine', 0, '', (int) $conf->entity);
		if ($result <= 0) {
			$error++;
		}
	}

	$stringSettings = array(
		'PROCEDURESPV_PUBLIC_UPLOAD_ALLOWED_EXTENSIONS',
		'PROCEDURESPV_EMAIL_TEMPLATE_COLLECTE',
		'PROCEDURESPV_EMAIL_TEMPLATE_RELANCE_COLLECTE',
		'PROCEDURESPV_EMAIL_TEMPLATE_RELANCE_MANDAT',
		'PROCEDURESPV_PDF_MODEL_MANDAT_ENEDIS',
	);

	foreach ($stringSettings as $constName) {
		$value = GETPOST($constName, 'alphanohtml');
		$result = dolibarr_set_const($db, $constName, $value, 'chaine', 0, '', (int) $conf->entity);
		if ($result <= 0) {
			$error++;
		}
	}

	if ($error) {
		setEventMessages($langs->trans('ErrorSetupNotSaved'), null, 'errors');
	} else {
		setEventMessages($langs->trans('SetupSaved'), null, 'mesgs');
	}
}

$defaultValidity = getDolGlobalInt('PROCEDURESPV_PUBLICLINK_VALIDITY_DAYS', 30);
$defaultCollecteDays = getDolGlobalInt('PROCEDURESPV_RELANCE_COLLECTE_DAYS', 7);
$defaultMandatDays = getDolGlobalInt('PROCEDURESPV_RELANCE_MANDAT_DAYS', 7);
$defaultEnedisDays = getDolGlobalInt('PROCEDURESPV_RELANCE_ENEDIS_IDLE_DAYS', 30);
$maxUploadSize = getDolGlobalInt('PROCEDURESPV_PUBLIC_UPLOAD_MAX_SIZE', 10 * 1024 * 1024);
$allowedExtensions = getDolGlobalString('PROCEDURESPV_PUBLIC_UPLOAD_ALLOWED_EXTENSIONS', 'pdf,jpg,jpeg,png');
$emailTemplateCollecte = getDolGlobalString('PROCEDURESPV_EMAIL_TEMPLATE_COLLECTE', '');
$emailTemplateRelanceCollecte = getDolGlobalString('PROCEDURESPV_EMAIL_TEMPLATE_RELANCE_COLLECTE', '');
$emailTemplateRelanceMandat = getDolGlobalString('PROCEDURESPV_EMAIL_TEMPLATE_RELANCE_MANDAT', '');
$pdfModelMandat = getDolGlobalString('PROCEDURESPV_PDF_MODEL_MANDAT_ENEDIS', 'mandatenedis');

llxHeader('', $langs->trans('ProceduresPVSetup'), '', '', 0, 0, '', '', '', 'mod-procedurespv page-admin');

$linkback = '<a href="'.DOL_URL_ROOT.'/admin/modules.php?search_keyword='.urlencode('procedurespv').'">'.$langs->trans('BackToModuleList').'</a>';
print load_fiche_titre($langs->trans('ProceduresPVSetup'), $linkback, 'title_setup');

$head = procedurespvAdminPrepareHead();
print dol_get_fiche_head($head, 'settings', $langs->trans('ProceduresPVSetup'), -1, 'fa-solar-panel');

print '<form method="POST" action="'.dol_escape_htmltag($_SERVER['PHP_SELF']).'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="save">';

print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><td>'.$langs->trans('Parameter').'</td><td>'.$langs->trans('Value').'</td></tr>';

print '<tr class="oddeven"><td>'.$langs->trans('UseCentralePVIfAvailable').'</td><td>';
print function_exists('ajax_constantonoff') ? ajax_constantonoff('PROCEDURESPV_USE_CENTRALEPV_IF_AVAILABLE') : yn(getDolGlobalInt('PROCEDURESPV_USE_CENTRALEPV_IF_AVAILABLE', 1));
print '</td></tr>';

print '<tr class="oddeven"><td>'.$langs->trans('AllowWithoutCentralePV').'</td><td>';
print function_exists('ajax_constantonoff') ? ajax_constantonoff('PROCEDURESPV_ALLOW_WITHOUT_CENTRALEPV') : yn(getDolGlobalInt('PROCEDURESPV_ALLOW_WITHOUT_CENTRALEPV', 1));
print '</td></tr>';

print '<tr class="oddeven"><td>'.$langs->trans('PrefillFromCentralePV').'</td><td>';
print function_exists('ajax_constantonoff') ? ajax_constantonoff('PROCEDURESPV_PREFILL_FROM_CENTRALEPV') : yn(getDolGlobalInt('PROCEDURESPV_PREFILL_FROM_CENTRALEPV', 1));
print '</td></tr>';

print '<tr class="oddeven"><td>'.$langs->trans('AutoFreezeOnEnedisDeposit').'</td><td>';
print function_exists('ajax_constantonoff') ? ajax_constantonoff('PROCEDURESPV_AUTO_FREEZE_ON_ENEDIS_DEPOSIT') : yn(getDolGlobalInt('PROCEDURESPV_AUTO_FREEZE_ON_ENEDIS_DEPOSIT', 1));
print '</td></tr>';

print '<tr class="oddeven"><td>'.$langs->trans('PublicLinkValidityDays').'</td><td><input type="number" class="flat width75" name="PROCEDURESPV_PUBLICLINK_VALIDITY_DAYS" value="'.((int) $defaultValidity).'"></td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('RelanceCollecteDays').'</td><td><input type="number" class="flat width75" name="PROCEDURESPV_RELANCE_COLLECTE_DAYS" value="'.((int) $defaultCollecteDays).'"></td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('RelanceMandatDays').'</td><td><input type="number" class="flat width75" name="PROCEDURESPV_RELANCE_MANDAT_DAYS" value="'.((int) $defaultMandatDays).'"></td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('RelanceEnedisIdleDays').'</td><td><input type="number" class="flat width75" name="PROCEDURESPV_RELANCE_ENEDIS_IDLE_DAYS" value="'.((int) $defaultEnedisDays).'"></td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('PublicUploadMaxSize').'</td><td><input type="number" class="flat width150" name="PROCEDURESPV_PUBLIC_UPLOAD_MAX_SIZE" value="'.((int) $maxUploadSize).'"></td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('PublicUploadAllowedExtensions').'</td><td><input type="text" class="flat minwidth300" name="PROCEDURESPV_PUBLIC_UPLOAD_ALLOWED_EXTENSIONS" value="'.dol_escape_htmltag($allowedExtensions).'"></td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('CollecteEmailTemplate').'</td><td><input type="text" class="flat minwidth300" name="PROCEDURESPV_EMAIL_TEMPLATE_COLLECTE" value="'.dol_escape_htmltag($emailTemplateCollecte).'"></td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('CollecteReminderEmailTemplate').'</td><td><input type="text" class="flat minwidth300" name="PROCEDURESPV_EMAIL_TEMPLATE_RELANCE_COLLECTE" value="'.dol_escape_htmltag($emailTemplateRelanceCollecte).'"></td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('MandatReminderEmailTemplate').'</td><td><input type="text" class="flat minwidth300" name="PROCEDURESPV_EMAIL_TEMPLATE_RELANCE_MANDAT" value="'.dol_escape_htmltag($emailTemplateRelanceMandat).'"></td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('MandatPdfModel').'</td><td><input type="text" class="flat minwidth200" name="PROCEDURESPV_PDF_MODEL_MANDAT_ENEDIS" value="'.dol_escape_htmltag($pdfModelMandat).'"></td></tr>';

print '</table>';

print '<div class="center">';
print '<input type="submit" class="button button-save" value="'.$langs->trans('Save').'">';
print '</div>';

print '</form>';

print dol_get_fiche_end();

llxFooter();
$db->close();
