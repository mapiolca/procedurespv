<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

require '../../../main.inc.php';
require_once dol_buildpath('/procedurespv/lib/procedurespv.lib.php', 0);
require_once dol_buildpath('/procedurespv/class/procedurespvcompatibility.class.php', 0);

$langs->loadLangs(array('admin', 'procedurespv@procedurespv'));

if (!isModEnabled('procedurespv')) {
	accessforbidden();
}

$permissiontosetup = !empty($user->admin) || $user->hasRight('procedurespv', 'setup', 'write');
if (!$permissiontosetup) {
	accessforbidden();
}

llxHeader('', $langs->trans('Compatibility'), '', '', 0, 0, '', '', '', 'mod-procedurespv page-admin');

$linkback = '<a href="'.DOL_URL_ROOT.'/admin/modules.php?search_keyword='.urlencode('procedurespv').'">'.$langs->trans('BackToModuleList').'</a>';
print load_fiche_titre($langs->trans('ProceduresPVSetup'), $linkback, 'title_setup');

$head = procedurespvAdminPrepareHead();
print dol_get_fiche_head($head, 'compatibility', $langs->trans('ProceduresPVSetup'), -1, 'fa-solar-panel');

print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><td>'.$langs->trans('Information').'</td><td>'.$langs->trans('Value').'</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('DetectedDolibarrVersion').'</td><td>'.dol_escape_htmltag(defined('DOL_VERSION') ? DOL_VERSION : $langs->trans('Unknown')).'</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('DetectedPHPVersion').'</td><td>'.dol_escape_htmltag(PHP_VERSION).'</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('MinimumDolibarrVersion').'</td><td>20.0.0</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('MinimumPHPVersion').'</td><td>8.0.0</td></tr>';
print '</table>';

print '<br>';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><td>'.$langs->trans('Feature').'</td><td>'.$langs->trans('Status').'</td><td>'.$langs->trans('Reason').'</td></tr>';
foreach (ProceduresPVCompatibility::getCompatibilityFeatures() as $feature) {
	$status = !empty($feature['available']) ? $langs->trans('Available') : $langs->trans('Unavailable');
	$reason = !empty($feature['reason']) ? $langs->trans($feature['reason']) : '';
	print '<tr class="oddeven">';
	print '<td><strong>'.$langs->trans($feature['label']).'</strong><br><span class="opacitymedium">'.$langs->trans($feature['description']).'</span></td>';
	print '<td>'.$status.'</td>';
	print '<td>'.$reason.'</td>';
	print '</tr>';
}
print '</table>';

print dol_get_fiche_end();

llxFooter();
$db->close();
