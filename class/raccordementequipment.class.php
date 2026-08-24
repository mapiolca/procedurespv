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
	/** @var list<string> Fields updated on the parent raccordement */
	public $changedFields = array();

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

	/**
	 * Fetch equipment attached to the PowerPlantPV plant linked to the raccordement.
	 *
	 * These rows are copied to the normalized raccordement equipment table when
	 * the collection is validated. They also remain available as form defaults
	 * for collections validated before that synchronization was introduced.
	 *
	 * @param Raccordement $raccordement Grid connection
	 * @param string $type Equipment type
	 * @return array<int,array{id:int,entity:int,fk_raccordement:int,fk_product:int,type:string,quantity:int,unit_power:float,ref:string,label:string}>
	 */
	public function fetchConfirmedPowerPlantLines($raccordement, $type)
	{
		$this->error = '';
		$rows = array();
		if (
			!isModEnabled('powerplantpv')
			|| !is_object($raccordement)
			|| (int) $raccordement->fk_centrale_pv <= 0
			|| empty($raccordement->date_collecte_soumission)
			|| getDolGlobalInt('PROCEDURESPV_PREFILL_FROM_CENTRALEPV', 1) <= 0
			|| !in_array($type, array(self::TYPE_INVERTER, self::TYPE_MODULE), true)
		) {
			return $rows;
		}

		$category = $type === self::TYPE_INVERTER ? 'ONDULE' : 'MODULE';
		$technicalTable = $type === self::TYPE_INVERTER ? 'powerplantpv_product_inverter' : 'powerplantpv_product_pvpanel';
		$powerField = $type === self::TYPE_INVERTER ? 'ac_apparent_power' : 'pmax';
		$powerPlantEntities = getEntity('powerplant');
		$productEntities = getEntity('product');

		$sql = 'SELECT MIN(pc.rowid) AS rowid, pc.entity, pc.fk_product, SUM(pc.qty) AS quantity,';
		$sql .= ' p.ref, p.label, tech.'.$powerField.' AS unit_power';
		$sql .= ' FROM '.MAIN_DB_PREFIX.'powerplantpv_powerplantcomp AS pc';
		$sql .= ' INNER JOIN '.MAIN_DB_PREFIX.'powerplantpv_powerplant AS pp ON pp.rowid = pc.fk_powerplant AND pp.entity = pc.entity';
		$sql .= ' INNER JOIN '.MAIN_DB_PREFIX.'product AS p ON p.rowid = pc.fk_product';
		$sql .= ' INNER JOIN '.MAIN_DB_PREFIX.'product_extrafields AS pe ON pe.fk_object = p.rowid';
		$sql .= ' INNER JOIN '.MAIN_DB_PREFIX.'c_powerplantpv_categorypv AS category ON category.rowid = pe.categorie_photovoltaique';
		$sql .= ' LEFT JOIN '.MAIN_DB_PREFIX.$technicalTable.' AS tech ON tech.rowid = (';
		$sql .= 'SELECT technical.rowid FROM '.MAIN_DB_PREFIX.$technicalTable.' AS technical';
		$sql .= ' WHERE technical.fk_product = p.rowid AND technical.entity IN ('.$productEntities.')';
		$sql .= ' ORDER BY technical.entity DESC, technical.rowid ASC'.$this->db->plimit(1).')';
		$sql .= ' WHERE pc.fk_powerplant = '.((int) $raccordement->fk_centrale_pv);
		$sql .= ' AND pc.entity IN ('.$powerPlantEntities.')';
		$sql .= ' AND pp.entity IN ('.$powerPlantEntities.')';
		$sql .= ' AND p.entity IN ('.$productEntities.')';
		$sql .= ' AND (pc.fk_status IS NULL OR pc.fk_status <> 6)';
		$sql .= " AND category.active = 1 AND category.code = '".$this->db->escape($category)."'";
		$sql .= ' GROUP BY pc.entity, pc.fk_product, p.ref, p.label, tech.'.$powerField;
		$sql .= ' ORDER BY p.ref ASC, pc.fk_product ASC';

		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return $rows;
		}

		while (is_object($obj = $this->db->fetch_object($resql))) {
			$quantity = max(1, (int) $obj->quantity);
			$rows[] = array(
				'id' => (int) $obj->rowid,
				'entity' => (int) $obj->entity,
				'fk_raccordement' => (int) $raccordement->id,
				'fk_product' => (int) $obj->fk_product,
				'type' => $type,
				'quantity' => $quantity,
				'unit_power' => isset($obj->unit_power) ? (float) $obj->unit_power : 0.0,
				'ref' => isset($obj->ref) ? (string) $obj->ref : '',
				'label' => isset($obj->label) ? (string) $obj->label : '',
			);
		}
		$this->db->free($resql);

		return $rows;
	}

	/**
	 * Persist missing ENEDIS request equipment from the confirmed PowerPlantPV plant.
	 *
	 * The source rows are copied with a power snapshot and never modify the
	 * product catalog. Collection validation can replace the earlier defaults
	 * populated when the power plant was associated.
	 *
	 * @param Raccordement $raccordement Grid connection
	 * @param User $user Acting user
	 * @param bool $replaceExisting Replace existing defaults with values confirmed by the collection
	 * @param bool $manageTransaction Manage the database transaction locally
	 * @return int 1 when rows were copied, 0 when no prefill is needed, -1 on error
	 */
	public function prefillFromConfirmedPowerPlant($raccordement, $user, $replaceExisting = false, $manageTransaction = true)
	{
		$this->error = '';
		$this->errors = array();
		$this->changedFields = array();
		if (
			!is_object($raccordement)
			|| (int) $raccordement->id <= 0
			|| (int) $raccordement->fk_centrale_pv <= 0
			|| empty($raccordement->date_collecte_soumission)
			|| getDolGlobalInt('PROCEDURESPV_PREFILL_FROM_CENTRALEPV', 1) <= 0
		) {
			return 0;
		}
		if (!isModEnabled('powerplantpv')) {
			$this->error = 'PowerPlantPVRequiredForEquipmentPrefill';
			return -1;
		}
		if (!is_object($user) || (int) $user->id <= 0) {
			$this->error = 'NotEnoughPermissions';
			return -1;
		}

		$hasHistoricalInverters = trim((string) $raccordement->onduleurs) !== '' || (int) $raccordement->nombre_onduleurs > 0;
		$hasHistoricalModules = trim((string) $raccordement->modules) !== '' || (int) $raccordement->nombre_modules > 0;
		$existingInverters = array();
		$existingModules = array();
		if (!$replaceExisting) {
			$existingInverters = $this->fetchLines((int) $raccordement->id, self::TYPE_INVERTER);
			if ($this->error !== '') {
				return -1;
			}
			$existingModules = $this->fetchLines((int) $raccordement->id, self::TYPE_MODULE);
			if ($this->error !== '') {
				return -1;
			}
		}

		$invertersToCopy = array();
		$modulesToCopy = array();
		if ($replaceExisting || (empty($existingInverters) && !$hasHistoricalInverters)) {
			$invertersToCopy = $this->fetchConfirmedPowerPlantLines($raccordement, self::TYPE_INVERTER);
			if ($this->error !== '') {
				return -1;
			}
		}
		if ($replaceExisting || (empty($existingModules) && !$hasHistoricalModules)) {
			$modulesToCopy = $this->fetchConfirmedPowerPlantLines($raccordement, self::TYPE_MODULE);
			if ($this->error !== '') {
				return -1;
			}
		}
		if (!$replaceExisting && empty($invertersToCopy) && empty($modulesToCopy)) {
			return 0;
		}

		if ($manageTransaction) {
			$this->db->begin();
		}
		$inserted = 0;
		if ($replaceExisting && $this->deleteLinesByType($raccordement, self::TYPE_INVERTER) < 0) {
			$inserted = -1;
		}
		if ($inserted >= 0) {
			$inserted = $this->insertConfirmedLines($raccordement, $invertersToCopy, $user);
		}
		if ($inserted >= 0 && $replaceExisting && $this->deleteLinesByType($raccordement, self::TYPE_MODULE) < 0) {
			$inserted = -1;
		}
		if ($inserted >= 0) {
			$moduleInserted = $this->insertConfirmedLines($raccordement, $modulesToCopy, $user);
			$inserted = $moduleInserted < 0 ? -1 : $inserted + $moduleInserted;
		}
		$recalculated = 0;
		if ($inserted >= 0 && ($replaceExisting || $inserted > 0)) {
			$recalculated = $this->recalculate($raccordement, $user, !$replaceExisting);
		}
		if ($inserted < 0 || $recalculated < 0) {
			if ($manageTransaction) {
				$this->db->rollback();
			}
			return -1;
		}
		if ($manageTransaction) {
			$this->db->commit();
		}

		return ($inserted > 0 || $recalculated > 0) ? 1 : 0;
	}

	/** @param Raccordement $raccordement Parent object @param string $type Equipment type @return int */
	private function deleteLinesByType($raccordement, $type)
	{
		$sql = 'DELETE FROM '.MAIN_DB_PREFIX.'pvproc_raccordement_equipment';
		$sql .= ' WHERE entity = '.((int) $raccordement->entity).' AND fk_raccordement = '.((int) $raccordement->id);
		$sql .= ' AND equipment_type = \''.$this->db->escape($type).'\'';
		if (!$this->db->query($sql)) {
			$this->error = $this->db->lasterror();
			return -1;
		}

		return 1;
	}

	/**
	 * @param Raccordement $raccordement Parent object
	 * @param array<int,array<string,mixed>> $lines Confirmed source lines
	 * @param User $user Acting user
	 * @return int Number of inserted rows, -1 on error
	 */
	private function insertConfirmedLines($raccordement, array $lines, $user)
	{
		$inserted = 0;
		foreach ($lines as $line) {
			$productId = isset($line['fk_product']) ? (int) $line['fk_product'] : 0;
			$type = isset($line['type']) ? (string) $line['type'] : '';
			$quantity = isset($line['quantity']) ? max(1, (int) $line['quantity']) : 0;
			$unitPower = isset($line['unit_power']) ? price2num((string) $line['unit_power'], 'MU') : 0;
			if ($productId <= 0 || $quantity <= 0 || !in_array($type, array(self::TYPE_INVERTER, self::TYPE_MODULE), true)) {
				continue;
			}

			$sql = 'INSERT INTO '.MAIN_DB_PREFIX.'pvproc_raccordement_equipment (entity, fk_raccordement, fk_product, equipment_type, quantity, unit_power_snapshot, datec, fk_user_creat)';
			$sql .= ' SELECT '.((int) $raccordement->entity).', '.((int) $raccordement->id).', '.$productId.', \''.$this->db->escape($type).'\', '.$quantity.', '.((float) $unitPower).', \''.$this->db->idate(dol_now()).'\', '.((int) $user->id);
			$sql .= ' FROM DUAL WHERE NOT EXISTS (SELECT rowid FROM '.MAIN_DB_PREFIX.'pvproc_raccordement_equipment';
			$sql .= ' WHERE entity = '.((int) $raccordement->entity).' AND fk_raccordement = '.((int) $raccordement->id).' AND fk_product = '.$productId.' AND equipment_type = \''.$this->db->escape($type).'\')';
			$resql = $this->db->query($sql);
			if (!$resql) {
				$this->error = $this->db->lasterror();
				return -1;
			}
			$inserted += max(0, (int) $this->db->affected_rows($resql));
		}

		return $inserted;
	}

	/**
	 * Save equipment values entered for a raccordement without a linked PV plant.
	 *
	 * Values entered without a linked plant replace normalized PowerPlantPV rows so the parent aggregates
	 * cannot subsequently be overwritten by stale equipment selections.
	 *
	 * @param Raccordement $raccordement Parent object
	 * @param array{onduleurs:string,nombre_onduleurs:int,puissance_onduleurs:string,modules:string,nombre_modules:int,puissance_installee_kwc:string} $values Posted local values
	 * @param User $user Acting user
	 * @return int
	 */
	public function saveLocalValues($raccordement, array $values, $user)
	{
		$this->error = '';
		$this->errors = array();
		$this->changedFields = array();
		if (!is_object($raccordement) || (int) $raccordement->id <= 0 || (int) $raccordement->fk_centrale_pv > 0) {
			$this->error = 'LocalEquipmentEntryRequiresLocalSiteSource';
			return -1;
		}
		if (!is_object($user) || (int) $user->id <= 0) {
			$this->error = 'NotEnoughPermissions';
			return -1;
		}

		$inverters = trim(dol_string_nohtmltag($values['onduleurs']));
		$inverterCount = max(0, (int) $values['nombre_onduleurs']);
		$inverterPower = price2num($values['puissance_onduleurs'], 'MU');
		$modules = trim(dol_string_nohtmltag($values['modules']));
		$moduleCount = max(0, (int) $values['nombre_modules']);
		$installedPower = price2num($values['puissance_installee_kwc'], 'MU');

		$this->db->begin();
		if ($this->deleteLinesByType($raccordement, self::TYPE_INVERTER) < 0
			|| $this->deleteLinesByType($raccordement, self::TYPE_MODULE) < 0) {
			$this->db->rollback();
			return -1;
		}

		$raccordement->onduleurs = $inverters;
		$raccordement->references_onduleurs = $inverters;
		$raccordement->nombre_onduleurs = $inverterCount;
		$raccordement->puissance_onduleurs = $inverterPower;
		$raccordement->modules = $modules;
		$raccordement->nombre_modules = $moduleCount;
		$raccordement->puissance_installee_kwc = $installedPower;
		// A local total does not prove that every module has the same nominal power.
		$raccordement->puissance_unitaire_modules = null;
		$this->changedFields = array(
			'onduleurs',
			'references_onduleurs',
			'nombre_onduleurs',
			'puissance_onduleurs',
			'modules',
			'nombre_modules',
			'puissance_unitaire_modules',
			'puissance_installee_kwc',
		);
		$raccordement->context['trigger_reason'] = 'equipment_changed';
		$raccordement->context['changed_fields'] = $this->changedFields;
		if ($raccordement->update($user, 1) < 0) {
			$this->error = $raccordement->error;
			$this->errors = $raccordement->errors;
			$this->db->rollback();
			return -1;
		}

		$this->db->commit();
		return 1;
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

	/**
	 * @param Raccordement $raccordement Parent
	 * @param User $user User
	 * @param bool $preserveMissingTypes Keep historical aggregates for equipment types without normalized lines
	 * @return int
	 */
	public function recalculate($raccordement, $user, $preserveMissingTypes = false)
	{
		$this->changedFields = array();
		$inverterCount = 0;
		$inverterVa = 0.0;
		$moduleCount = 0;
		$moduleWc = 0.0;
		$modulePowers = array();
		$inverterRefs = array();
		$moduleRefs = array();
		$hasInverterLines = false;
		$hasModuleLines = false;
		foreach ($this->fetchLines((int) $raccordement->id) as $line) {
			$quantity = (int) $line['quantity'];
			$unitPower = (float) $line['unit_power'];
			$cacheLabel = trim((string) $line['ref'].' - '.(string) $line['label']).' × '.$quantity;
			if ($line['type'] === self::TYPE_INVERTER) {
				$hasInverterLines = true;
				$inverterCount += $quantity;
				$inverterVa += $quantity * $unitPower;
				$inverterRefs[] = $cacheLabel;
			} else {
				$hasModuleLines = true;
				$moduleCount += $quantity;
				$moduleWc += $quantity * $unitPower;
				$modulePowers[(string) price2num($unitPower, 'MU')] = $unitPower;
				$moduleRefs[] = $cacheLabel;
			}
		}
		$changedFields = array();
		if (!$preserveMissingTypes || $hasInverterLines) {
			$raccordement->nombre_onduleurs = $inverterCount;
			$raccordement->puissance_onduleurs = price2num($inverterVa / 1000, 'MU');
			$raccordement->onduleurs = implode("\n", $inverterRefs);
			$raccordement->references_onduleurs = implode(', ', array_map(static function ($line) { return preg_replace('/\s+×.*$/u', '', $line); }, $inverterRefs));
			$changedFields = array_merge($changedFields, array('onduleurs', 'nombre_onduleurs', 'references_onduleurs', 'puissance_onduleurs'));
		}
		if (!$preserveMissingTypes || $hasModuleLines) {
			$raccordement->nombre_modules = $moduleCount;
			$raccordement->puissance_installee_kwc = price2num($moduleWc / 1000, 'MU');
			$raccordement->puissance_unitaire_modules = count($modulePowers) === 1 ? reset($modulePowers) : null;
			$raccordement->modules = implode("\n", $moduleRefs);
			$changedFields = array_merge($changedFields, array('modules', 'nombre_modules', 'puissance_unitaire_modules', 'puissance_installee_kwc'));
		}
		if (empty($changedFields)) {
			return 0;
		}
		$this->changedFields = array_values(array_unique($changedFields));
		$raccordement->context['trigger_reason'] = 'equipment_changed';
		$raccordement->context['changed_fields'] = $this->changedFields;
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
