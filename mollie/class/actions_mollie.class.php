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
 * \file    htdocs/mollie/class/actions_mollie.class.php
 * \ingroup mollie
 * \brief   Hooks for the online payment framework (context 'newpayment').
 */

require_once DOL_DOCUMENT_ROOT.'/core/class/commonhookactions.class.php';

/**
 * Class ActionsMollie
 *
 * Will carry the online payment hooks: getValidPayment, doAddButton, doPayment,
 * doCheckStatus, isPaymentOK and getBankAccountPaymentMethod. The methods are
 * added iteratively; the module does not register itself as a payment method
 * until the Mollie API connection is configured and working.
 */
class ActionsMollie extends CommonHookActions
{
	/**
	 * @var DoliDB Database handler.
	 */
	public $db;

	/**
	 * @var string Error code (or message)
	 */
	public $error = '';

	/**
	 * @var string[] Errors
	 */
	public $errors = array();

	/**
	 * @var mixed[] Hook results. Propagated to $hookmanager->resArray for later reuse
	 */
	public $results = array();

	/**
	 * @var ?string String displayed by executeHook() immediately after return
	 */
	public $resprints;

	/**
	 * Constructor
	 *
	 *  @param		DoliDB		$db      Database handler
	 */
	public function __construct($db)
	{
		$this->db = $db;
	}

	/**
	 * Return a configured Mollie client, or null when no API key is set for the active mode.
	 *
	 * @return MollieClient|null
	 */
	private function getClient()
	{
		require_once __DIR__.'/mollieclient.class.php';
		return MollieClient::createFromConf();
	}

	/**
	 * Hook getValidPayment (payments.lib.php): register 'mollie' as a valid online
	 * payment method as soon as an API key is configured. This is what makes the
	 * payment link appear on invoice cards and the public payment page accept us.
	 *
	 * @param array<string,mixed>	$parameters		'paymentmethod', 'mode', 'validpaymentmethod'
	 * @param CommonObject			$object			Not used by this hook
	 * @param string				$action			Current action
	 * @param HookManager			$hookmanager	Hook manager
	 * @return int									0 = merge our entry into the list
	 */
	public function getValidPayment($parameters, &$object, &$action, $hookmanager)
	{
		$paymentmethod = empty($parameters['paymentmethod']) ? '' : $parameters['paymentmethod'];
		if ($paymentmethod && $paymentmethod != 'mollie') {
			return 0;
		}
		if ($this->getClient() === null) {
			return 0; // Not configured, so not a valid method
		}

		if (!empty($parameters['mode'])) {
			$this->results = array('validpaymentmethod' => array('mollie' => array('label' => 'Mollie', 'status' => 'valid')));
		} else {
			$this->results = array('validpaymentmethod' => array('mollie' => 'valid'));
		}
		return 0;
	}

