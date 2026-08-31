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
 * \file    htdocs/mollie/public/webhook.php
 * \ingroup mollie
 * \brief   Mollie webhook: the primary booker of payments.
 *
 * Mollie POSTs only "id=tr_...", unsigned. The security model is fetch-and-verify:
 * we fetch the payment from the Mollie API with our own key and act solely on that
 * response, never on the request itself. Booking follows the same canonical path as
 * core's paymentok.php (Paiement->create with invoice closing + addPaymentToBank),
 * with deduplication on llx_paiement.ext_payment_id so webhook retries and a racing
 * return page can never book twice. Mollie expects HTTP 200 within 15 seconds and
 * retries non-200 responses for about 26 hours.
 */

if (!defined('NOLOGIN')) {
	define('NOLOGIN', '1'); // Public endpoint called by Mollie
}
if (!defined('NOCSRFCHECK')) {
	define('NOCSRFCHECK', '1'); // Mollie cannot send a CSRF token; security is fetch-and-verify
}
if (!defined('NOTOKENRENEWAL')) {
	define('NOTOKENRENEWAL', '1');
}
if (!defined('NOBROWSERNOTIF')) {
	define('NOBROWSERNOTIF', '1');
}
if (!defined('NOIPCHECK')) {
	define('NOIPCHECK', '1');
}

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

require_once __DIR__.'/../class/mollieclient.class.php';
require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';
require_once DOL_DOCUMENT_ROOT.'/compta/paiement/class/paiement.class.php';

/**
 * @var Conf $conf
 * @var DoliDB $db
 * @var User $user
 */

/**
 * End the request with an HTTP status and a short plain-text body.
 *
 * @param int    $code HTTP status code (non-200 makes Mollie retry)
 * @param string $msg  One-line result for the caller and the log
 * @return never
 */
function mollie_webhook_respond($code, $msg)
{
	dol_syslog('mollie webhook: '.$msg, $code >= 500 ? LOG_ERR : LOG_INFO, 0, '_payment');
	http_response_code($code);
	header('Content-Type: text/plain; charset=utf-8');
	print $msg."\n";
	exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	mollie_webhook_respond(405, 'Method not allowed, POST id=tr_... expected');
}

$paymentid = GETPOST('id', 'alphanohtml');
if (!preg_match('/^tr_[a-zA-Z0-9]+$/', $paymentid)) {
	mollie_webhook_respond(400, 'Missing or invalid payment id');
}

$client = MollieClient::createFromConf();
if ($client === null) {
	// Configuration problem an admin can still fix: let Mollie retry
	mollie_webhook_respond(503, 'Mollie module not configured');
}

// Fetch-and-verify: everything below acts on Mollie's answer, not on the request
$payment = $client->getPayment($paymentid);
if ($payment === null) {
	if ($client->lasthttpcode == 404) {
		// Unknown to our account (or other mode's key): nothing to do, stop retries
		mollie_webhook_respond(200, 'Payment '.$paymentid.' unknown, ignored');
	}
	mollie_webhook_respond(502, 'Could not verify payment '.$paymentid.': '.$client->error);
}

$status = empty($payment['status']) ? '' : $payment['status'];
if ($status !== 'paid') {
	// Terminal-but-unpaid (canceled, expired, failed) and intermediate states: nothing to book
	mollie_webhook_respond(200, 'Payment '.$paymentid.' has status '.$status.', nothing to record');
}

$meta = (empty($payment['metadata']) || !is_array($payment['metadata'])) ? array() : $payment['metadata'];
if ((empty($meta['source']) ? '' : $meta['source']) !== 'invoice' || empty($meta['invoice_id'])) {
	mollie_webhook_respond(200, 'Payment '.$paymentid.' carries no invoice metadata, ignored');
}
if (isset($meta['entity']) && (int) $meta['entity'] !== (int) $conf->entity) {
	// Multi-company would need the entity in the webhook URL; out of scope for now
	mollie_webhook_respond(200, 'Payment '.$paymentid.' belongs to entity '.((int) $meta['entity']).', not handled here');
}

// Deduplication: one Mollie payment books at most once, however often we are called
$sql = "SELECT rowid FROM ".MAIN_DB_PREFIX."paiement";
$sql .= " WHERE ext_payment_id = '".$db->escape($paymentid)."' AND ext_payment_site = 'mollie'";
$resql = $db->query($sql);
if (!$resql) {
	mollie_webhook_respond(500, 'Database error during duplicate check');
}
if ($db->num_rows($resql) > 0) {
	mollie_webhook_respond(200, 'Payment '.$paymentid.' was already recorded');
}

