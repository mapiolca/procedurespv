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
require_once DOL_DOCUMENT_ROOT.'/core/lib/ajax.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formmail.class.php';
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

$pdfDocumentTypeMandatEnedis = 'procedurespv_mandatenedis';
$pdfDocumentConstMandatEnedis = 'PROCEDURESPV_MANDATENEDIS_ADDON_PDF';
$legacyPdfDocumentConstMandatEnedis = 'PROCEDURESPV_PDF_MODEL_MANDAT_ENEDIS';
$value = GETPOST('value', 'alpha');
$label = GETPOST('label', 'restricthtml');
$scandir = GETPOST('scan_dir', 'restricthtml');
$type = GETPOST('type', 'alpha');
if ($type === '') {
	$type = $pdfDocumentTypeMandatEnedis;
}

/**
 * Return a template id stored in a Dolibarr constant.
 *
 * @param string $constName Constant name
 * @return int
 */
function procedurespvGetEmailTemplateConstId($constName)
{
	$value = getDolGlobalString($constName, '');

	return ctype_digit($value) ? (int) $value : 0;
}

/**
 * Render a native Dolibarr email template select.
 *
 * @param FormMail $formmail FormMail helper
 * @param Translate $langs Translation handler
 * @param User $user Current user
 * @param string $htmlName HTML field name
 * @param string $typeTemplate Native email template type
 * @param int $selectedId Selected template id
 * @return void
 */
function procedurespvPrintEmailTemplateSelect($formmail, $langs, $user, $htmlName, $typeTemplate, $selectedId)
{
	$result = $formmail->fetchAllEMailTemplate($typeTemplate, $user, $langs, 1);
	$lines = array();

	print '<select class="flat minwidth500" id="'.dol_escape_htmltag($htmlName).'" name="'.dol_escape_htmltag($htmlName).'">';
	print '<option value="0"'.($selectedId <= 0 ? ' selected="selected"' : '').'>'.$langs->trans('NoDefaultEmailTemplate').'</option>';

	if ($result >= 0) {
		$lines = is_array($formmail->lines_model) ? $formmail->lines_model : array();
		foreach ($lines as $template) {
			if (!is_object($template) || empty($template->id)) {
				continue;
			}

			$templateId = (int) $template->id;
			$templateLabel = !empty($template->label) ? (string) $template->label : (string) $templateId;
			$templateLang = !empty($template->lang) ? (string) $template->lang : '';
			$templateTopic = !empty($template->topic) ? (string) $template->topic : '';
			$optionLabel = $templateLabel;
			if ($templateLang !== '') {
				$optionLabel .= ' ['.$templateLang.']';
			}
			if ($templateTopic !== '') {
				$optionLabel .= ' - '.dol_trunc($templateTopic, 80);
			}

			print '<option value="'.$templateId.'"'.($selectedId === $templateId ? ' selected="selected"' : '').'>'.dol_escape_htmltag($optionLabel).'</option>';
		}
	}

	print '</select>';
	if (function_exists('ajax_combobox')) {
		print ajax_combobox($htmlName);
	}

	print '<br><span class="opacitymedium">'.$langs->trans('EmailTemplateTypeUsed', dol_escape_htmltag($typeTemplate)).'</span>';
	if ($result >= 0 && empty($lines)) {
		print '<br><span class="opacitymedium">'.$langs->trans('NoEmailTemplateForType').'</span>';
	} elseif ($result < 0) {
		print '<br><span class="error">'.dol_escape_htmltag($formmail->error).'</span>';
	}
}

/**
 * Return active document models for a native document type.
 *
 * @param DoliDB $db Database handler
 * @param string $type Native document model type
 * @return array<int, string>
 */
function procedurespvGetActiveDocumentModels($db, $type)
{
	global $conf;

	$models = array();
	$sql = 'SELECT nom';
	$sql .= ' FROM '.MAIN_DB_PREFIX.'document_model';
	$sql .= " WHERE type = '".$db->escape($type)."'";
	$sql .= ' AND entity = '.((int) $conf->entity);

	$resql = $db->query($sql);
	if ($resql) {
		while ($obj = $db->fetch_object($resql)) {
			if (is_object($obj) && !empty($obj->nom)) {
				$models[] = (string) $obj->nom;
			}
		}
		$db->free($resql);
	} else {
		dol_print_error($db);
	}

	return $models;
}

