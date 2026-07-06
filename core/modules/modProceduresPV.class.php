<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

include_once DOL_DOCUMENT_ROOT.'/core/modules/DolibarrModules.class.php';

/**
 * Module descriptor for Procedures PV.
 */
class modProceduresPV extends DolibarrModules
{
	/**
	 * Constructor.
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		global $conf;

		$this->db = $db;

		$this->numero = 450009;
		$this->rights_class = 'procedurespv';
		$this->family = 'Les Métiers du Bâtiment';
		$this->module_position = 91;
		$this->name = preg_replace('/^mod/i', '', get_class($this));
		$this->description = 'ProceduresPVModuleDescription';
		$this->descriptionlong = 'ProceduresPVModuleDescriptionLong';
		$this->version = '0.1.0';
		$this->const_name = 'MAIN_MODULE_'.strtoupper($this->rights_class);
		$this->picto = 'fa-solar-panel';
		$this->editor_name = 'Pierre Ardoin';
		$this->editor_url = '';

		$this->module_parts = array(
			'models' => 1,
			'hooks' => array(
				'data' => array('emailtemplates'),
				'entity' => '0',
			),
		);
		$this->dirs = array('/procedurespv/temp');
		$this->config_page_url = array('setup.php@procedurespv');
		$this->hidden = false;
		$this->depends = array();
		$this->requiredby = array();
		$this->conflictwith = array();
		$this->phpmin = array(8, 0);
		$this->need_dolibarr_version = array(20, 0);
		$this->langfiles = array('procedurespv@procedurespv');
		$this->tabs = array();
		$this->dictionaries = array();
		$this->boxes = array();
		$this->cronjobs = array();

		$this->const = array();

		$this->rights = array();
		$r = 0;

		$r++;
		$this->rights[$r][0] = $this->numero * 100 + $r;
		$this->rights[$r][1] = 'ReadRaccordements';
		$this->rights[$r][4] = 'raccordement';
		$this->rights[$r][5] = 'read';

		$r++;
		$this->rights[$r][0] = $this->numero * 100 + $r;
		$this->rights[$r][1] = 'CreateModifyRaccordements';
		$this->rights[$r][4] = 'raccordement';
		$this->rights[$r][5] = 'write';

		$r++;
		$this->rights[$r][0] = $this->numero * 100 + $r;
		$this->rights[$r][1] = 'DeleteRaccordements';
		$this->rights[$r][4] = 'raccordement';
		$this->rights[$r][5] = 'delete';

		$r++;
		$this->rights[$r][0] = $this->numero * 100 + $r;
		$this->rights[$r][1] = 'SendCollecteClient';
		$this->rights[$r][4] = 'raccordement';
		$this->rights[$r][5] = 'send_collecte';

		$r++;
		$this->rights[$r][0] = $this->numero * 100 + $r;
		$this->rights[$r][1] = 'ValidateCollecteClient';
		$this->rights[$r][4] = 'raccordement';
		$this->rights[$r][5] = 'validate_collecte';

		$r++;
		$this->rights[$r][0] = $this->numero * 100 + $r;
		$this->rights[$r][1] = 'ValidateMandatEnedis';
		$this->rights[$r][4] = 'raccordement';
		$this->rights[$r][5] = 'validate_mandat';

		$r++;
		$this->rights[$r][0] = $this->numero * 100 + $r;
		$this->rights[$r][1] = 'LinkCentralePV';
		$this->rights[$r][4] = 'raccordement';
		$this->rights[$r][5] = 'link_centrale';

		$r++;
		$this->rights[$r][0] = $this->numero * 100 + $r;
		$this->rights[$r][1] = 'FreezeRaccordementSnapshot';
		$this->rights[$r][4] = 'raccordement';
		$this->rights[$r][5] = 'freeze_snapshot';

		$r++;
		$this->rights[$r][0] = $this->numero * 100 + $r;
		$this->rights[$r][1] = 'EditSnapshotAfterDeposit';
		$this->rights[$r][4] = 'raccordement';
		$this->rights[$r][5] = 'edit_snapshot_after_deposit';

		$r++;
		$this->rights[$r][0] = $this->numero * 100 + $r;
		$this->rights[$r][1] = 'ManageCARDi';
		$this->rights[$r][4] = 'raccordement';
		$this->rights[$r][5] = 'manage_cardi';

		$r++;
		$this->rights[$r][0] = $this->numero * 100 + $r;
		$this->rights[$r][1] = 'ManageConventions';
		$this->rights[$r][4] = 'raccordement';
		$this->rights[$r][5] = 'manage_convention';

		$r++;
		$this->rights[$r][0] = $this->numero * 100 + $r;
		$this->rights[$r][1] = 'ManageMES';
		$this->rights[$r][4] = 'raccordement';
		$this->rights[$r][5] = 'manage_mes';

		$r++;
		$this->rights[$r][0] = $this->numero * 100 + $r;
		$this->rights[$r][1] = 'ManageRelances';
		$this->rights[$r][4] = 'raccordement';
		$this->rights[$r][5] = 'manage_relance';

		$r++;
		$this->rights[$r][0] = $this->numero * 100 + $r;
		$this->rights[$r][1] = 'SetupProceduresPV';
		$this->rights[$r][4] = 'setup';
		$this->rights[$r][5] = 'write';

		$this->menu = array();
		$r = 0;

		$centralepvModuleKey = '';
		if (getDolGlobalInt('PROCEDURESPV_USE_CENTRALEPV_IF_AVAILABLE', 1) > 0) {
			foreach (array('powerplantpv', 'centralepv', 'centrale_pv', 'centralespv') as $candidateModuleKey) {
				if (function_exists('isModEnabled') && isModEnabled($candidateModuleKey)) {
					$centralepvModuleKey = $candidateModuleKey;
					break;
				}
				if (is_object($conf) && !empty($conf->{$candidateModuleKey}->enabled)) {
					$centralepvModuleKey = $candidateModuleKey;
					break;
				}
			}
		}

		$centralepvEnabled = $centralepvModuleKey !== '';
		$mainmenu = $centralepvEnabled ? $centralepvModuleKey : 'procedurespv';

		if (!$centralepvEnabled) {
			$this->menu[$r++] = array(
				'fk_menu' => '',
				'type' => 'top',
				'titre' => 'ProceduresPV',
				'mainmenu' => 'procedurespv',
				'leftmenu' => '',
				'url' => '/custom/procedurespv/index.php',
				'langs' => 'procedurespv@procedurespv',
				'position' => 1000,
				'enabled' => 'isModEnabled("procedurespv")',
				'perms' => '$user->hasRight("procedurespv", "raccordement", "read")',
				'target' => '',
				'user' => 2,
			);
		}

		$this->menu[$r++] = array(
			'fk_menu' => 'fk_mainmenu='.$mainmenu,
			'type' => 'left',
			'titre' => 'Raccordement',
			'prefix' => img_picto('', 'fa-plug', 'class="paddingright pictofixedwidth valignmiddle"'),
			'mainmenu' => $mainmenu,
			'leftmenu' => 'procedurespv_raccordement',
			'url' => '/custom/procedurespv/raccordement/list.php',
			'langs' => 'procedurespv@procedurespv',
			'position' => 1010,
			'enabled' => 'isModEnabled("procedurespv")',
			'perms' => '$user->hasRight("procedurespv", "raccordement", "read")',
			'target' => '',
			'user' => 2,
		);

		$this->menu[$r++] = array(
			'fk_menu' => 'fk_mainmenu='.$mainmenu.',fk_leftmenu=procedurespv_raccordement',
			'type' => 'left',
			'titre' => 'NewRaccordement',
			'mainmenu' => $mainmenu,
			'leftmenu' => '',
			'url' => '/custom/procedurespv/raccordement/card.php?action=create',
			'langs' => 'procedurespv@procedurespv',
			'position' => 1020,
			'enabled' => 'isModEnabled("procedurespv")',
			'perms' => '$user->hasRight("procedurespv", "raccordement", "write")',
			'target' => '',
			'user' => 2,
		);

		$this->menu[$r++] = array(
			'fk_menu' => 'fk_mainmenu='.$mainmenu.',fk_leftmenu=procedurespv_raccordement',
			'type' => 'left',
			'titre' => 'RaccordementList',
			'mainmenu' => $mainmenu,
			'leftmenu' => '',
			'url' => '/custom/procedurespv/raccordement/list.php',
			'langs' => 'procedurespv@procedurespv',
			'position' => 1030,
			'enabled' => 'isModEnabled("procedurespv")',
			'perms' => '$user->hasRight("procedurespv", "raccordement", "read")',
			'target' => '',
			'user' => 2,
		);
	}

	/**
	 * Init module.
	 *
	 * @param string $options Options
	 * @return int
	 */
	public function init($options = '')
	{
		global $conf;

		require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
		require_once DOL_DOCUMENT_ROOT.'/core/class/translate.class.php';
		require_once dol_buildpath('/procedurespv/class/actions_procedurespv.class.php', 0);

		$sql = array();
		$result = $this->_load_tables('/procedurespv/sql/');
		if ($result < 0) {
			return -1;
		}

		$legacyMandatPdfModel = getDolGlobalString('PROCEDURESPV_PDF_MODEL_MANDAT_ENEDIS', '');
		$mandatPdfModel = $legacyMandatPdfModel !== '' ? $legacyMandatPdfModel : 'mandatenedis';

		$missingEmailTemplateConsts = array();
		foreach (ActionsProceduresPV::getDefaultEmailTemplatesDefinition() as $templateconf) {
			$constName = (string) $templateconf['const'];
			$missingEmailTemplateConsts[$constName] = getDolGlobalString($constName, '__PROCEDURESPV_UNSET__') === '__PROCEDURESPV_UNSET__';
		}

		$defaults = array(
			'PROCEDURESPV_USE_CENTRALEPV_IF_AVAILABLE' => '1',
			'PROCEDURESPV_ALLOW_WITHOUT_CENTRALEPV' => '1',
			'PROCEDURESPV_PREFILL_FROM_CENTRALEPV' => '1',
			'PROCEDURESPV_AUTO_FREEZE_ON_ENEDIS_DEPOSIT' => '1',
			'PROCEDURESPV_PUBLICLINK_VALIDITY_DAYS' => '30',
			'PROCEDURESPV_RELANCE_COLLECTE_DAYS' => '7',
			'PROCEDURESPV_RELANCE_MANDAT_DAYS' => '7',
			'PROCEDURESPV_RELANCE_ENEDIS_IDLE_DAYS' => '30',
			'PROCEDURESPV_PUBLIC_UPLOAD_MAX_SIZE' => '10485760',
			'PROCEDURESPV_PUBLIC_UPLOAD_ALLOWED_EXTENSIONS' => 'pdf,jpg,jpeg,png',
			'PROCEDURESPV_EMAIL_TEMPLATE_COLLECTE' => '',
			'PROCEDURESPV_EMAIL_TEMPLATE_RELANCE_COLLECTE' => '',
			'PROCEDURESPV_EMAIL_TEMPLATE_RELANCE_MANDAT' => '',
			'PROCEDURESPV_RACCORDEMENT_ADDON' => 'mod_pvproc_standard',
			'PROCEDURESPV_RACCORDEMENT_STANDARD_MASK' => 'DDR{yyyy}{mm}-{0000}',
			'PROCEDURESPV_RACCORDEMENT_ADVANCED_MASK' => 'DDR{yyyy}{mm}-{0000}',
			'PROCEDURESPV_MANDATENEDIS_ADDON_PDF' => $mandatPdfModel,
			'PROCEDURESPV_PDF_MODEL_MANDAT_ENEDIS' => 'mandatenedis',
			'PROCEDURESPV_MANDATENEDIS_STAMP_IMAGE' => '',
		);

		foreach ($defaults as $name => $value) {
			if (getDolGlobalString($name, '__PROCEDURESPV_UNSET__') === '__PROCEDURESPV_UNSET__') {
				dolibarr_set_const($this->db, $name, $value, 'chaine', 0, '', (int) $conf->entity);
			}
		}

		$sqlCheck = 'SELECT rowid';
		$sqlCheck .= ' FROM '.MAIN_DB_PREFIX.'document_model';
		$sqlCheck .= " WHERE nom = 'mandatenedis'";
		$sqlCheck .= " AND type = 'procedurespv_mandatenedis'";
		$sqlCheck .= ' AND entity = '.((int) $conf->entity);
		$resql = $this->db->query($sqlCheck);
		if ($resql) {
			$num = $this->db->num_rows($resql);
			$this->db->free($resql);
			if ((int) $num === 0) {
				addDocumentModel('mandatenedis', 'procedurespv_mandatenedis', 'mandatenedis', 'procedurespv/core/modules/procedurespv/doc');
			}
		}

		$resultEmailTemplateMigration = $this->migrateLegacyEmailTemplateTypes();
		if ($resultEmailTemplateMigration < 0) {
			return $resultEmailTemplateMigration;
		}

		$resultEmailTemplates = $this->registerDefaultEmailTemplates($missingEmailTemplateConsts);
		if ($resultEmailTemplates < 0) {
			return $resultEmailTemplates;
		}

		return $this->_init($sql, $options);
	}

