<?php
return [
    'ctrl' =>[
        'title' => 'Persistence Test Entity',
        'label' => 'title',
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'cruser_id' => 'cruser_id',
        'languageField' => 'sys_language_uid',
        'transOrigPointerField' => 'l10n_parent',
        'transOrigDiffSourceField' => 'l10n_diffsource',
        'prependAtCopy' => 'LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.prependAtCopy',
        'sortby' => 'sorting',
        'delete' => 'deleted',
        'enablecolumns' => [
            'disabled' => 'hidden',
            'starttime' => 'starttime',
            'endtime' => 'endtime',
            'fe_group' => 'fe_group'
        ],
        'versioningWS' => true,
        'origUid' => 't3_origuid',
    ],
    'columns' => [
        'sys_language_uid' => [
            'exclude' => true,
            'label' => 'LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.language',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'foreign_table' => 'sys_language',
                'foreign_table_where' => 'ORDER BY sys_language.title',
                'items' => [
                    ['LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.allLanguages', -1],
                    ['LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.default_value', 0],
                ],
                'default' => 0,
            ],
        ],
        'l10n_parent' => [
            'exclude' => true,
            'label' => 'LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.l18n_parent',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'items' => [
                    ['', 0],
                ],
                'foreign_table' => 'tx_persistence_entity',
                'foreign_table_where' => '
                    AND tx_persistence_entity.pid=###CURRENT_PID###
                    AND tx_persistence_entity.sys_language_uid IN (-1,0)
                ',
                'default' => 0,
            ],
        ],
        'l10n_diffsource' => [
            'config' => [
                'type' => 'passthrough',
                'default' => '',
            ],
        ],
        'hidden' => [
            'exclude' => true,
            'label' => 'LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.hidden',
            'config' => [
                'type' => 'check',
                'default' => 0,
            ],
        ],
        'title' => [
            'exclude' => true,
            'l10n_mode' => 'prefixLangTitle',
            'label' => 'Title',
            'config' => [
                'type' => 'input',
                'size' => 30,
                'eval' => 'required',
            ],
        ],
        'scalar_string' => [
            'exclude' => true,
            'l10n_mode' => 'prefixLangTitle',
            'label' => 'String',
            'config' => [
                'type' => 'input',
                'size' => 30,
                'eval' => 'required',
            ],
        ],
        'scalar_float' => [
            'exclude' => true,
            'l10n_mode' => 'prefixLangTitle',
            'label' => 'Float',
            'config' => [
                'type' => 'input',
                'size' => 30,
                'eval' => 'required',
            ],
        ],
        'scalar_integer' => [
            'exclude' => true,
            'l10n_mode' => 'prefixLangTitle',
            'label' => 'Integer',
            'config' => [
                'type' => 'input',
                'size' => 30,
                'eval' => 'required',
            ],
        ],
        'scalar_text' => [
            'exclude' => true,
            'l10n_mode' => 'prefixLangTitle',
            'label' => 'Text',
            'config' => [
                'type' => 'input',
                'size' => 30,
                'eval' => 'required',
            ],
        ],
        'relation_inline_11_file_reference' => [
            'exclude' => true,
            'label' => 'File reference (inline 1:1)',
            'config' => [
                'type' => 'inline',
                'foreign_field' => 'uid_foreign',
                'foreign_label' => 'uid_local',
                'foreign_match_fields' => [
                    'fieldname' => 'relation_inline_11_file_reference',
                ],
                'foreign_selector' => 'uid_local',
                'foreign_sortby' => 'sorting_foreign',
                'foreign_table' => 'sys_file_reference',
                'foreign_table_field' => 'tablenames',
                'maxitems' => 1,
            ],
        ],
        'relation_inline_1n_file_reference' => [
            'exclude' => true,
            'label' => 'File reference (inline 1:n)',
            'config' => [
                'type' => 'inline',
                'foreign_field' => 'uid_foreign',
                'foreign_label' => 'uid_local',
                'foreign_match_fields' => [
                    'fieldname' => 'relation_inline_1n_file_reference',
                ],
                'foreign_selector' => 'uid_local',
                'foreign_sortby' => 'sorting_foreign',
                'foreign_table' => 'sys_file_reference',
                'foreign_table_field' => 'tablenames',
                'maxitems' => 10,
            ],
        ],
        'relation_inline_1n_csv_file_reference' => [
            'exclude' => true,
            'label' => 'File reference (inline 1:n csv)',
            'config' => [
                'type' => 'inline',
                'foreign_table' => 'sys_file_reference',
                'maxitems' => 10,
                'default' => '',
            ],
        ],
        'relation_inline_mn_mm_content' => [
            'exclude' => true,
            'label' => 'Content (inline m:n mm)',
            'config' => [
                'type' => 'inline',
                'foreign_table' => 'tt_content',
                'foreign_table_where' => ' AND tt_content.sys_language_uid IN (-1, 0) ORDER BY tt_content.sorting ASC',
                'MM' => 'tx_persistence_entity_mm',
                'MM_match_fields' => [
                    'fieldname' => 'relation_inline_mn_mm_content',
                ],
                'maxitems' => 10,
                'default' => '',
            ],
        ],
        'relation_inline_mn_symmetric_entity' => [
            'exclude' => true,
            'label' => 'Entity (inline m:n symmetric)',
            'config' => [
                'type' => 'inline',
                'foreign_table' => 'tx_persistence_entity_symmetric',
                'foreign_field' => 'entity',
                'foreign_sortby' => 'sorting_entity',
                'symmetric_field' => 'peer',
                'symmetric_sortby' => 'sorting_peer',
                'maxitems' => 10,
                'default' => '',
            ],
        ],
        'relation_select_1n_page' => [
            'exclude' => true,
            'label' => 'Page (select 1:n)',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'foreign_table' => 'pages',
                'maxitems' => 1,
                'default' => 0,
            ],
        ],
        'relation_select_mn_csv_category' => [
            'exclude' => true,
            'label' => 'Category (select m:n csv)',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectMultipleSideBySide',
                'foreign_table' => 'sys_category',
                'maxitems' => 10,
                'default' => 0,
            ],
        ],
        'relation_select_mn_mm_content' => [
            'exclude' => true,
            'label' => 'Content (select m:n mm)',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectMultipleSideBySide',
                'foreign_table' => 'tt_content',
                'foreign_table_where' => ' AND tt_content.sys_language_uid IN (-1, 0) ORDER BY tt_content.sorting ASC',
                'MM' => 'tx_persistence_entity_mm',
                'MM_match_fields' => [
                    'fieldname' => 'relation_select_mn_mm_content',
                ],
                'MM_table_where' => ' AND further = 1',
                'size' => 6,
                'maxitems' => 10,
                'default' => 0,
            ],
        ],
        'relation_group_1n_content_page' => [
            'exclude' => true,
            'label' => 'Content, Page (group 1:n)',
            'config' => [
                'type' => 'group',
                'internal_type' => 'db',
                'allowed' => 'tt_content,pages',
                'maxitems' => 1,
                'autoSizeMax' => 10,
                'default' => '',
            ],
        ],
        'relation_group_mn_csv_content_page' => [
            'exclude' => true,
            'label' => 'Content, Page (group m:n csv)',
            'config' => [
                'type' => 'group',
                'internal_type' => 'db',
                'allowed' => 'tt_content,pages',
                'maxitems' => 10,
                'autoSizeMax' => 10,
                'default' => '',
            ],
        ],
        'relation_group_mn_csv_any' => [
            'exclude' => true,
            'label' => 'Content, Page (group m:n csv)',
            'config' => [
                'type' => 'group',
                'internal_type' => 'db',
                'allowed' => '*',
                'maxitems' => 10,
                'autoSizeMax' => 10,
                'default' => '',
            ],
        ],
        'relation_group_mn_mm_content_page' => [
            'exclude' => true,
            'label' => 'Content, Page (group m:n mm)',
            'config' => [
                'type' => 'group',
                'internal_type' => 'db',
                'allowed' => 'tt_content,pages',
                'MM' => 'tx_persistence_entity_mm',
                'MM_match_fields' => [
                    'fieldname' => 'relation_group_mn_mm_content_page',
                ],
                'maxitems' => 10,
                'autoSizeMax' => 10,
                'default' => '',
            ],
        ],
        'relation_group_mn_mm_any' => [
            'exclude' => true,
            'label' => 'Content, Page (group m:n mm)',
            'config' => [
                'type' => 'group',
                'internal_type' => 'db',
                'allowed' => '*',
                'MM' => 'tx_persistence_entity_mm',
                'MM_match_fields' => [
                    'fieldname' => 'relation_group_mn_mm_any',
                ],
                'maxitems' => 10,
                'autoSizeMax' => 10,
                'default' => '',
            ],
        ],
    ],
    'types' => [
        '0' => [
            'showitem' => '
                --div--;LLL:EXT:irre_tutorial/Resources/Private/Language/locallang_db.xml:tabs.general,
                    title,
                    scalar_string,
                    scalar_float,
                    scalar_integer,
                    scalar_text,
                    relation_inline_11_file,
                    relation_inline_1n_file,
                    relation_inline_1n_csv_file,
                    relation_inline_mn_mm_content,
                    relation_inline_mn_symmetric_entity,
                    relation_select_1n_page,
                    relation_select_mn_csv_category,
                    relation_select_mn_mm_content,
                    relation_group_1n_content_page,
                    relation_group_mn_csv_content_page,
                    relation_group_mn_mm_content_page
                --div--;LLL:EXT:irre_tutorial/Resources/Private/Language/locallang_db.xml:tabs.visibility,
                    sys_language_uid,
                    l10n_parent,
                    l10n_diffsource,
                    hidden
            ',
        ],
    ],
    'palettes' => [
        '1' => [
            'showitem' => '',
        ],
    ],
];