/**
 * Render native document model administration for the ENEDIS mandate PDF.
 *
 * @param DoliDB $db Database handler
 * @param Translate $langs Translation handler
 * @param Form $form Form helper
 * @param string $type Native document model type
 * @param string $currentDefault Current default model
 * @return void
 */
function procedurespvPrintMandatPdfModelBlock($db, $langs, $form, $type, $currentDefault)
{
	$activeModels = procedurespvGetActiveDocumentModels($db, $type);
	$modelDir = dol_buildpath('/procedurespv/core/modules/procedurespv/doc', 0);
	$modelPathLabel = 'procedurespv/core/modules/procedurespv/doc';
	$pageUrl = dol_buildpath('/procedurespv/admin/setup.php', 1);

	print load_fiche_titre($langs->trans('DocumentModules', $langs->trans('MandatPdfModel')), '', '');
	print '<table class="noborder centpercent">';
	print '<tr class="liste_titre">';
	print '<td>'.$langs->trans('Name').'</td>';
	print '<td>'.$langs->trans('Description').'</td>';
	print '<td class="center width75">'.$langs->trans('Status').'</td>';
	print '<td class="center width75">'.$langs->trans('Default').'</td>';
	print '<td class="center width75">'.$langs->trans('ShortInfo').'</td>';
	print '<td class="center width75">'.$langs->trans('Preview').'</td>';
	print '</tr>';

	if (!is_dir($modelDir)) {
		print '<tr class="oddeven"><td colspan="6"><span class="opacitymedium">'.$langs->trans('NoRecordFound').'</span></td></tr>';
		print '</table>';
		return;
	}

	$filelist = glob($modelDir.'/pdf_*.modules.php');
	if (!is_array($filelist)) {
		$filelist = array();
	}
	sort($filelist);

	$numShown = 0;
	foreach ($filelist as $filepath) {
		$file = basename($filepath);
		if (!preg_match('/^pdf_([a-zA-Z0-9_]+)\.modules\.php$/', $file, $matches)) {
			continue;
		}

		$name = $matches[1];
		$classname = 'pdf_'.$name;
		require_once $filepath;
		if (!class_exists($classname)) {
			continue;
		}

		$module = new $classname($db);
		$modelDocumentType = !empty($module->document_model_type) ? (string) $module->document_model_type : $type;
		if ($modelDocumentType !== $type) {
			continue;
		}

		$moduleVersion = !empty($module->version) ? (string) $module->version : 'dolibarr';
		if ($moduleVersion === 'development' && getDolGlobalInt('MAIN_FEATURES_LEVEL') < 2) {
			continue;
		}
		if ($moduleVersion === 'experimental' && getDolGlobalInt('MAIN_FEATURES_LEVEL') < 1) {
			continue;
		}

		$numShown++;
		$moduleName = !empty($module->name) ? (string) $module->name : $name;
		$moduleLabel = $langs->trans($moduleName) !== $moduleName ? $langs->trans($moduleName) : $moduleName;
		$moduleDescription = '';
		if (method_exists($module, 'info')) {
			$moduleDescription = (string) $module->info($langs);
		} elseif (!empty($module->description)) {
			$moduleDescription = $langs->trans((string) $module->description);
		}
		$modelScandir = !empty($module->scandir) ? (string) $module->scandir : $modelPathLabel;
		$modelType = !empty($module->type) ? (string) $module->type : 'pdf';

		print '<tr class="oddeven">';
		print '<td>'.dol_escape_htmltag($moduleLabel).'</td>';
		print '<td>'.$moduleDescription.'</td>';

		print '<td class="center">';
		if (in_array($name, $activeModels, true)) {
			print '<a href="'.$pageUrl.'?action=del&token='.newToken().'&value='.urlencode($name).'&type='.urlencode($type).'">';
			print img_picto($langs->trans('Enabled'), 'switch_on');
			print '</a>';
		} else {
			print '<a href="'.$pageUrl.'?action=set&token='.newToken().'&value='.urlencode($name).'&type='.urlencode($type).'&scan_dir='.urlencode($modelScandir).'&label='.urlencode($moduleLabel).'">';
			print img_picto($langs->trans('Disabled'), 'switch_off');
			print '</a>';
		}
		print '</td>';

		print '<td class="center">';
		if ($currentDefault === $name) {
			print '<a href="'.$pageUrl.'?action=unsetdoc&token='.newToken().'&value='.urlencode($name).'&type='.urlencode($type).'">';
			print img_picto($langs->trans('Enabled'), 'on');
			print '</a>';
		} else {
			print '<a href="'.$pageUrl.'?action=setdoc&token='.newToken().'&value='.urlencode($name).'&type='.urlencode($type).'&scan_dir='.urlencode($modelScandir).'&label='.urlencode($moduleLabel).'">';
			print img_picto($langs->trans('Disabled'), 'off');
			print '</a>';
		}
		print '</td>';

		$htmltooltip = $langs->trans('Name').': '.dol_escape_htmltag($moduleLabel);
		$htmltooltip .= '<br>'.$langs->trans('Type').': '.dol_escape_htmltag($modelType);
		if ($modelType === 'pdf' && !empty($module->page_largeur) && !empty($module->page_hauteur)) {
			$htmltooltip .= '<br>'.$langs->trans('Width').'/'.$langs->trans('Height').': '.((int) $module->page_largeur).'/'.((int) $module->page_hauteur);
		}
		$htmltooltip .= '<br>'.$langs->trans('Path').': '.dol_escape_htmltag($modelPathLabel.'/'.$file);
		$htmltooltip .= '<br><br><u>'.$langs->trans('FeaturesSupported').':</u>';
		$htmltooltip .= '<br>'.$langs->trans('Logo').': '.yn(!empty($module->option_logo), 1, 1);
		$htmltooltip .= '<br>'.$langs->trans('MultiLanguage').': '.yn(!empty($module->option_multilang), 1, 1);

		print '<td class="center">'.$form->textwithpicto('', $htmltooltip, 1, 'info').'</td>';
		print '<td class="center">'.img_object($langs->transnoentitiesnoconv('PreviewNotAvailable'), 'generic').'</td>';
		print '</tr>';
	}

	if ($numShown === 0) {
		print '<tr class="oddeven"><td colspan="6"><span class="opacitymedium">'.$langs->trans('NoRecordFound').'</span></td></tr>';
	}

	print '</table>';
}

