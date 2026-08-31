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
 * \file    htdocs/mollie/admin/setup.php
 * \ingroup mollie
 * \brief   Mollie setup page.
 */

// Load Dolibarr environment
$res = 0;
// Try main.inc.php into web root known defined into CONTEXT_DOCUMENT_ROOT (not always defined)
if (!$res && !empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) {
	$res = @include str_replace("..", "", $_SERVER["CONTEXT_DOCUMENT_ROOT"])."/main.inc.php";
}
// Try main.inc.php into web root detected using web root calculated from SCRIPT_FILENAME
$tmp = empty($_SERVER['SCRIPT_FILENAME']) ? '' : $_SERVER['SCRIPT_FILENAME'];
$tmp2 = realpath(__FILE__);
$i = strlen($tmp) - 1;
$j = strlen($tmp2) - 1;
while ($i > 0 && $j > 0 && isset($tmp[$i]) && isset($tmp2[$j]) && $tmp[$i] == $tmp2[$j]) {
	$i--;
	$j--;
}
if (!$res && $i > 0 && file_exists(substr($tmp, 0, ($i + 1))."/main.inc.php")) {
	$res = @include substr($tmp, 0, ($i + 1))."/main.inc.php";
}
if (!$res && $i > 0 && file_exists(dirname(substr($tmp, 0, ($i + 1)))."/main.inc.php")) {
	$res = @include dirname(substr($tmp, 0, ($i + 1)))."/main.inc.php";
}
// Try main.inc.php using relative path
if (!$res && file_exists("../../main.inc.php")) {
	$res = @include "../../main.inc.php";
}
if (!$res && file_exists("../../../main.inc.php")) {
	$res = @include "../../../main.inc.php";
}
if (!$res) {
	die("Include of main fails");
}

// Libraries
require_once DOL_DOCUMENT_ROOT."/core/lib/admin.lib.php";
require_once '../lib/mollie.lib.php';
require_once '../class/mollieclient.class.php';

/**
 * @var Conf $conf
 * @var DoliDB $db
 * @var HookManager $hookmanager
 * @var Translate $langs
 * @var User $user
 */

// Translations
$langs->loadLangs(array("admin", "banks", "bills", "mollie@mollie"));

// Initialize a technical object to manage hooks of page
$hookmanager->initHooks(array('molliesetup', 'globalsetup'));

// Parameters
$action = GETPOST('action', 'aZ09');
$backtopage = GETPOST('backtopage', 'alpha');

// Access control
if (!$user->admin) {
	accessforbidden();
}


/*
 * Actions
 */

if ($action == 'update') {
	$error = 0;

	// API keys: an empty field means "keep the stored key"
	$testkey = GETPOST('MOLLIE_TEST_API_KEY', 'alphanohtml');
	if ($testkey !== '') {
		if (strpos($testkey, 'test_') !== 0) {
			setEventMessages($langs->trans("MollieErrorKeyPrefix", $langs->transnoentities("MollieTestApiKey"), 'test_'), null, 'errors');
			$error++;
		} else {
			dolibarr_set_const($db, 'MOLLIE_TEST_API_KEY', $testkey, 'chaine', 0, '', $conf->entity);
		}
	}
	$livekey = GETPOST('MOLLIE_LIVE_API_KEY', 'alphanohtml');
	if ($livekey !== '') {
		if (strpos($livekey, 'live_') !== 0) {
			setEventMessages($langs->trans("MollieErrorKeyPrefix", $langs->transnoentities("MollieLiveApiKey"), 'live_'), null, 'errors');
			$error++;
		} else {
			dolibarr_set_const($db, 'MOLLIE_LIVE_API_KEY', $livekey, 'chaine', 0, '', $conf->entity);
		}
	}

	dolibarr_set_const($db, 'MOLLIE_LIVE_ENABLED', GETPOSTINT('MOLLIE_LIVE_ENABLED'), 'chaine', 0, '', $conf->entity);
	dolibarr_set_const($db, 'MOLLIE_BANK_ACCOUNT', GETPOSTINT('MOLLIE_BANK_ACCOUNT'), 'chaine', 0, '', $conf->entity);
	dolibarr_set_const($db, 'MOLLIE_PAYMENT_MODE_ID', GETPOSTINT('MOLLIE_PAYMENT_MODE_ID'), 'chaine', 0, '', $conf->entity);

	if (!$error) {
		setEventMessages($langs->trans("SetupSaved"), null, 'mesgs');
	}

	// The "save and test" button: after saving, try the connection with the fresh settings
	// (dolibarr_set_const already updated $conf->global in memory).
	if (GETPOST('testconnection', 'alpha')) {
		$client = MollieClient::createFromConf();
		if ($client === null) {
			setEventMessages($langs->trans("MollieErrorNoApiKey"), null, 'errors');
		} else {
			$methods = $client->listMethods();
			if ($methods === null) {
				setEventMessages($langs->trans("MollieConnectionFailed").'<br>'.$client->error, null, 'errors');
			} else {
				$labels = array();
				foreach ($methods as $method) {
					$labels[] = isset($method['description']) ? $method['description'] : $method['id'];
				}
				$mode = $client->isLive() ? 'live' : 'test';
				if (count($labels)) {
					setEventMessages($langs->trans("MollieConnectionOk", $mode).'<br>'.$langs->trans("MollieActiveMethods").': '.implode(', ', $labels), null, 'mesgs');
				} else {
					setEventMessages($langs->trans("MollieConnectionOk", $mode).'<br>'.$langs->trans("MollieNoActiveMethods"), null, 'warnings');
				}
			}
		}
	}

	// Messages are kept in the session, so they survive this redirect (classic POST/redirect/GET)
	header("Location: ".$_SERVER["PHP_SELF"]);
	exit;
}


