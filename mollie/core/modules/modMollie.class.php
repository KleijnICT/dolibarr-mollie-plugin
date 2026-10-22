<?php
/* Copyright (C) 2026 KleijnICT B.V. <https://kleijnict.nl>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * 	\defgroup   mollie     Module Mollie
 *  \brief      Mollie module descriptor.
 *
 *  \file       htdocs/mollie/core/modules/modMollie.class.php
 *  \ingroup    mollie
 *  \brief      Description and activation file for module Mollie
 */
include_once DOL_DOCUMENT_ROOT.'/core/modules/DolibarrModules.class.php';


/**
 *  Description and activation class for module Mollie
 */
class modMollie extends DolibarrModules
{
	/**
	 * Constructor. Define names, constants, directories, boxes, permissions
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		global $conf, $langs;

		$this->db = $db;

		// Id for module (must be unique). 500000+ is the range for external/custom modules.
		$this->numero = 500880;

		// Key text used to identify module (for permissions, menus, etc...)
		$this->rights_class = 'mollie';

		// It is used to group modules by family in module setup page
		$this->family = "interface";

		// Module position in the family on 2 digits ('01', '10', '20', ...)
		$this->module_position = '50';

		// Module label (no space allowed), used if translation string 'ModuleMollieName' not found (Mollie is name of module).
		$this->name = preg_replace('/^mod/i', '', get_class($this));

		// Module description, used if translation string 'ModuleMollieDesc' not found (Mollie is name of module).
		$this->description = "MollieDescription";
		// Used only if file README.md and README-LL.md not found.
		$this->descriptionlong = "Accept online payments on invoices via Mollie hosted checkout (iDEAL, cards and all methods enabled in your Mollie dashboard).";

		// Author
		$this->editor_name = 'KleijnICT B.V.';
		$this->editor_url = 'https://kleijnict.nl';

		$this->version = '1.0.0';

		// Key used in llx_const table to save module status enabled/disabled (where MOLLIE is value of property name of module in uppercase)
		$this->const_name = 'MAIN_MODULE_'.strtoupper($this->name);

		// To use a supported fa-xxx css style of font awesome, use this->picto='xxx'
		$this->picto = 'fa-credit-card';

		// Define some features supported by module (triggers, login, substitutions, menus, css, etc...)
		$this->module_parts = array(
			'triggers' => 0,
			'login' => 0,
			'substitutions' => 0,
			'menus' => 0,
			'tpl' => 0,
			'barcode' => 0,
			'models' => 0,
			'printing' => 0,
			'theme' => 0,
			'css' => array(),
			'js' => array(),
			// Hook contexts managed by this module. The online payment pages
			// (public/payment/newpayment.php and paymentok.php) run under context 'newpayment';
			// the invoice card runs under 'invoicecard' and needs our getValidPayment hook
			// to decide whether to show the online payment link.
			'hooks' => array(
				'data' => array(
					'newpayment',
					'invoicecard',
				),
			),
			'moduleforexternal' => 0,
			'websitetemplates' => 0,
			'captcha' => 0
		);

		// Data directories to create when module is enabled.
		$this->dirs = array("/mollie/temp");

		// Config pages. Put here list of php page, stored into mollie/admin directory, to use to setup module.
		$this->config_page_url = array("setup.php@mollie");

		// Dependencies
		$this->hidden = getDolGlobalInt('MODULE_MOLLIE_DISABLED'); // A condition to disable module
		$this->depends = array();
		$this->requiredby = array();
		$this->conflictwith = array();

		// The language file dedicated to your module
		$this->langfiles = array("mollie@mollie");

		// Prerequisites
		$this->phpmin = array(7, 4); // Minimum version of PHP required by module
		$this->need_dolibarr_version = array(23, 0); // Minimum version of Dolibarr required by module
		$this->need_javascript_ajax = 0;

		// Messages at activation
		$this->warnings_activation = array();
		$this->warnings_activation_ext = array();

		// Constants added when module is enabled (none yet; API keys are added via the setup page)
		$this->const = array();

		if (!isModEnabled("mollie")) {
			$conf->mollie = new stdClass();
			$conf->mollie->enabled = 0;
		}

		// No tabs, dictionaries, boxes, cronjobs, permissions or menus:
		// this module integrates through the online payment framework hooks only.
		$this->tabs = array();
		$this->dictionaries = array();
		$this->boxes = array();
		$this->cronjobs = array();
		$this->rights = array();
		$this->menu = array();
	}

	/**
	 *  Function called when module is enabled.
	 *  The init function add constants, boxes, permissions and menus (defined in constructor) into Dolibarr database.
	 *  It also creates data directories
	 *
	 *  @param      string  $options    Options when enabling module ('', 'noboxes')
	 *  @return     int<-1,1>          	1 if OK, <=0 if KO
	 */
	public function init($options = '')
	{
		$sql = array();

		return $this->_init($sql, $options);
	}

	/**
	 *	Function called when module is disabled.
	 *	Remove from database constants, boxes and permissions from Dolibarr database.
	 *	Data directories are not deleted
	 *
	 *	@param	string		$options	Options when enabling module ('', 'noboxes')
	 *	@return	int<-1,1>				1 if OK, <=0 if KO
	 */
	public function remove($options = '')
	{
		$sql = array();

		return $this->_remove($sql, $options);
	}
}
