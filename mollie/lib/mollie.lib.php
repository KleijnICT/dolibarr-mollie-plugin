<?php
/* Copyright (C) 2026 KleijnICT B.V. <https://kleijnict.nl>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * \file    htdocs/mollie/lib/mollie.lib.php
 * \ingroup mollie
 * \brief   Library files with common functions for Mollie
 */

/**
 * Prepare admin pages header
 *
 * @return array<array{string,string,string}>
 */
function mollieAdminPrepareHead()
{
	global $langs, $conf;

	$langs->load("mollie@mollie");

	$h = 0;
	$head = array();

	$head[$h][0] = dol_buildpath("/mollie/admin/setup.php", 1);
	$head[$h][1] = $langs->trans("Settings");
	$head[$h][2] = 'settings';
	$h++;

	$head[$h][0] = dol_buildpath("/mollie/admin/about.php", 1);
	$head[$h][1] = $langs->trans("About");
	$head[$h][2] = 'about';
	$h++;

	complete_head_from_modules($conf, $langs, null, $head, $h, 'mollie@mollie');

	complete_head_from_modules($conf, $langs, null, $head, $h, 'mollie@mollie', 'remove');

	return $head;
}

/**
 * Mask an API key for display: keep the mode prefix and last 4 characters.
 *
 * @param string $key API key (test_... or live_...)
 * @return string Masked key, e.g. "test_****abcd"
 */
function mollieMaskKey($key)
{
	$prefix = '';
	if (preg_match('/^(test_|live_)/', $key, $m)) {
		$prefix = $m[1];
	}
	return $prefix.'****'.substr($key, -4);
}
