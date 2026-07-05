CREATE TABLE IF NOT EXISTS llx_pvproc_relance
(
	rowid integer AUTO_INCREMENT PRIMARY KEY,
	entity integer DEFAULT 1 NOT NULL,
	fk_raccordement integer NOT NULL,
	type_relance varchar(64),
	target_type varchar(64),
	target_id integer,
	destinataire_email varchar(255),
	date_prevue datetime,
	date_envoi datetime,
	canal varchar(32),
	status integer DEFAULT 0,
	modele_utilise varchar(128),
	resultat text,
	commentaire text,
	fk_actioncomm integer,
	datec datetime,
	tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	import_key varchar(14),
	KEY idx_pvproc_relance_entity (entity),
	KEY idx_pvproc_relance_fk_raccordement (fk_raccordement),
	KEY idx_pvproc_relance_type_relance (type_relance),
	KEY idx_pvproc_relance_target (target_type, target_id),
	KEY idx_pvproc_relance_status (status),
	KEY idx_pvproc_relance_date_prevue (date_prevue),
	KEY idx_pvproc_relance_fk_actioncomm (fk_actioncomm)
) ENGINE=innodb;

