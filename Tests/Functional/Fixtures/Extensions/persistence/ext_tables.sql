#
# Table structure for table 'tx_persistence_entity'
#
CREATE TABLE tx_persistence_entity (
	title tinytext,

	scalar_string varchar(255) DEFAULT '' NOT NULL,
	scalar_float float(7,4) DEFAULT '0' NOT NULL,
	scalar_integer int(11) DEFAULT '0' NOT NULL,
	scalar_text text,

	relation_inline_11_file_reference int(11) DEFAULT '0' NOT NULL,
	relation_inline_1n_file_reference int(11) DEFAULT '0' NOT NULL,
	relation_inline_1n_csv_file_reference text,
	relation_inline_mn_mm_content int(11) DEFAULT '0' NOT NULL,
	relation_inline_mn_symmetric_entity int(11) DEFAULT '0' NOT NULL,
	relation_select_1n_page text,
	relation_select_mn_csv_category text,
	relation_select_mn_mm_content text,
	relation_group_1n_content_page text,
	relation_group_mn_csv_content_page text,
	relation_group_mn_csv_any text,
	relation_group_mn_mm_content_page text,
	relation_group_mn_mm_any text
);

#
# Table structure for table 'tx_persistence_entity_mm'
#
CREATE TABLE tx_persistence_entity_mm (
	uid_local int(11) DEFAULT '0' NOT NULL,
	uid_foreign int(11) DEFAULT '0' NOT NULL,
	tablenames varchar(255) DEFAULT '' NOT NULL,
	fieldname varchar(255) DEFAULT '' NOT NULL,
	sorting int(11) DEFAULT '0' NOT NULL,
	sorting_foreign int(11) DEFAULT '0' NOT NULL,

	further tinyint(2) DEFAULT '0' NOT NULL,

	KEY uid_local_foreign (uid_local,uid_foreign),
	KEY uid_foreign_tablefield (uid_foreign,tablenames(40),fieldname(3),sorting_foreign)
);

#
# Table structure for table 'tx_persistence_entity_symmetric'
#
CREATE TABLE tx_persistence_entity_symmetric (
	entity int(11) DEFAULT '0' NOT NULL,
	peer int(11) DEFAULT '0' NOT NULL,
	sorting_entity int(10) DEFAULT '0' NOT NULL,
	sorting_peer int(10) DEFAULT '0' NOT NULL
);
