CREATE TABLE IF NOT EXISTS llx_pvproc_piece
(
	rowid integer AUTO_INCREMENT PRIMARY KEY,
	entity integer DEFAULT 1 NOT NULL,
	fk_raccordement integer NOT NULL,
	code_piece varchar(64),
	label varchar(255),
	origin varchar(32),
	required integer DEFAULT 0,
	status integer DEFAULT 0,
	filepath varchar(255),
	filename varchar(255),
	fk_user_valid integer,
	date_validation datetime,
	motif_refus text,
	commentaire text,
	datec datetime,
	tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	import_key varchar(14),
	KEY idx_pvproc_piece_entity (entity),
	KEY idx_pvproc_piece_fk_raccordement (fk_raccordement),
	KEY idx_pvproc_piece_code_piece (code_piece),
	KEY idx_pvproc_piece_origin (origin),
	KEY idx_pvproc_piece_status (status)
) ENGINE=innodb;

