CREATE TABLE IF NOT EXISTS llx_pvproc_signature
(
	rowid integer AUTO_INCREMENT PRIMARY KEY,
	entity integer DEFAULT 1 NOT NULL,
	fk_raccordement integer NOT NULL,
	type_signature varchar(64),
	signataire_nom varchar(255),
	signataire_fonction varchar(255),
	signataire_email varchar(255),
	signature_date datetime,
	signature_ip varchar(64),
	signature_user_agent varchar(255),
	pdf_hash varchar(128),
	filepath varchar(255),
	filename varchar(255),
	status integer DEFAULT 0,
	date_validation datetime,
	fk_user_valid integer,
	motif_non_conformite text,
	datec datetime,
	tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	import_key varchar(14),
	KEY idx_pvproc_signature_entity (entity),
	KEY idx_pvproc_signature_fk_raccordement (fk_raccordement),
	KEY idx_pvproc_signature_type_signature (type_signature),
	KEY idx_pvproc_signature_status (status),
	KEY idx_pvproc_signature_pdf_hash (pdf_hash)
) ENGINE=innodb;

