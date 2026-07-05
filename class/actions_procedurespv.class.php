<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * Hooks for Procedures PV module.
 */
class ActionsProceduresPV
{
	/** @var string Email template type for customer intake links */
	public const EMAIL_TEMPLATE_TYPE_COLLECTE = 'procedurespv_raccordement_collecte';

	/** @var string Email template type for customer intake reminders */
	public const EMAIL_TEMPLATE_TYPE_RELANCE_COLLECTE = 'procedurespv_raccordement_relance_collecte';

	/** @var string Email template type for ENEDIS mandate reminders */
	public const EMAIL_TEMPLATE_TYPE_RELANCE_MANDAT = 'procedurespv_raccordement_relance_mandat';

	/** @var string Runtime placeholder replaced by the generated public intake URL */
	public const PUBLIC_COLLECTE_URL_PLACEHOLDER = '__PROCEDURESPV_PUBLIC_COLLECTE_URL__';

	/** @var DoliDB Database handler */
	public $db;

	/** @var string Error */
	public $error = '';

	/** @var array<string> Errors */
	public $errors = array();

	/** @var array<string,mixed> Hook results */
	public $results = array();

	/** @var string|null Hook output */
	public $resprints;

	/**
	 * Constructor.
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		$this->db = $db;
	}

	/**
	 * Return native email template types exposed by the module.
	 *
	 * @return array<string,array{label:string,picto:string}>
	 */
	public static function getEmailTemplateTypes()
	{
		return array(
			self::EMAIL_TEMPLATE_TYPE_COLLECTE => array(
				'label' => 'EmailTemplateTypeCollecteClient',
				'picto' => 'email',
			),
			self::EMAIL_TEMPLATE_TYPE_RELANCE_COLLECTE => array(
				'label' => 'EmailTemplateTypeRelanceCollecte',
				'picto' => 'email',
			),
			self::EMAIL_TEMPLATE_TYPE_RELANCE_MANDAT => array(
				'label' => 'EmailTemplateTypeRelanceMandat',
				'picto' => 'email',
			),
		);
	}

	/**
	 * Return default native email templates created at module activation.
	 *
	 * @return array<string,array{const:string,type_template:string,label:string,topic:string,content:string,position:int,joinfiles:int}>
	 */
	public static function getDefaultEmailTemplatesDefinition()
	{
		return array(
			'collecte_client' => array(
				'const' => 'PROCEDURESPV_EMAIL_TEMPLATE_COLLECTE',
				'type_template' => self::EMAIL_TEMPLATE_TYPE_COLLECTE,
				'label' => 'DefaultEmailTemplateCollecteLabel',
				'topic' => 'DefaultEmailTemplateCollecteTopic',
				'content' => 'DefaultEmailTemplateCollecteContent',
				'position' => 100,
				'joinfiles' => 0,
			),
			'relance_collecte' => array(
				'const' => 'PROCEDURESPV_EMAIL_TEMPLATE_RELANCE_COLLECTE',
				'type_template' => self::EMAIL_TEMPLATE_TYPE_RELANCE_COLLECTE,
				'label' => 'DefaultEmailTemplateRelanceCollecteLabel',
				'topic' => 'DefaultEmailTemplateRelanceCollecteTopic',
				'content' => 'DefaultEmailTemplateRelanceCollecteContent',
				'position' => 110,
				'joinfiles' => 0,
			),
			'relance_mandat' => array(
				'const' => 'PROCEDURESPV_EMAIL_TEMPLATE_RELANCE_MANDAT',
				'type_template' => self::EMAIL_TEMPLATE_TYPE_RELANCE_MANDAT,
				'label' => 'DefaultEmailTemplateRelanceMandatLabel',
				'topic' => 'DefaultEmailTemplateRelanceMandatTopic',
				'content' => 'DefaultEmailTemplateRelanceMandatContent',
				'position' => 120,
				'joinfiles' => 0,
			),
		);
	}

	/**
	 * Add Procedures PV entries into the native email templates type list.
	 *
	 * @param array<string,mixed> $parameters Hook parameters
	 * @param CommonObject $object Current object
	 * @param string $action Current action
	 * @param HookManager $hookmanager Hook manager
	 * @return int
	 */
	public function emailElementlist($parameters, &$object, &$action, $hookmanager)
	{
		global $langs;

		$langs->loadLangs(array('procedurespv@procedurespv'));

		$this->results = array();
		foreach (self::getEmailTemplateTypes() as $type => $typeconf) {
			$this->results[$type] = img_picto('', $typeconf['picto'], 'class="pictofixedwidth"').dol_escape_htmltag($langs->trans($typeconf['label']));
		}

		return 0;
	}
}