	/**
	 * Hook doAddButton (newpayment.php): print the "Pay with Mollie" button,
	 * following the same markup and click-through script as the core buttons.
	 *
	 * @param array<string,mixed>	$parameters		'paymentmethod'
	 * @param CommonObject			$object			Object to pay
	 * @param string				$action			Current action
	 * @param HookManager			$hookmanager	Hook manager
	 * @return int									0 on success
	 */
	public function doAddButton($parameters, &$object, &$action, $hookmanager)
	{
		global $langs;

		$paymentmethod = empty($parameters['paymentmethod']) ? '' : $parameters['paymentmethod'];
		if ($paymentmethod && $paymentmethod != 'mollie') {
			return 0;
		}
		if ($this->getClient() === null) {
			return 0;
		}

		$langs->load('mollie@mollie');

		$out = '<div class="button buttonpayment" id="div_dopayment_mollie"><span class="fa fa-credit-card"></span> <input class="" type="submit" id="dopayment_mollie" name="dopayment_mollie" value="'.dol_escape_htmltag($langs->trans("MollieDoPayment")).'">';
		$out .= '<br>';
		$out .= '<span class="buttonpaymentsmall">'.dol_escape_htmltag($langs->trans("MollieDoPaymentSubtitle")).'</span>';
		$out .= '</div>';
		$out .= '<script>
				$( document ).ready(function() {
					$("#div_dopayment_mollie").click(function(){
						$("#dopayment_mollie").click();
					});
					$("#dopayment_mollie").click(function(e){
						$("#div_dopayment_mollie").css(\'cursor\', \'wait\');
						e.stopPropagation();
					});
				});
			</script>';

		$this->resprints = $out;
		return 0;
	}

	/**
	 * Hook doPayment (newpayment.php, actions phase): when our button was clicked,
	 * create the Mollie payment and redirect the customer to the hosted checkout.
	 * The framework has already validated the secure key and loaded the object.
	 * Note: we deliberately do NOT set $_SESSION['FinalPaymentAmt'], so the return
	 * page only displays the result; the booking is done by our webhook (part 3b).
	 *
	 * @param array<string,mixed>	$parameters		'paymentmethod', 'validpaymentmethod'
	 * @param CommonObject			$object			Object to pay (Facture for source 'invoice')
	 * @param string				$action			Current action ('dopayment' when a button was clicked)
	 * @param HookManager			$hookmanager	Hook manager
	 * @return int									0 = not ours, -1 = error to display
	 */
	public function doPayment($parameters, &$object, &$action, $hookmanager)
	{
		global $conf, $langs;

		if ($action !== 'dopayment' || !GETPOST('dopayment_mollie')) {
			return 0;
		}

		$langs->load('mollie@mollie');

		$source = GETPOST('s', 'aZ09') ? GETPOST('s', 'aZ09') : GETPOST('source', 'aZ09');
		$ref = GETPOST('ref', 'alphanohtml');
		$amount = price2num(GETPOST('amount', 'alpha'), 'MT');
		$currency = GETPOST('currency', 'aZ09');
		if (empty($currency)) {
			$currency = $conf->currency;
		}

		if ($source !== 'invoice') {
			$this->errors[] = $langs->trans('MollieErrorSourceNotSupported', $source);
			return -1;
		}
		if (!is_numeric($amount) || (float) $amount <= 0) {
			$this->errors[] = $langs->trans('MollieErrorBadAmount');
			return -1;
		}

		// The framework has validated the securekey, but $object is not loaded at this
		// call site in the actions phase, so fetch the invoice ourselves from the ref.
		require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';
		$invoice = new Facture($this->db);
		if ($invoice->fetch(0, $ref) <= 0) {
			$this->errors[] = 'Invoice '.$ref.' not found';
			return -1;
		}

		$client = $this->getClient();
		if ($client === null) {
			$this->errors[] = $langs->trans('MollieErrorNoApiKey');
			return -1;
		}

		$body = array(
			'amount' => array(
				'currency' => $currency,
				'value' => number_format((float) $amount, 2, '.', ''), // Mollie wants a string with exactly 2 decimals
			),
			'description' => $langs->transnoentities('MolliePaymentDescription', $ref),
			'redirectUrl' => $this->buildReturnUrl(true),
			'cancelUrl' => $this->buildReturnUrl(false),
			'metadata' => array(
				'source' => $source,
				'ref' => $ref,
				'invoice_id' => (int) $invoice->id,
				'entity' => (int) $conf->entity,
			),
		);

		// Mollie refuses webhook URLs it cannot reach from the internet, so skip
		// the webhook on a local development instance (it can be fired by hand there).
		$webhookurl = DOL_MAIN_URL_ROOT.'/custom/mollie/public/webhook.php';
		if (!preg_match('/localhost|127\.0\.0\.1/i', $webhookurl)) {
			$body['webhookUrl'] = $webhookurl;
		}

		// No idempotency key here: retrying checkout for the same invoice is legitimate
		// (abandoned session, other language, expired iDEAL session), and Mollie rejects
		// a reused key as soon as any parameter differs. Only one payment can end up
		// paid, and the webhook books exactly that one. Core's Stripe module makes the
		// same trade-off (noidempotency=1 by default).
		$payment = $client->createPayment($body);
		if ($payment === null) {
			$this->errors[] = $client->error;
			return -1;
		}

		$checkouturl = empty($payment['_links']['checkout']['href']) ? '' : $payment['_links']['checkout']['href'];
		if (empty($checkouturl) || empty($payment['id'])) {
			$this->errors[] = $langs->trans('MollieErrorNoCheckoutUrl');
			return -1;
		}

		// Same convention as core payment modules; the return page reads it to verify the payment
		$_SESSION['TRANSACTIONID'] = $payment['id'];

		dol_syslog(__METHOD__.' created Mollie payment '.$payment['id'].' for '.$source.' '.$ref.', redirecting to checkout', LOG_INFO, 0, '_payment');

		header('Location: '.$checkouturl);
		exit;
	}

	/**
	 * Hook isPaymentOK (paymentok.php): tell the return page whether the payment
	 * really succeeded. Mollie appends nothing to the redirect URL, so we look up
	 * the payment id we stored in the session and verify the status at Mollie
	 * (fetch-and-verify). Display only; booking is the webhook's job.
	 *
	 * @param array<string,mixed>	$parameters		'paymentmethod'
	 * @param CommonObject			$object			Paid object
	 * @param string				$action			Current action
	 * @param HookManager			$hookmanager	Hook manager
	 * @return int									0 (result in $this->results['ispaymentok'])
	 */
	public function isPaymentOK($parameters, &$object, &$action, $hookmanager)
	{
		$paymentmethod = empty($parameters['paymentmethod']) ? '' : $parameters['paymentmethod'];
		if ($paymentmethod != 'mollie') {
			return 0;
		}

		$transactionid = empty($_SESSION['TRANSACTIONID']) ? '' : $_SESSION['TRANSACTIONID'];
		if (strpos($transactionid, 'tr_') !== 0) {
			return 0; // Session lost or not a Mollie payment: leave the default verdict
		}

		$client = $this->getClient();
		if ($client === null) {
			return 0;
		}

		$payment = $client->getPayment($transactionid);
		if ($payment === null) {
			dol_syslog(__METHOD__.' could not verify Mollie payment '.$transactionid.': '.$client->error, LOG_WARNING, 0, '_payment');
			return 0;
		}

		$this->results = array('ispaymentok' => (!empty($payment['status']) && $payment['status'] === 'paid'));
		return 0;
	}

	/**
	 * Hook getBankAccountPaymentMethod (paymentok.php): the bank account to use
	 * for payments made through Mollie (the operational "Mollie" counter account).
	 *
	 * @param array<string,mixed>	$parameters		'paymentmethod'
	 * @param CommonObject			$object			Paid object
	 * @param string				$action			Current action
	 * @param HookManager			$hookmanager	Hook manager
	 * @return int									0 (result in $this->results['bankaccountid'])
	 */
	public function getBankAccountPaymentMethod($parameters, &$object, &$action, $hookmanager)
	{
		$paymentmethod = empty($parameters['paymentmethod']) ? '' : $parameters['paymentmethod'];
		if ($paymentmethod != 'mollie') {
			return 0;
		}

		$this->results = array('bankaccountid' => getDolGlobalInt('MOLLIE_BANK_ACCOUNT'));
		return 0;
	}

	/**
	 * Rebuild the return URL exactly like newpayment.php builds $urlok/$urlko,
	 * from the same POSTed fields, and make sure the fulltag carries PM=mollie so
	 * the return page knows which payment method handled it.
	 *
	 * @param bool $ok True for the payment-ok URL, false for the payment-ko URL
	 * @return string Absolute URL to paymentok.php / paymentko.php
	 */
	private function buildReturnUrl($ok)
	{
		$url = DOL_MAIN_URL_ROOT.'/public/payment/'.($ok ? 'paymentok.php' : 'paymentko.php').'?';

		$fulltag = GETPOST('fulltag', 'alpha');
		if (!preg_match('/PM=mollie/', $fulltag)) {
			$fulltag .= ($fulltag ? '.' : '').'PM=mollie';
		}

		$suffix = GETPOST('suffix', 'aZ09');
		$source = GETPOST('s', 'aZ09') ? GETPOST('s', 'aZ09') : GETPOST('source', 'aZ09');
		$ref = GETPOST('ref', 'alphanohtml');
		$tag = GETPOST('tag', 'alpha');
		$securekey = GETPOST('securekey', 'alpha');
		$entity = GETPOSTINT('e');
		$getpostlang = GETPOST('lang', 'aZ09');

		if (!empty($suffix)) {
			$url .= 'suffix='.urlencode($suffix).'&';
		}
		if ($source) {
			$url .= 's='.urlencode($source).'&';
		}
		if (!empty($ref)) {
			$url .= 'ref='.urlencode($ref).'&';
		}
		if (!empty($tag)) {
			$url .= 'tag='.urlencode($tag).'&';
		}
		if (!empty($fulltag)) {
			$url .= 'fulltag='.urlencode($fulltag).'&';
		}
		if (!empty($securekey)) {
			$url .= 'securekey='.urlencode($securekey).'&';
		}
		if (!empty($entity)) {
			$url .= 'e='.urlencode((string) $entity).'&';
		}
		if (!empty($getpostlang)) {
			$url .= 'lang='.urlencode($getpostlang).'&';
		}

		return preg_replace('/&$/', '', $url);
	}
}
