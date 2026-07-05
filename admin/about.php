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

$langs->loadLangs(array('admin', 'procedurespv@procedurespv'));

if (!isModEnabled('procedurespv')) {
	accessforbidden();
}

$permissiontosetup = !empty($user->admin) || $user->hasRight('procedurespv', 'setup', 'write');
if (!$permissiontosetup) {
	accessforbidden();
}

llxHeader('', $langs->trans('About'), '', '', 0, 0, '', '', '', 'mod-procedurespv page-admin');

$linkback = '<a href="'.DOL_URL_ROOT.'/admin/modules.php?search_keyword='.urlencode('procedurespv').'">'.$langs->trans('BackToModuleList').'</a>';
print load_fiche_titre($langs->trans('ProceduresPVSetup'), $linkback, 'title_setup');

$head = procedurespvAdminPrepareHead();
print dol_get_fiche_head($head, 'about', $langs->trans('ProceduresPVSetup'), -1, 'fa-solar-panel');

print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><td>'.$langs->trans('Information').'</td><td>'.$langs->trans('Value').'</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('ModuleName').'</td><td>'.$langs->trans('ProceduresPV').'</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('Version').'</td><td>0.1.0</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('Publisher').'</td><td>Pierre Ardoin</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('Description').'</td><td>'.$langs->trans('ProceduresPVShortDescription').'</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('Compatibility').'</td><td>Dolibarr 20.0+ / PHP 8.0+</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('Dependencies').'</td><td>'.$langs->trans('NoMandatoryDependency').'</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('MainFeatures').'</td><td>'.$langs->trans('ProceduresPVMainFeatures').'</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('License').'</td><td>GPL-3.0-or-later</td></tr>';
print '</table>';

print dol_get_fiche_end();

llxFooter();
$db->close();
