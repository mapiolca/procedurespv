<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

/** Normalized photovoltaic equipment selected for a raccordement. */
class RaccordementEquipment
{
	public const TYPE_INVERTER = 'inverter';
	public const TYPE_MODULE = 'module';

	/** @var DoliDB */
	private $db;
	public $error = '';
	public $errors = array();

	/** @param DoliDB $db Database handler */
	public function __construct($db)
	{
		$this->db = $db;
	}

	/** @return bool */
	public static function isAvailable()
	{
		if (!isModEnabled('powerplantpv')
			|| !is_readable(dol_buildpath('/powerplantpv/class/productinverter.class.php', 0))
			|| !is_readable(dol_buildpath('/powerplantpv/class/powerplantpvproductimport.class.php', 0))) {
			return false;
		}

		dol_include_once('/powerplantpv/class/productinverter.class.php');
		dol_include_once('/powerplantpv/class/powerplantpvproductimport.class.php');

		return class_exists('ProductInverter')
			&& method_exists('ProductInverter', 'fetchByProduct')
			&& method_exists('ProductInverter', 'saveForProduct')
			&& class_exists('PowerPlantPVProductImport')
			&& method_exists('PowerPlantPVProductImport', 'fetchPvPanel')
			&& method_exists('PowerPlantPVProductImport', 'importModuleToProduct');
	}

