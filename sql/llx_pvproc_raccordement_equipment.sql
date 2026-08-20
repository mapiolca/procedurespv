CREATE TABLE IF NOT EXISTS llx_pvproc_raccordement_equipment
(
	rowid integer AUTO_INCREMENT PRIMARY KEY,
	entity integer DEFAULT 1 NOT NULL,
	fk_raccordement integer NOT NULL,
	fk_product integer NOT NULL,
	equipment_type varchar(16) NOT NULL,
	quantity integer DEFAULT 1 NOT NULL,
	unit_power_snapshot double(24,8) NOT NULL,
	datec datetime,
	tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	fk_user_creat integer,
	fk_user_modif integer,
	import_key varchar(14),
	UNIQUE KEY uk_pvproc_equipment_product (entity, fk_raccordement, fk_product, equipment_type),
	KEY idx_pvproc_equipment_entity (entity),
	KEY idx_pvproc_equipment_raccordement (fk_raccordement),
	KEY idx_pvproc_equipment_product (fk_product),
	KEY idx_pvproc_equipment_type (equipment_type)
) ENGINE=innodb;