$invoice = new Facture($db);
if ($invoice->fetch((int) $meta['invoice_id']) <= 0) {
	mollie_webhook_respond(200, 'Invoice '.((int) $meta['invoice_id']).' for payment '.$paymentid.' not found, manual handling required');
}

if ($invoice->status == Facture::STATUS_DRAFT) {
	// Payment links only exist on validated invoices, so this is an anomaly; retrying will not fix it
	mollie_webhook_respond(200, 'Invoice '.$invoice->ref.' is still draft, payment '.$paymentid.' requires manual handling');
}

$pcurrency = empty($payment['amount']['currency']) ? '' : $payment['amount']['currency'];
$pamount = empty($payment['amount']['value']) ? 0 : (float) $payment['amount']['value'];
if ($pcurrency !== $conf->currency) {
	mollie_webhook_respond(200, 'Payment '.$paymentid.' is in '.$pcurrency.' but company currency is '.$conf->currency.', manual handling required');
}
if ($pamount <= 0) {
	mollie_webhook_respond(200, 'Payment '.$paymentid.' has a non-positive amount, ignored');
}

// Same remaining-amount arithmetic as the core payment page
$remaintopay = (float) price2num($invoice->total_ttc - $invoice->getSommePaiement() - $invoice->getSumCreditNotesUsed() - $invoice->getSumDepositsUsed(), 'MT');
if ($remaintopay <= 0) {
	mollie_webhook_respond(200, 'Invoice '.$invoice->ref.' is already fully paid, payment '.$paymentid.' requires manual handling');
}
if ($pamount > $remaintopay + 0.001) {
	mollie_webhook_respond(200, 'Payment '.$paymentid.' ('.$pamount.') exceeds remaining amount of invoice '.$invoice->ref.' ('.$remaintopay.'), manual handling required');
}

$paymenttypeid = getDolGlobalInt('MOLLIE_PAYMENT_MODE_ID');
if ($paymenttypeid <= 0) {
	$paymenttypeid = (int) dol_getIdFromCode($db, 'CB', 'c_paiement', 'code', 'id', 1);
}
if ($paymenttypeid <= 0) {
	mollie_webhook_respond(500, 'No payment mode configured and no CB fallback found');
}

$datepaye = empty($payment['paidAt']) ? 0 : (int) strtotime($payment['paidAt']); // paidAt is ISO 8601
if ($datepaye <= 0) {
	$datepaye = dol_now();
}

// Canonical booking path, mirroring core paymentok.php: create the payment
// (which also closes the invoice when fully paid) and add it to the bank account
$db->begin();

$paiement = new Paiement($db);
$paiement->datepaye = $datepaye;
$paiement->amounts = array($invoice->id => $pamount);
$paiement->paiementid = $paymenttypeid;
$paiement->num_payment = '';
$paiement->note_public = 'Online payment '.$paymentid.' via Mollie'.(empty($payment['method']) ? '' : ' ('.$payment['method'].')');
$paiement->ext_payment_id = $paymentid;
$paiement->ext_payment_site = 'mollie';

$paiement_id = $paiement->create($user, 1);
if ($paiement_id <= 0) {
	$db->rollback();
	mollie_webhook_respond(500, 'Failed to create payment for '.$paymentid.': '.$paiement->error.' '.implode('; ', $paiement->errors));
}

if (isModEnabled('bank')) {
	$bankaccountid = getDolGlobalInt('MOLLIE_BANK_ACCOUNT');
	if ($bankaccountid <= 0) {
		$db->rollback();
		mollie_webhook_respond(500, 'No bank account configured for Mollie payments');
	}
	$label = '(CustomerInvoicePayment)';
	if ($invoice->type == Facture::TYPE_CREDIT_NOTE) {
		$label = '(CustomerInvoicePaymentBack)';
	}
	$result = $paiement->addPaymentToBank($user, 'payment', $label, $bankaccountid, '', '');
	if ($result < 0) {
		$db->rollback();
		mollie_webhook_respond(500, 'Failed to add payment '.$paymentid.' to bank: '.$paiement->error.' '.implode('; ', $paiement->errors));
	}
}

$db->commit();

mollie_webhook_respond(200, 'Payment '.$paymentid.' ('.$pamount.' '.$pcurrency.') recorded on invoice '.$invoice->ref);
