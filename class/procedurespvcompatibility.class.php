<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * Compatibility helper for Procedures PV.
 *
 * @phpstan-type CompatibilityFeature array{
 * 	label: string,
 * 	description: string,
 * 	min_dolibarr?: string,
 * 	core_available_from?: string,
 * 	module_available_from?: string,
 * 	min_php?: string,
 * 	compatibility_check?: string,
 * 	available: bool,
 * 	reason?: string
 * }
 */
class ProceduresPVCompatibility
{
	/**
	 * Test Dolibarr version.
	 *
	 * @param string $version Minimal version
	 * @return bool
	 */
	public static function isDolibarrVersionAtLeast($version)
	{
		return defined('DOL_VERSION') && version_compare(DOL_VERSION, $version, '>=');
	}

	/**
	 * Test PHP version.
	 *
	 * @param string $version Minimal version
	 * @return bool
	 */
	public static function isPhpVersionAtLeast($version)
	{
		return version_compare(PHP_VERSION, $version, '>=');
	}

	/**
	 * Return compatibility features.
	 *
	 * @return array<string, CompatibilityFeature>
	 */
	public static function getCompatibilityFeatures()
	{
		$dolibarr20 = self::isDolibarrVersionAtLeast('20.0.0');
		$php80 = self::isPhpVersionAtLeast('8.0.0');
		$nativeEmailTemplates = defined('DOL_DOCUMENT_ROOT') && is_readable(DOL_DOCUMENT_ROOT.'/core/class/html.formmail.class.php');
		$nativeDocumentModels = defined('DOL_DOCUMENT_ROOT') && is_readable(DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php');
		$powerPlantPVEquipment = false;
		if (function_exists('isModEnabled') && isModEnabled('powerplantpv') && function_exists('dol_buildpath')) {
			$inverterClass = dol_buildpath('/powerplantpv/class/productinverter.class.php', 0);
			$importClass = dol_buildpath('/powerplantpv/class/powerplantpvproductimport.class.php', 0);
			if (is_readable($inverterClass) && is_readable($importClass)) {
				dol_include_once('/powerplantpv/class/productinverter.class.php');
				dol_include_once('/powerplantpv/class/powerplantpvproductimport.class.php');
				$powerPlantPVEquipment = class_exists('ProductInverter')
					&& method_exists('ProductInverter', 'fetchByProduct')
					&& method_exists('ProductInverter', 'saveForProduct')
					&& class_exists('PowerPlantPVProductImport')
					&& method_exists('PowerPlantPVProductImport', 'fetchPvPanel')
					&& method_exists('PowerPlantPVProductImport', 'importModuleToProduct');
			}
		}

		return array(
			'module_base' => array(
				'label' => 'CompatibilityFeatureModuleBase',
				'description' => 'CompatibilityFeatureModuleBaseDescription',
				'min_dolibarr' => '20.0.0',
				'core_available_from' => '20.0.0',
				'module_available_from' => '20.0.0',
				'min_php' => '8.0.0',
				'compatibility_check' => "version_compare(DOL_VERSION, '20.0.0', '>=') && version_compare(PHP_VERSION, '8.0.0', '>=')",
				'available' => $dolibarr20 && $php80,
				'reason' => ($dolibarr20 && $php80) ? '' : 'RequiresDolibarr20Php80',
			),
			'native_helpers_v20' => array(
				'label' => 'CompatibilityFeatureNativeHelpers',
				'description' => 'CompatibilityFeatureNativeHelpersDescription',
				'min_dolibarr' => '20.0.0',
				'core_available_from' => '20.0.0',
				'module_available_from' => '20.0.0',
				'min_php' => '8.0.0',
				'compatibility_check' => "function_exists('getDolGlobalInt') && function_exists('isModEnabled')",
				'available' => function_exists('getDolGlobalInt') && function_exists('isModEnabled'),
				'reason' => (function_exists('getDolGlobalInt') && function_exists('isModEnabled')) ? '' : 'RequiresDolibarr20NativeHelpers',
			),
			'native_email_templates' => array(
				'label' => 'CompatibilityFeatureNativeEmailTemplates',
				'description' => 'CompatibilityFeatureNativeEmailTemplatesDescription',
				'min_dolibarr' => '20.0.0',
				'core_available_from' => '20.0.0',
				'module_available_from' => '20.0.0',
				'min_php' => '8.0.0',
				'compatibility_check' => "is_readable(DOL_DOCUMENT_ROOT.'/core/class/html.formmail.class.php')",
				'available' => $nativeEmailTemplates,
				'reason' => $nativeEmailTemplates ? '' : 'RequiresNativeEmailTemplates',
			),
			'native_document_models' => array(
				'label' => 'CompatibilityFeatureNativeDocumentModels',
				'description' => 'CompatibilityFeatureNativeDocumentModelsDescription',
				'min_dolibarr' => '20.0.0',
				'core_available_from' => '20.0.0',
				'module_available_from' => '20.0.0',
				'min_php' => '8.0.0',
				'compatibility_check' => "is_readable(DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php')",
				'available' => $nativeDocumentModels,
				'reason' => $nativeDocumentModels ? '' : 'RequiresNativeDocumentModels',
			),
			'powerplantpv_equipment' => array(
				'label' => 'CompatibilityFeaturePowerPlantPVEquipment',
				'description' => 'CompatibilityFeaturePowerPlantPVEquipmentDescription',
				'min_dolibarr' => '20.0.0',
				'core_available_from' => '20.0.0',
				'module_available_from' => '20.0.0',
				'min_php' => '8.0.0',
				'compatibility_check' => "isModEnabled('powerplantpv') && ProductInverter/PowerPlantPVProductImport APIs are available",
				'available' => $powerPlantPVEquipment,
				'reason' => $powerPlantPVEquipment ? '' : 'RequiresPowerPlantPVEquipmentAPIs',
			),
		);
	}

	/**
	 * Test feature availability.
	 *
	 * @param string $code Feature code
	 * @return bool
	 */
	public static function isFeatureAvailable($code)
	{
		$features = self::getCompatibilityFeatures();

		return !empty($features[$code]['available']);
	}

	/**
	 * Return unavailable features.
	 *
	 * @return array<string, CompatibilityFeature>
	 */
	public static function getUnavailableFeatures()
	{
		$unavailable = array();

		foreach (self::getCompatibilityFeatures() as $code => $feature) {
			if (empty($feature['available'])) {
				$unavailable[$code] = $feature;
			}
		}

		return $unavailable;
	}
}
