<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

require '../../main.inc.php';

$langs->loadLangs(array('procedurespv@procedurespv'));

if (!isModEnabled('procedurespv')) {
	accessforbidden();
}

if (empty($user->admin) && !$user->hasRight('procedurespv', 'raccordement', 'read')) {
	accessforbidden();
}

header('Location: '.dol_buildpath('/procedurespv/raccordement/list.php', 1));
exit;