	/**
	 * Migrate legacy email template types that exceeded the native Dolibarr column length.
	 *
	 * @return int
	 */
	private function migrateLegacyEmailTemplateTypes()
	{
		$legacyTypes = array(
			'procedurespv_raccordement_collecte' => ActionsProceduresPV::EMAIL_TEMPLATE_TYPE_COLLECTE,
			'procedurespv_raccordement_relance_collecte' => ActionsProceduresPV::EMAIL_TEMPLATE_TYPE_RELANCE_COLLECTE,
			'procedurespv_raccordement_relance_mandat' => ActionsProceduresPV::EMAIL_TEMPLATE_TYPE_RELANCE_MANDAT,
		);

		foreach ($legacyTypes as $oldType => $newType) {
			$sql = 'UPDATE '.MAIN_DB_PREFIX.'c_email_templates';
			$sql .= " SET type_template = '".$this->db->escape($newType)."'";
			$sql .= " WHERE module = 'procedurespv'";
			$sql .= " AND type_template = '".$this->db->escape($oldType)."'";

			$resql = $this->db->query($sql);
			if (!$resql) {
				$this->error = $this->db->lasterror();
				return -2;
			}
		}

		return 1;
	}

	/**
	 * Register default native email templates without overwriting edited templates.
	 *
	 * @param array<string,bool> $missingEmailTemplateConsts Constants missing before init defaults were created
	 * @return int
	 */
	private function registerDefaultEmailTemplates($missingEmailTemplateConsts)
	{
		global $conf;

		$templates = ActionsProceduresPV::getDefaultEmailTemplatesDefinition();
		$langcodes = array('fr_FR', 'en_US');
		$entity = !empty($conf->entity) ? (int) $conf->entity : 1;
		$defaultLang = getDolGlobalString('MAIN_LANG_DEFAULT', 'fr_FR');
		if (!in_array($defaultLang, $langcodes, true)) {
			$defaultLang = 'fr_FR';
		}

		foreach ($langcodes as $langcode) {
			$outputlangs = new Translate('', $conf);
			$outputlangs->setDefaultLang($langcode);
			$outputlangs->loadLangs(array('procedurespv@procedurespv'));

			foreach ($templates as $templateconf) {
				$typeTemplate = $this->db->escape((string) $templateconf['type_template']);
				$label = $this->db->escape($outputlangs->transnoentitiesnoconv((string) $templateconf['label']));
				$topic = $this->db->escape($outputlangs->transnoentitiesnoconv((string) $templateconf['topic']));
				$content = $this->db->escape($outputlangs->transnoentitiesnoconv((string) $templateconf['content']));
				$position = (int) $templateconf['position'];
				$joinfiles = (int) $templateconf['joinfiles'];

				$sql = 'INSERT INTO '.MAIN_DB_PREFIX.'c_email_templates';
				$sql .= ' (entity, module, type_template, lang, private, fk_user, datec, label, position, active, enabled, joinfiles, topic, content)';
				$sql .= ' SELECT '.$entity.", 'procedurespv', '".$typeTemplate."', '".$this->db->escape($langcode)."', 0, NULL, NOW(),";
				$sql .= " '".$label."', ".$position.', 1, \'isModEnabled("procedurespv")\', '.$joinfiles.", '".$topic."', '".$content."'";
				$sql .= ' FROM DUAL';
				$sql .= ' WHERE NOT EXISTS (';
				$sql .= 'SELECT 1 FROM '.MAIN_DB_PREFIX.'c_email_templates';
				$sql .= ' WHERE entity = '.$entity;
				$sql .= " AND module = 'procedurespv'";
				$sql .= " AND type_template = '".$typeTemplate."'";
				$sql .= " AND lang = '".$this->db->escape($langcode)."'";
				$sql .= " AND label = '".$label."'";
				$sql .= ')';

				$resql = $this->db->query($sql);
				if (!$resql) {
					$this->error = $this->db->lasterror();
					return -2;
				}
			}
		}

		foreach ($templates as $templateconf) {
			$constName = (string) $templateconf['const'];
			if (empty($missingEmailTemplateConsts[$constName])) {
				continue;
			}

			$templateId = $this->fetchDefaultEmailTemplateId($templateconf, $defaultLang, $entity);
			if ($templateId <= 0 && $defaultLang !== 'fr_FR') {
				$templateId = $this->fetchDefaultEmailTemplateId($templateconf, 'fr_FR', $entity);
			}
			if ($templateId > 0) {
				dolibarr_set_const($this->db, $constName, (string) $templateId, 'chaine', 0, '', $entity);
			}
		}

		return 1;
	}