	/**
	 * @param string $type Equipment type
	 * @param string $term Search term
	 * @param int $limit Maximum rows
	 * @return array<int,array{id:int,text:string,ref:string,label:string,power:float}>
	 */
	public function searchProducts($type, $term = '', $limit = 30)
	{
		$rows = array();
		$category = $type === self::TYPE_INVERTER ? 'ONDULE' : 'MODULE';
		$sql = 'SELECT p.rowid, p.ref, p.label FROM '.MAIN_DB_PREFIX.'product AS p';
		$sql .= ' INNER JOIN '.MAIN_DB_PREFIX.'product_extrafields AS pe ON pe.fk_object = p.rowid';
		$sql .= ' INNER JOIN '.MAIN_DB_PREFIX.'c_powerplantpv_categorypv AS c ON c.rowid = pe.categorie_photovoltaique';
		$sql .= ' WHERE p.entity IN ('.getEntity('product').') AND c.active = 1 AND c.code = \''.$this->db->escape($category).'\'';
		if ($term !== '') {
			$sql .= natural_search(array('p.ref', 'p.label'), $term);
		}
		$sql .= ' ORDER BY p.ref ASC'.$this->db->plimit(max(1, min(100, (int) $limit)));
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return $rows;
		}
		while (is_object($obj = $this->db->fetch_object($resql))) {
			$power = $this->fetchCatalogPower((int) $obj->rowid, $type);
			$rows[] = array('id' => (int) $obj->rowid, 'text' => trim((string) $obj->ref.' - '.(string) $obj->label), 'ref' => (string) $obj->ref, 'label' => (string) $obj->label, 'power' => $power > 0 ? $power : 0.0);
		}
		return $rows;
	}

	/**
	 * @param int $fkRaccordement Raccordement id
	 * @param string|null $type Optional type
	 * @return array<int,array<string,mixed>>
	 */
	public function fetchLines($fkRaccordement, $type = null)
	{
		global $conf;
		$rows = array();
		$entityFilter = function_exists('getEntity') ? getEntity('procedurespv_raccordement') : (string) ((int) $conf->entity);
		$sql = 'SELECT e.rowid, e.entity, e.fk_raccordement, e.fk_product, e.equipment_type, e.quantity, e.unit_power_snapshot, p.ref, p.label';
		$sql .= ' FROM '.MAIN_DB_PREFIX.'pvproc_raccordement_equipment AS e LEFT JOIN '.MAIN_DB_PREFIX.'product AS p ON p.rowid = e.fk_product';
		$sql .= ' WHERE e.fk_raccordement = '.((int) $fkRaccordement).' AND e.entity IN ('.$entityFilter.')';
		if ($type !== null) {
			$sql .= ' AND e.equipment_type = \''.$this->db->escape($type).'\'';
		}
		$sql .= ' ORDER BY e.equipment_type, p.ref, e.rowid';
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return $rows;
		}
		while (is_object($obj = $this->db->fetch_object($resql))) {
			$rows[] = array('id' => (int) $obj->rowid, 'entity' => (int) $obj->entity, 'fk_raccordement' => (int) $obj->fk_raccordement, 'fk_product' => (int) $obj->fk_product, 'type' => (string) $obj->equipment_type, 'quantity' => (int) $obj->quantity, 'unit_power' => (float) $obj->unit_power_snapshot, 'ref' => isset($obj->ref) ? (string) $obj->ref : '', 'label' => isset($obj->label) ? (string) $obj->label : '');
		}
		return $rows;
	}

	/** @param int $fkRaccordement Raccordement id @return bool */
	public function hasModules($fkRaccordement)
	{
		global $conf;
		$entityFilter = function_exists('getEntity') ? getEntity('procedurespv_raccordement') : (string) ((int) $conf->entity);
		$sql = 'SELECT rowid FROM '.MAIN_DB_PREFIX.'pvproc_raccordement_equipment WHERE fk_raccordement = '.((int) $fkRaccordement).' AND entity IN ('.$entityFilter.') AND equipment_type = \''.self::TYPE_MODULE.'\''.$this->db->plimit(1);
		$resql = $this->db->query($sql);
		return $resql && is_object($this->db->fetch_object($resql));
	}

	/**
	 * Persist both selections atomically and recalculate parent aggregates.
	 *
	 * @param Raccordement $raccordement Parent object
	 * @param array<int,int> $inverters Product id => quantity
	 * @param array<int,int> $modules Product id => quantity
	 * @param array<string,float|int|string> $manualPowers Keys inverter_ID/module_ID
	 * @param User $user Current user
	 * @return int
	 */
	public function saveSelections($raccordement, array $inverters, array $modules, array $manualPowers, $user)
	{
		if (!self::isAvailable()) {
			$this->error = 'EquipmentManagementUnavailable';
			return -1;
		}
		if (!is_object($user) || (empty($user->admin) && !$user->hasRight('produit', 'lire'))) {
			$this->error = 'NotEnoughPermissionsToReadProductCatalog';
			return -1;
		}
		$this->db->begin();
		if ($this->saveType($raccordement, self::TYPE_INVERTER, $inverters, $manualPowers, $user) < 0
			|| $this->saveType($raccordement, self::TYPE_MODULE, $modules, $manualPowers, $user) < 0
			|| $this->recalculate($raccordement, $user) < 0) {
			$this->db->rollback();
			return -1;
		}
		$this->db->commit();
		return 1;
	}

	/**
	 * @param Raccordement $raccordement Parent object
	 * @param string $type Type
	 * @param array<int,int> $selection Product => quantity
	 * @param array<string,float|int|string> $manualPowers Manual powers
	 * @param User $user User
	 * @return int
	 */
	private function saveType($raccordement, $type, array $selection, array $manualPowers, $user)
	{
		$existing = array();
		foreach ($this->fetchLines((int) $raccordement->id, $type) as $line) {
			$existing[(int) $line['fk_product']] = $line;
		}
		$kept = array();
		foreach ($selection as $productId => $quantity) {
			$productId = (int) $productId;
			$quantity = (int) $quantity;
			if ($productId <= 0 || $quantity <= 0 || !$this->productMatchesType($productId, $type)) {
				$this->error = 'InvalidEquipmentSelection';
				return -1;
			}
			$power = isset($existing[$productId]) ? (float) $existing[$productId]['unit_power'] : $this->fetchCatalogPower($productId, $type);
			if ($power <= 0) {
				$key = $type.'_'.$productId;
				$manualPower = isset($manualPowers[$key]) ? (float) price2num($manualPowers[$key], 'MU') : 0.0;
				if ($manualPower <= 0 || !$this->canUpdateTechnicalProduct($user)) {
					$this->error = $manualPower <= 0 ? 'EquipmentPowerRequired' : 'NotEnoughPermissionsToUpdateEquipmentPower';
					return -1;
				}
				$power = $type === self::TYPE_INVERTER ? $manualPower * 1000 : $manualPower;
				if ($this->updateCatalogPower($productId, $type, $power, $user) < 0) {
					return -1;
				}
				$power = $this->fetchCatalogPower($productId, $type);
			}
			if ($power <= 0) {
				$this->error = 'EquipmentPowerRequired';
				return -1;
			}
			$kept[] = $productId;
			if (isset($existing[$productId])) {
				$sql = 'UPDATE '.MAIN_DB_PREFIX.'pvproc_raccordement_equipment SET quantity = '.$quantity.', fk_user_modif = '.((int) $user->id).' WHERE rowid = '.((int) $existing[$productId]['id']);
			} else {
				$sql = 'INSERT INTO '.MAIN_DB_PREFIX.'pvproc_raccordement_equipment (entity, fk_raccordement, fk_product, equipment_type, quantity, unit_power_snapshot, datec, fk_user_creat) VALUES (';
				$sql .= ((int) $raccordement->entity).', '.((int) $raccordement->id).', '.$productId.', \''.$this->db->escape($type).'\', '.$quantity.', '.((float) $power).', \''.$this->db->idate(dol_now()).'\', '.((int) $user->id).')';
			}
			if (!$this->db->query($sql)) {
				$this->error = $this->db->lasterror();
				return -1;
			}
		}
		$sql = 'DELETE FROM '.MAIN_DB_PREFIX.'pvproc_raccordement_equipment WHERE entity = '.((int) $raccordement->entity).' AND fk_raccordement = '.((int) $raccordement->id).' AND equipment_type = \''.$this->db->escape($type).'\'';
		if (!empty($kept)) {
			$sql .= ' AND fk_product NOT IN ('.implode(',', array_map('intval', $kept)).')';
		}
		if (!$this->db->query($sql)) {
			$this->error = $this->db->lasterror();
			return -1;
		}
		return 1;
	}

	/** @param Raccordement $raccordement Parent @param User $user User @return int */
	public function recalculate($raccordement, $user)
	{
		$inverterCount = 0;
		$inverterVa = 0.0;
		$moduleCount = 0;
		$moduleWc = 0.0;
		$modulePowers = array();
		$inverterRefs = array();
		$moduleRefs = array();
		foreach ($this->fetchLines((int) $raccordement->id) as $line) {
			$quantity = (int) $line['quantity'];
			$unitPower = (float) $line['unit_power'];
			$cacheLabel = trim((string) $line['ref'].' - '.(string) $line['label']).' × '.$quantity;
			if ($line['type'] === self::TYPE_INVERTER) {
				$inverterCount += $quantity;
				$inverterVa += $quantity * $unitPower;
				$inverterRefs[] = $cacheLabel;
			} else {
				$moduleCount += $quantity;
				$moduleWc += $quantity * $unitPower;
				$modulePowers[(string) price2num($unitPower, 'MU')] = $unitPower;
				$moduleRefs[] = $cacheLabel;
			}
		}
		$raccordement->nombre_onduleurs = $inverterCount;
		$raccordement->puissance_onduleurs = price2num($inverterVa / 1000, 'MU');
		$raccordement->onduleurs = implode("\n", $inverterRefs);
		$raccordement->references_onduleurs = implode(', ', array_map(static function ($line) { return preg_replace('/\s+×.*$/u', '', $line); }, $inverterRefs));
		$raccordement->nombre_modules = $moduleCount;
		$raccordement->puissance_installee_kwc = price2num($moduleWc / 1000, 'MU');
		$raccordement->puissance_unitaire_modules = count($modulePowers) === 1 ? reset($modulePowers) : null;
		$raccordement->modules = implode("\n", $moduleRefs);
		$raccordement->context['trigger_reason'] = 'equipment_changed';
		$raccordement->context['changed_fields'] = array('onduleurs', 'nombre_onduleurs', 'references_onduleurs', 'puissance_onduleurs', 'modules', 'nombre_modules', 'puissance_unitaire_modules', 'puissance_installee_kwc');
		if ($raccordement->update($user, 1) < 0) {
			$this->error = $raccordement->error;
			return -1;
		}
		return 1;
	}

	/** @param int $productId Product id @param string $type Type @return bool */
	private function productMatchesType($productId, $type)
	{
		$category = $type === self::TYPE_INVERTER ? 'ONDULE' : 'MODULE';
		$sql = 'SELECT p.rowid FROM '.MAIN_DB_PREFIX.'product AS p INNER JOIN '.MAIN_DB_PREFIX.'product_extrafields AS pe ON pe.fk_object = p.rowid INNER JOIN '.MAIN_DB_PREFIX.'c_powerplantpv_categorypv AS c ON c.rowid = pe.categorie_photovoltaique';
		$sql .= ' WHERE p.rowid = '.((int) $productId).' AND p.entity IN ('.getEntity('product').') AND c.active = 1 AND c.code = \''.$this->db->escape($category).'\''.$this->db->plimit(1);
		$resql = $this->db->query($sql);
		return $resql && is_object($this->db->fetch_object($resql));
	}

	/** @param int $productId Product id @param string $type Type @return float */
	private function fetchCatalogPower($productId, $type)
	{
		if (!self::isAvailable()) {
			return 0.0;
		}
		if ($type === self::TYPE_INVERTER) {
			dol_include_once('/powerplantpv/class/productinverter.class.php');
			$inverter = new ProductInverter($this->db);
			return $inverter->fetchByProduct($productId) > 0 && isset($inverter->data['ac_apparent_power']) ? (float) $inverter->data['ac_apparent_power'] : 0.0;
		}
		dol_include_once('/powerplantpv/class/powerplantpvproductimport.class.php');
		$importer = new PowerPlantPVProductImport($this->db);
		$panel = $importer->fetchPvPanel($productId);
		return is_object($panel) && isset($panel->pmax) ? (float) $panel->pmax : 0.0;
	}

	/** @param User $user User @return bool */
	private function canUpdateTechnicalProduct($user)
	{
		return is_object($user) && (!empty($user->admin) || ($user->hasRight('produit', 'creer') && $user->hasRight('powerplantpv', 'powerplant', 'write')));
	}

	/** @param int $productId Product id @param string $type Type @param float $power Power in VA/Wc @param User $user User @return int */
	private function updateCatalogPower($productId, $type, $power, $user)
	{
		if ($type === self::TYPE_INVERTER) {
			dol_include_once('/powerplantpv/class/productinverter.class.php');
			$inverter = new ProductInverter($this->db);
			$found = $inverter->fetchByProduct($productId);
			if ($found < 0) {
				$this->error = $inverter->error;
				return -1;
			}
			$data = array();
			foreach (ProductInverter::getInverterFields() as $field => $spec) {
				$data[$field] = $found > 0 && array_key_exists($field, $inverter->data) ? $inverter->data[$field] : null;
			}
			$data['ac_apparent_power'] = $power;
			if ($inverter->saveForProduct($productId, $data, $user) < 0) {
				$this->error = $inverter->error;
				return -1;
			}
			return 1;
		}

		dol_include_once('/powerplantpv/class/powerplantpvproductimport.class.php');
		$importer = new PowerPlantPVProductImport($this->db);
		$raw = array('pmax' => $power, 'source' => 'Saisie manuelle ProceduresPV');
		$source = array('source' => 'procedurespv_manual', 'source_dataset' => 'module', 'source_key' => hash('sha256', 'procedurespv|'.$productId.'|'.$power), 'source_name' => 'Saisie manuelle ProceduresPV', 'source_url' => '', 'filename' => '', 'import_status' => 'imported');
		$result = $importer->importModuleToProduct($productId, array('pmax' => $power), $raw, $user, PowerPlantPVProductImport::STRATEGY_OVERWRITE_AFTER_CONFIRM, $source, array(), false);
		if (!isset($result['result']) || (int) $result['result'] < 0) {
			$this->error = $importer->error;
			$this->errors = $importer->errors;
			return -1;
		}
		return 1;
	}
}
