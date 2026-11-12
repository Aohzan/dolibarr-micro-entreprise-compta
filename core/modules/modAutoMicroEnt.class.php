<?php
/* Copyright (C) 2004-2018 Laurent Destailleur  <eldy@users.sourceforge.net>
 * Copyright (C) 2013-2018 Fabrice Delliaux <netbox253@gmail.com>
 * Copyright (C) 2026 Aohzan (fork: https://github.com/Aohzan/dolibarr-micro-entreprise-compta)
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
 * \defgroup automicroent Micro-entreprise
 * \brief    Turnover to declare and estimated contributions of a French micro-entreprise.
 *
 * \file     automicroent/core/modules/modAutoMicroEnt.class.php
 * \ingroup  automicroent
 * \brief    Module descriptor.
 */

include_once DOL_DOCUMENT_ROOT.'/core/modules/DolibarrModules.class.php';

// phpcs:disable Squiz.Classes.ValidClassName.NotCamelCaps -- Dolibarr requires the "mod" prefix.
/**
 * Descriptor of the automicroent module.
 *
 * The identifiers (number, rights class, table, URLs) stay those of the
 * original module so that existing installations upgrade in place.
 */
class modAutoMicroEnt extends DolibarrModules
{
	/**
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		$this->db = $db;

		$this->numero = 451050;
		$this->rights_class = 'automicroent';
		$this->family = 'financial';
		$this->module_position = '90';
		$this->name = preg_replace('/^mod/i', '', get_class($this));
		$this->description = 'Turnover to declare and estimated contributions of a French micro-entreprise';
		$this->editor_name = 'Aohzan';
		$this->editor_url = 'https://github.com/Aohzan/dolibarr-micro-entreprise-compta';
		$this->url_last_version = 'https://github.com/Aohzan/dolibarr-micro-entreprise-compta/releases/latest';
		// Set by scripts/release.sh.
		$this->version = '7.1.0';
		$this->const_name = 'MAIN_MODULE_'.strtoupper($this->name);
		$this->picto = 'fa-file-invoice';

		$this->module_parts = array();
		$this->dirs = array();
		$this->config_page_url = array('setup.php@automicroent');

		$this->hidden = false;
		$this->depends = array('modFacture', 'modComptabilite');
		$this->requiredby = array();
		$this->conflictwith = array();
		$this->langfiles = array('automicroent@automicroent');
		// Same PHP range as Dolibarr 23 and 24 (htdocs/install/check.php).
		$this->phpmin = array(7, 2);
		$this->need_dolibarr_version = array(23, 0);

		$this->const = array();
		$this->tabs = array();
		$this->dictionaries = array();
		$this->boxes = array();
		$this->cronjobs = array();

		$this->rights = array();
		$this->rights[] = array(451051, 'AME_PermissionUse', 'r', 1, 'use');
		$this->rights[] = array(451052, 'AME_PermissionConfigure', 'w', 0, 'configure');

		$this->menu = array();
		$this->menu[] = array(
			'fk_menu' => 'fk_mainmenu=accountancy',
			'type' => 'left',
			'titre' => 'AME_Menu',
			'prefix' => img_picto('', $this->picto, 'class="pictofixedwidth"'),
			'mainmenu' => 'accountancy',
			'leftmenu' => 'automicroent',
			'url' => '/automicroent/declaration.php',
			'langs' => 'automicroent@automicroent',
			'position' => 1000,
			'enabled' => 'isModEnabled("automicroent")',
			'perms' => '$user->hasRight("automicroent", "use")',
			'target' => '',
			'user' => 0,
		);
		$children = array(
			array('automicroent_rates', 'AME_MenuRates', '/automicroent/rates.php', 'configure'),
			array('automicroent_declaration', 'AME_MenuDeclarations', '/automicroent/declaration.php', 'use'),
			array('automicroent_stats', 'AME_MenuStats', '/automicroent/stats.php', 'use'),
		);
		foreach ($children as $i => $child) {
			list($leftmenu, $title, $url, $right) = $child;
			$this->menu[] = array(
				'fk_menu' => 'fk_mainmenu=accountancy,fk_leftmenu=automicroent',
				'type' => 'left',
				'titre' => $title,
				'mainmenu' => 'accountancy',
				'leftmenu' => $leftmenu,
				'url' => $url,
				'langs' => 'automicroent@automicroent',
				'position' => 1001 + $i,
				'enabled' => 'isModEnabled("automicroent")',
				'perms' => '$user->hasRight("automicroent", "'.$right.'")',
				'target' => '',
				'user' => 0,
			);
		}
	}

	/**
	 * Called when the module is enabled: creates or migrates the table, then
	 * the rights and menus.
	 *
	 * @param string $options Options ('', 'noboxes')
	 * @return int 1 if OK, 0 if KO
	 */
	public function init($options = '')
	{
		if ($this->_load_tables('/automicroent/sql/') <= 0) {
			return 0;
		}
		return $this->_init(array(), $options);
	}

	/**
	 * Called when the module is disabled. The rates are kept.
	 *
	 * @param string $options Options ('', 'noboxes')
	 * @return int 1 if OK, 0 if KO
	 */
	public function remove($options = '')
	{
		return $this->_remove(array(), $options);
	}
}