	/**
	 * Fetch one default email template identifier.
	 *
	 * @param array{label:string,type_template:string} $templateconf Template definition
	 * @param string $langcode Language code
	 * @param int $entity Entity id
	 * @return int
	 */
	private function fetchDefaultEmailTemplateId($templateconf, $langcode, $entity)
	{
		global $conf;

		$outputlangs = new Translate('', $conf);
		$outputlangs->setDefaultLang($langcode);
		$outputlangs->loadLangs(array('procedurespv@procedurespv'));

		$sql = 'SELECT rowid';
		$sql .= ' FROM '.MAIN_DB_PREFIX.'c_email_templates';
		$sql .= ' WHERE entity = '.((int) $entity);
		$sql .= " AND module = 'procedurespv'";
		$sql .= " AND type_template = '".$this->db->escape((string) $templateconf['type_template'])."'";
		$sql .= " AND lang = '".$this->db->escape($langcode)."'";
		$sql .= " AND label = '".$this->db->escape($outputlangs->transnoentitiesnoconv((string) $templateconf['label']))."'";
		$sql .= ' ORDER BY rowid ASC';
		$sql .= $this->db->plimit(1);

		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return -1;
		}

		$obj = $this->db->fetch_object($resql);
		$rowid = is_object($obj) ? (int) $obj->rowid : 0;
		$this->db->free($resql);

		return $rowid;
	}

	/**
	 * Remove module.
	 *
	 * Settings and business data are intentionally preserved.
	 *
	 * @param string $options Options
	 * @return int
	 */
	public function remove($options = '')
	{
		$sql = array();

		return $this->_remove($sql, $options);
	}
}
