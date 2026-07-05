CREATE TABLE IF NOT EXISTS llx_pvproc_convention
(
	rowid integer AUTO_INCREMENT PRIMARY KEY,
	entity integer DEFAULT 1 NOT NULL,
	fk_raccordement integer NOT NULL,
	type_convention varchar(64),
	ref_convention varchar(128),
	status integer DEFAULT 0,
	date_reception datetime,
	date_envoi_client datetime,
	date_signature_client datetime,
	date_retour_enedis datetime,
	date_validation datetime,
	document_recu varchar(255),
	document_signe varchar(255),
	commentaire text,
	datec datetime,
	tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	import_key varchar(14),
	KEY idx_pvproc_convention_entity (entity),
	KEY idx_pvproc_convention_fk_raccordement (fk_raccordement),
	KEY idx_pvproc_convention_type_convention (type_convention),
	KEY idx_pvproc_convention_ref_convention (ref_convention),
	KEY idx_pvproc_convention_status (status)
) ENGINE=innodb;