if (in_array($action, array('set', 'del', 'setdoc', 'unsetdoc'), true)) {
	if (!GETPOST('token', 'alpha')) {
		accessforbidden($langs->trans('ErrorBadToken'));
	}

	$error = 0;
	if ($type !== $pdfDocumentTypeMandatEnedis || $value === '') {
		accessforbidden('Bad document model parameters');
	}

	if ($action === 'set') {
		$result = addDocumentModel($value, $type, $label, $scandir);
		if ($result <= 0) {
			$error++;
		}
	} elseif ($action === 'del') {
		$result = delDocumentModel($value, $type);
		if ($result <= 0) {
			$error++;
		}
		if (!$error && getDolGlobalString($pdfDocumentConstMandatEnedis) === $value) {
			dolibarr_del_const($db, $pdfDocumentConstMandatEnedis, (int) $conf->entity);
			dolibarr_del_const($db, $legacyPdfDocumentConstMandatEnedis, (int) $conf->entity);
		}
	} elseif ($action === 'setdoc') {
		$result = dolibarr_set_const($db, $pdfDocumentConstMandatEnedis, $value, 'chaine', 0, '', (int) $conf->entity);
		$resultLegacy = dolibarr_set_const($db, $legacyPdfDocumentConstMandatEnedis, $value, 'chaine', 0, '', (int) $conf->entity);
		if ($result <= 0 || $resultLegacy <= 0) {
			$error++;
		} else {
			$conf->global->{$pdfDocumentConstMandatEnedis} = $value;
			$conf->global->{$legacyPdfDocumentConstMandatEnedis} = $value;
			$result = delDocumentModel($value, $type);
			if ($result > 0) {
				$result = addDocumentModel($value, $type, $label, $scandir);
			}
			if ($result <= 0) {
				$error++;
			}
		}
	} elseif ($action === 'unsetdoc') {
		$result = dolibarr_del_const($db, $pdfDocumentConstMandatEnedis, (int) $conf->entity);
		$resultLegacy = dolibarr_del_const($db, $legacyPdfDocumentConstMandatEnedis, (int) $conf->entity);
		if ($result <= 0 && $resultLegacy <= 0) {
			$error++;
		}
	}

	if ($error) {
		setEventMessages($langs->trans('ErrorSetupNotSaved'), null, 'errors');
	} else {
		setEventMessages($langs->trans('SetupSaved'), null, 'mesgs');
	}
} elseif ($action === 'save') {
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
	);

	foreach ($stringSettings as $constName) {
		$value = GETPOST($constName, 'alphanohtml');
		$result = dolibarr_set_const($db, $constName, $value, 'chaine', 0, '', (int) $conf->entity);
		if ($result <= 0) {
			$error++;
		}
	}

	$emailTemplateSettings = array(
		'PROCEDURESPV_EMAIL_TEMPLATE_COLLECTE',
		'PROCEDURESPV_EMAIL_TEMPLATE_RELANCE_COLLECTE',
		'PROCEDURESPV_EMAIL_TEMPLATE_RELANCE_MANDAT',
	);

	foreach ($emailTemplateSettings as $constName) {
		$value = GETPOSTINT($constName);
		$result = dolibarr_set_const($db, $constName, $value > 0 ? (string) $value : '', 'chaine', 0, '', (int) $conf->entity);
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
$emailTemplateCollecteId = procedurespvGetEmailTemplateConstId('PROCEDURESPV_EMAIL_TEMPLATE_COLLECTE');
$emailTemplateRelanceCollecteId = procedurespvGetEmailTemplateConstId('PROCEDURESPV_EMAIL_TEMPLATE_RELANCE_COLLECTE');
$emailTemplateRelanceMandatId = procedurespvGetEmailTemplateConstId('PROCEDURESPV_EMAIL_TEMPLATE_RELANCE_MANDAT');
$pdfModelMandat = getDolGlobalString($pdfDocumentConstMandatEnedis, '');
if ($pdfModelMandat === '') {
	$pdfModelMandat = getDolGlobalString($legacyPdfDocumentConstMandatEnedis, '');
}

$form = new Form($db);
$formmail = new FormMail($db);

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
print '<tr class="oddeven"><td>'.$langs->trans('CollecteEmailTemplate').'</td><td>';
procedurespvPrintEmailTemplateSelect($formmail, $langs, $user, 'PROCEDURESPV_EMAIL_TEMPLATE_COLLECTE', 'procedurespv_raccordement_collecte', $emailTemplateCollecteId);
print '</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('CollecteReminderEmailTemplate').'</td><td>';
procedurespvPrintEmailTemplateSelect($formmail, $langs, $user, 'PROCEDURESPV_EMAIL_TEMPLATE_RELANCE_COLLECTE', 'procedurespv_raccordement_relance_collecte', $emailTemplateRelanceCollecteId);
print '</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('MandatReminderEmailTemplate').'</td><td>';
procedurespvPrintEmailTemplateSelect($formmail, $langs, $user, 'PROCEDURESPV_EMAIL_TEMPLATE_RELANCE_MANDAT', 'procedurespv_raccordement_relance_mandat', $emailTemplateRelanceMandatId);
print '</td></tr>';

print '</table>';

print '<div class="center">';
print '<input type="submit" class="button button-save" value="'.$langs->trans('Save').'">';
print '</div>';

print '</form>';

print '<br>';
procedurespvPrintMandatPdfModelBlock($db, $langs, $form, $pdfDocumentTypeMandatEnedis, $pdfModelMandat);

print dol_get_fiche_end();

llxFooter();
$db->close();