/*
 * View
 */

$form = new Form($db);

$help_url = '';
$title = "MollieSetup";

llxHeader('', $langs->trans($title), $help_url, '', 0, 0, '', '', '', 'mod-mollie page-admin');

// Subheader
$linkback = '<a href="'.($backtopage ? $backtopage : DOL_URL_ROOT.'/admin/modules.php?restore_lastsearch_values=1').'">'.img_picto($langs->trans("BackToModuleList"), 'back', 'class="pictofixedwidth"').'<span class="hideonsmartphone">'.$langs->trans("BackToModuleList").'</span></a>';

print load_fiche_titre($langs->trans($title), $linkback, 'title_setup');

// Configuration header
$head = mollieAdminPrepareHead();
print dol_get_fiche_head($head, 'settings', $langs->trans($title), -1, 'fa-credit-card');

print '<span class="opacitymedium">'.$langs->trans("MollieSetupPage").'</span><br><br>';

print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="update">';

print '<table class="noborder centpercent">';

print '<tr class="liste_titre">';
print '<td>'.$langs->trans("Parameter").'</td>';
print '<td>'.$langs->trans("Value").'</td>';
print '</tr>';

// Test API key
print '<tr class="oddeven">';
print '<td>'.$langs->trans("MollieTestApiKey").'</td>';
print '<td><input type="password" class="minwidth300" name="MOLLIE_TEST_API_KEY" value="" autocomplete="new-password" placeholder="test_...">';
$current = getDolGlobalString('MOLLIE_TEST_API_KEY');
if ($current) {
	print ' <span class="opacitymedium">'.$langs->trans("MollieKeyStored", mollieMaskKey($current)).'</span>';
} else {
	print ' <span class="opacitymedium">'.$langs->trans("MollieKeyNotSet").'</span>';
}
print '</td></tr>';

// Live API key
print '<tr class="oddeven">';
print '<td>'.$langs->trans("MollieLiveApiKey").'</td>';
print '<td><input type="password" class="minwidth300" name="MOLLIE_LIVE_API_KEY" value="" autocomplete="new-password" placeholder="live_...">';
$current = getDolGlobalString('MOLLIE_LIVE_API_KEY');
if ($current) {
	print ' <span class="opacitymedium">'.$langs->trans("MollieKeyStored", mollieMaskKey($current)).'</span>';
} else {
	print ' <span class="opacitymedium">'.$langs->trans("MollieKeyNotSet").'</span>';
}
print '</td></tr>';

// Test / live switch
print '<tr class="oddeven">';
print '<td>'.$langs->trans("MollieLiveEnabled").'</td>';
print '<td>'.$form->selectyesno('MOLLIE_LIVE_ENABLED', getDolGlobalInt('MOLLIE_LIVE_ENABLED'), 1);
print ' <span class="opacitymedium">'.$langs->trans("MollieLiveEnabledHelp").'</span>';
print '</td></tr>';

// Bank account (the operational "Mollie" counter account)
print '<tr class="oddeven">';
print '<td>'.$langs->trans("MollieBankAccount").'</td>';
print '<td>';
if (isModEnabled('bank')) {
	print img_picto('', 'bank_account', 'class="pictofixedwidth"');
	print $form->select_comptes(getDolGlobalInt('MOLLIE_BANK_ACCOUNT'), 'MOLLIE_BANK_ACCOUNT', 0, '', 1, '', 0, 'minwidth300', 1);
} else {
	print '<span class="opacitymedium">'.$langs->trans("MollieBankModuleDisabled").'</span>';
}
print ' <span class="opacitymedium">'.$langs->trans("MollieBankAccountHelp").'</span>';
print '</td></tr>';

// Payment mode used for the payment records
print '<tr class="oddeven">';
print '<td>'.$langs->trans("MolliePaymentMode").'</td>';
print '<td>';
print $form->select_types_paiements(getDolGlobalInt('MOLLIE_PAYMENT_MODE_ID'), 'MOLLIE_PAYMENT_MODE_ID', '', 0, 1, 0, 0, 1, 'minwidth300', 1);
print ' <span class="opacitymedium">'.$langs->trans("MolliePaymentModeHelp").'</span>';
print '</td></tr>';

print '</table>';

// One form, two buttons: the test button saves the settings first, then tries the connection,
// so "fill in key, click test" works in one step.
print '<div class="center">';
print '<input class="button button-save" type="submit" name="save" value="'.$langs->trans("Save").'">';
print ' ';
print '<input class="button" type="submit" name="testconnection" value="'.$langs->trans("MollieSaveAndTestConnection").'">';
print '</div>';

print '</form>';

// Page end
print dol_get_fiche_end();

llxFooter();
$db->close();
