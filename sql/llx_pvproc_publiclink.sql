CREATE TABLE IF NOT EXISTS llx_pvproc_publiclink
(
	rowid integer AUTO_INCREMENT PRIMARY KEY,
	entity integer DEFAULT 1 NOT NULL,
	fk_raccordement integer NOT NULL,
	type_link varchar(64) NOT NULL,
	token_hash varchar(255) NOT NULL,
	email_destinataire varchar(255),
	date_creation datetime,
	date_expiration datetime,
	date_first_access datetime,
	date_last_access datetime,
	date_submit datetime,
	ip_last_access varchar(64),
	user_agent_last_access varchar(255),
	nb_access integer DEFAULT 0,
	status integer DEFAULT 0,
	datec datetime,
	tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	import_key varchar(14),
	UNIQUE KEY uk_pvproc_publiclink_token_hash (token_hash),
	KEY idx_pvproc_publiclink_entity (entity),
	KEY idx_pvproc_publiclink_fk_raccordement (fk_raccordement),
	KEY idx_pvproc_publiclink_type_link (type_link),
	KEY idx_pvproc_publiclink_status (status),
	KEY idx_pvproc_publiclink_date_expiration (date_expiration)
) ENGINE=innodb;

