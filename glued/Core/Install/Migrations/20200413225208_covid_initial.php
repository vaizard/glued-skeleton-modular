<?php

use Phinx\Db\Adapter\MysqlAdapter;

class CovidInitial extends Phinx\Migration\AbstractMigration
{
    public function change()
    {
        $this->execute('SET unique_checks=0; SET foreign_key_checks=0;');
        $this->table('t_assets_items', [
                'id' => false,
                'engine' => 'InnoDB',
                'encoding' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
                'comment' => '',
                'row_format' => 'DYNAMIC',
            ])
            ->addColumn('c_uid', 'integer', [
                'null' => false,
                'limit' => MysqlAdapter::INT_REGULAR,
            ])
            ->addColumn('c_data', 'json', [
                'null' => false,
                'after' => 'c_uid',
            ])
            ->addColumn('stor_name', 'string', [
                'null' => true,
                'limit' => 255,
                'collation' => 'utf8mb4_unicode_ci',
                'encoding' => 'utf8mb4',
                'after' => 'c_data',
            ])
            ->create();
        $this->table('t_calendar_sources', [
                'id' => false,
                'primary_key' => ['c_uid'],
                'engine' => 'InnoDB',
                'encoding' => 'utf8mb4',
                'collation' => 'utf8mb4_0900_ai_ci',
                'comment' => 'Uris to ICAL calendars and friends.',
                'row_format' => 'DYNAMIC',
            ])
            ->addColumn('c_json', 'json', [
                'null' => false,
                'comment' => 'Calendar data (json document)',
            ])
            ->addColumn('c_attr', 'json', [
                'null' => false,
                'comment' => 'Calendar attributes/state (json document)',
                'after' => 'c_json',
            ])
            ->addColumn('c_ts_created', 'timestamp', [
                'null' => true,
                'default' => 'CURRENT_TIMESTAMP',
                'update' => 'CURRENT_TIMESTAMP',
                'comment' => 'Timestamp created',
                'after' => 'c_attr',
            ])
            ->addColumn('c_ts_updated', 'timestamp', [
                'null' => true,
                'default' => 'CURRENT_TIMESTAMP',
                'comment' => 'Timestamp updated',
                'after' => 'c_ts_created',
            ])
            ->addColumn('c_uid', 'integer', [
                'null' => false,
                'limit' => MysqlAdapter::INT_REGULAR,
                'identity' => 'enable',
                'comment' => 'Unique row id',
                'after' => 'c_ts_updated',
            ])
            ->addColumn('c_user_id', 'integer', [
                'null' => false,
                'limit' => MysqlAdapter::INT_REGULAR,
                'comment' => 'Creator of the calendar (account)',
                'after' => 'c_uid',
            ])
            ->addColumn('c_domain_id', 'integer', [
                'null' => false,
                'limit' => MysqlAdapter::INT_REGULAR,
                'comment' => 'Domain id',
                'after' => 'c_user_id',
            ])
            ->addColumn('c_flag_deleted', 'boolean', [
                'null' => false,
                'limit' => MysqlAdapter::INT_TINY,
                'after' => 'c_domain_id',
            ])
            ->addIndex(['c_domain_id'], [
                'name' => 'c_domain_id',
                'unique' => false,
            ])
            ->addIndex(['c_user_id'], [
                'name' => 'c_user_id',
                'unique' => false,
            ])
            ->addForeignKey('c_user_id', 't_core_users', 'c_uid', [
                'constraint' => 't_calendar_sources_ibfk_1',
                'update' => 'NO_ACTION',
                'delete' => 'CASCADE',
            ])
            ->addForeignKey('c_domain_id', 't_core_domains', 'c_uid', [
                'constraint' => 't_calendar_sources_ibfk_3',
                'update' => 'RESTRICT',
                'delete' => 'CASCADE',
            ])
            ->create();
        $this->table('t_core_authn', [
                'id' => false,
                'primary_key' => ['c_uid'],
                'engine' => 'InnoDB',
                'encoding' => 'utf8mb4',
                'collation' => 'utf8mb4_0900_ai_ci',
                'comment' => 'Access credentials to users defined in t_core_users. Each user is allowed a secondary name/pass combination (plausible deniability)',
                'row_format' => 'DYNAMIC',
            ])
            ->addColumn('c_attr', 'json', [
                'null' => true,
                'comment' => 'Object attributes (status: enabled/disabled, allowed IPs, scope - i.e. allwed/forbidden actions, rate limits, etc.)',
            ])
            ->addColumn('c_hash', 'string', [
                'null' => false,
                'limit' => 255,
                'collation' => 'ascii_bin',
                'encoding' => 'ascii',
                'comment' => 'Password or api key hash (according to c_type)',
                'after' => 'c_attr',
            ])
            ->addColumn('c_ts_created', 'timestamp', [
                'null' => true,
                'default' => 'CURRENT_TIMESTAMP',
                'after' => 'c_hash',
            ])
            ->addColumn('c_ts_modified', 'timestamp', [
                'null' => true,
                'default' => 'CURRENT_TIMESTAMP',
                'update' => 'CURRENT_TIMESTAMP',
                'after' => 'c_ts_created',
            ])
            ->addColumn('c_type', 'integer', [
                'null' => false,
                'limit' => MysqlAdapter::INT_TINY,
                'comment' => '0 password, 1 api key',
                'after' => 'c_ts_modified',
            ])
            ->addColumn('c_uid', 'integer', [
                'null' => false,
                'limit' => MysqlAdapter::INT_REGULAR,
                'identity' => 'enable',
                'comment' => 'Unique row/object id',
                'after' => 'c_type',
            ])
            ->addColumn('c_user_uid', 'integer', [
                'null' => true,
                'limit' => MysqlAdapter::INT_REGULAR,
                'comment' => 'Corresponds to t_core_users.c_uid',
                'after' => 'c_uid',
            ])
            ->addIndex(['c_uid'], [
                'name' => 'c_uid',
                'unique' => true,
            ])
            ->addIndex(['c_user_uid'], [
                'name' => 'c_user_id',
                'unique' => false,
            ])
            ->addForeignKey('c_user_uid', 't_core_users', 'c_uid', [
                'constraint' => 't_core_authn_ibfk_1',
                'update' => 'NO_ACTION',
                'delete' => 'CASCADE',
            ])
            ->create();
        $this->table('t_core_domains', [
                'id' => false,
                'primary_key' => ['c_uid'],
                'engine' => 'InnoDB',
                'encoding' => 'utf8mb4',
                'collation' => 'utf8mb4_0900_ai_ci',
                'comment' => 'Domains (as in RBAC domains)',
                'row_format' => 'DYNAMIC',
            ])
            ->addColumn('c_name', 'string', [
                'null' => false,
                'limit' => 255,
                'collation' => 'utf8mb4_0900_ai_ci',
                'encoding' => 'utf8mb4',
                'comment' => 'Domain name',
            ])
            ->addColumn('c_ts_created', 'timestamp', [
                'null' => true,
                'default' => 'CURRENT_TIMESTAMP',
                'update' => 'CURRENT_TIMESTAMP',
                'comment' => 'Timestamp created',
                'after' => 'c_name',
            ])
            ->addColumn('c_ts_updated', 'timestamp', [
                'null' => true,
                'default' => 'CURRENT_TIMESTAMP',
                'comment' => 'Timestamp updated',
                'after' => 'c_ts_created',
            ])
            ->addColumn('c_uid', 'integer', [
                'null' => false,
                'limit' => MysqlAdapter::INT_REGULAR,
                'identity' => 'enable',
                'comment' => 'Unique row id',
                'after' => 'c_ts_updated',
            ])
            ->addColumn('c_user_id', 'integer', [
                'null' => false,
                'limit' => MysqlAdapter::INT_REGULAR,
                'comment' => 'Creator of the domain',
                'after' => 'c_uid',
            ])
            ->addIndex(['c_user_id'], [
                'name' => 'c_user_id',
                'unique' => false,
            ])
            ->addForeignKey('c_user_id', 't_core_users', 'c_uid', [
                'constraint' => 't_core_domains_ibfk_1',
                'update' => 'NO_ACTION',
                'delete' => 'CASCADE',
            ])
            ->create();
        $this->table('t_core_processlog', [
                'id' => false,
                'primary_key' => ['c_uid'],
                'engine' => 'InnoDB',
                'encoding' => 'utf8mb4',
                'collation' => 'utf8mb4_0900_ai_ci',
                'comment' => 'Mail accounts',
                'row_format' => 'DYNAMIC',
            ])
            ->addColumn('c_domain_id', 'integer', [
                'null' => false,
                'limit' => MysqlAdapter::INT_REGULAR,
            ])
            ->addColumn('c_name', 'string', [
                'null' => false,
                'limit' => 255,
                'collation' => 'utf8mb4_0900_ai_ci',
                'encoding' => 'utf8mb4',
                'comment' => 'User friendly process name',
                'after' => 'c_domain_id',
            ])
            ->addColumn('c_params', 'json', [
                'null' => false,
                'comment' => 'Process start parameters',
                'after' => 'c_name',
            ])
            ->addColumn('c_parent_collection', 'string', [
                'null' => false,
                'limit' => 255,
                'collation' => 'utf8mb4_0900_ai_ci',
                'encoding' => 'utf8mb4',
                'comment' => 'Process parent table name',
                'after' => 'c_params',
            ])
            ->addColumn('c_parent_method', 'string', [
                'null' => false,
                'limit' => 255,
                'collation' => 'utf8mb4_0900_ai_ci',
                'encoding' => 'utf8mb4',
                'comment' => 'Process parent method',
                'after' => 'c_parent_collection',
            ])
            ->addColumn('c_parent_object', 'string', [
                'null' => false,
                'limit' => 255,
                'collation' => 'utf8mb4_0900_ai_ci',
                'encoding' => 'utf8mb4',
                'comment' => 'Process parent uid',
                'after' => 'c_parent_method',
            ])
            ->addColumn('c_result_code', 'integer', [
                'null' => false,
                'limit' => MysqlAdapter::INT_REGULAR,
                'comment' => 'Process result code',
                'after' => 'c_parent_object',
            ])
            ->addColumn('c_result_message', 'string', [
                'null' => false,
                'limit' => 255,
                'collation' => 'utf8mb4_0900_ai_ci',
                'encoding' => 'utf8mb4',
                'comment' => 'Process result message',
                'after' => 'c_result_code',
            ])
            ->addColumn('c_result_payload', 'text', [
                'null' => false,
                'limit' => 65535,
                'collation' => 'utf8mb4_0900_ai_ci',
                'encoding' => 'utf8mb4',
                'comment' => 'Process result payload',
                'after' => 'c_result_message',
            ])
            ->addColumn('c_result_success', 'bit', [
                'null' => true,
                'limit' => 1,
                'comment' => 'NULL = unknown (still running), 1 = success, 0 = failure',
                'after' => 'c_result_payload',
            ])
            ->addColumn('c_runtime_limit', 'integer', [
                'null' => false,
                'limit' => MysqlAdapter::INT_REGULAR,
                'comment' => 'In minutes (0 = unknown/unlimited, 30 = 30 minutes)',
                'after' => 'c_result_success',
            ])
            ->addColumn('c_ts_created', 'timestamp', [
                'null' => false,
                'default' => 'CURRENT_TIMESTAMP',
                'comment' => 'Timestamp created',
                'after' => 'c_runtime_limit',
            ])
            ->addColumn('c_ts_finished', 'timestamp', [
                'null' => false,
                'default' => 'CURRENT_TIMESTAMP',
                'comment' => 'Timestamp finished',
                'after' => 'c_ts_created',
            ])
            ->addColumn('c_uid', 'biginteger', [
                'null' => false,
                'limit' => MysqlAdapter::INT_BIG,
                'identity' => 'enable',
                'comment' => 'Unique row id',
                'after' => 'c_ts_finished',
            ])
            ->addColumn('c_user_id', 'integer', [
                'null' => false,
                'limit' => MysqlAdapter::INT_REGULAR,
                'comment' => 'Creator of the worklog item (account)',
                'after' => 'c_uid',
            ])
            ->addIndex(['c_domain_id'], [
                'name' => 'c_domain_id',
                'unique' => false,
            ])
            ->addIndex(['c_user_id'], [
                'name' => 'c_user_id',
                'unique' => false,
            ])
            ->create();
        $this->table('t_core_profiles', [
                'id' => false,
                'primary_key' => ['c_uid'],
                'engine' => 'InnoDB',
                'encoding' => 'utf8mb4',
                'collation' => 'utf8mb4_0900_ai_ci',
                'comment' => 'Users\' system profiles (personal, invoicing, etc.)',
                'row_format' => 'DYNAMIC',
            ])
            ->addColumn('c_json', 'json', [
                'null' => false,
                'comment' => 'Profile data',
            ])
            ->addColumn('c_uid', 'integer', [
                'null' => false,
                'limit' => MysqlAdapter::INT_REGULAR,
                'identity' => 'enable',
                'comment' => 'Unique row id',
                'after' => 'c_json',
            ])
            ->addColumn('c_users_uid', 'integer', [
                'null' => false,
                'limit' => MysqlAdapter::INT_REGULAR,
                'comment' => 'Profile owner',
                'after' => 'c_uid',
            ])
            ->create();
        $this->table('t_core_users', [
                'id' => false,
                'primary_key' => ['c_uid'],
                'engine' => 'InnoDB',
                'encoding' => 'utf8mb4',
                'collation' => 'utf8mb4_0900_ai_ci',
                'comment' => 'Users\' table, their profile and their attributes',
                'row_format' => 'DYNAMIC',
            ])
            ->addColumn('c_attr', 'json', [
                'null' => true,
                'comment' => 'User\'s account attributes (enabled/disabled, GDPR anonymised, etc.)',
            ])
            ->addColumn('c_email', 'string', [
                'null' => false,
                'limit' => 255,
                'collation' => 'utf8mb4_0900_ai_ci',
                'encoding' => 'utf8mb4',
                'comment' => 'Primary email',
                'after' => 'c_attr',
            ])
            ->addColumn('c_lang', 'char', [
                'null' => true,
                'default' => 'en_US',
                'limit' => 5,
                'collation' => 'utf8mb4_0900_ai_ci',
                'encoding' => 'utf8mb4',
                'comment' => 'Preferred language',
                'after' => 'c_email',
            ])
            ->addColumn('c_name', 'string', [
                'null' => false,
                'limit' => 255,
                'collation' => 'utf8mb4_0900_ai_ci',
                'encoding' => 'utf8mb4',
                'comment' => 'User\'s visible screen name (nickname)',
                'after' => 'c_lang',
            ])
            ->addColumn('c_ts_created', 'timestamp', [
                'null' => true,
                'default' => 'CURRENT_TIMESTAMP',
                'comment' => 'Timestamp: account created',
                'after' => 'c_name',
            ])
            ->addColumn('c_ts_modified', 'timestamp', [
                'null' => true,
                'default' => 'CURRENT_TIMESTAMP',
                'update' => 'CURRENT_TIMESTAMP',
                'comment' => 'Timestamp: account modified',
                'after' => 'c_ts_created',
            ])
            ->addColumn('c_uid', 'integer', [
                'null' => false,
                'limit' => MysqlAdapter::INT_REGULAR,
                'identity' => 'enable',
                'comment' => 'Unique row/object id',
                'after' => 'c_ts_modified',
            ])
            ->addIndex(['c_email'], [
                'name' => 'c_email',
                'unique' => true,
            ])
            ->addIndex(['c_name'], [
                'name' => 'c_screenname',
                'unique' => false,
            ])
            ->addIndex(['c_uid'], [
                'name' => 'c_uid',
                'unique' => true,
            ])
            ->create();
        $this->table('t_covid_zakladace', [
                'id' => false,
                'primary_key' => ['c_uid'],
                'engine' => 'InnoDB',
                'encoding' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
                'comment' => '',
                'row_format' => 'DYNAMIC',
            ])
            ->addColumn('c_uid', 'integer', [
                'null' => false,
                'limit' => MysqlAdapter::INT_REGULAR,
                'identity' => 'enable',
            ])
            ->addColumn('c_ts', 'string', [
                'null' => false,
                'limit' => 255,
                'collation' => 'ascii_general_ci',
                'encoding' => 'ascii',
                'after' => 'c_uid',
            ])
            ->addColumn('c_name', 'string', [
                'null' => false,
                'limit' => 255,
                'collation' => 'utf8mb4_unicode_ci',
                'encoding' => 'utf8mb4',
                'after' => 'c_ts',
            ])
            ->addColumn('c_phone', 'string', [
                'null' => false,
                'limit' => 255,
                'collation' => 'utf8mb4_unicode_ci',
                'encoding' => 'utf8mb4',
                'after' => 'c_name',
            ])
            ->addColumn('c_email', 'string', [
                'null' => false,
                'limit' => 255,
                'collation' => 'utf8mb4_unicode_ci',
                'encoding' => 'utf8mb4',
                'after' => 'c_phone',
            ])
            ->addColumn('c_notes', 'text', [
                'null' => true,
                'limit' => 65535,
                'collation' => 'utf8mb4_unicode_ci',
                'encoding' => 'utf8mb4',
                'after' => 'c_email',
            ])
            ->addColumn('c_gdpr_yes', 'integer', [
                'null' => false,
                'limit' => MysqlAdapter::INT_TINY,
                'after' => 'c_notes',
            ])
            ->addColumn('c_address', 'string', [
                'null' => true,
                'limit' => 255,
                'collation' => 'utf8mb4_unicode_ci',
                'encoding' => 'utf8mb4',
                'after' => 'c_gdpr_yes',
            ])
            ->addColumn('c_addr_street', 'string', [
                'null' => true,
                'limit' => 255,
                'collation' => 'utf8mb4_unicode_ci',
                'encoding' => 'utf8mb4',
                'after' => 'c_address',
            ])
            ->addColumn('c_addr_city', 'string', [
                'null' => true,
                'limit' => 255,
                'collation' => 'utf8mb4_unicode_ci',
                'encoding' => 'utf8mb4',
                'after' => 'c_addr_street',
            ])
            ->addColumn('c_addr_zip', 'string', [
                'null' => true,
                'limit' => 255,
                'collation' => 'utf8mb4_unicode_ci',
                'encoding' => 'utf8mb4',
                'after' => 'c_addr_city',
            ])
            ->addColumn('c_addr_note', 'string', [
                'null' => true,
                'limit' => 255,
                'collation' => 'utf8mb4_unicode_ci',
                'encoding' => 'utf8mb4',
                'after' => 'c_addr_zip',
            ])
            ->addColumn('c_handovered', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => MysqlAdapter::INT_TINY,
                'after' => 'c_addr_note',
            ])
            ->addColumn('c_delivered', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => MysqlAdapter::INT_TINY,
                'after' => 'c_handovered',
            ])
            ->addColumn('c_noneed', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => MysqlAdapter::INT_TINY,
                'after' => 'c_delivered',
            ])
            ->addColumn('c_amount', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => MysqlAdapter::INT_REGULAR,
                'after' => 'c_noneed',
            ])
            ->addColumn('c_email_sent', 'integer', [
                'null' => true,
                'default' => '0',
                'limit' => MysqlAdapter::INT_REGULAR,
                'after' => 'c_amount',
            ])
            ->addColumn('c_email_result', 'integer', [
                'null' => true,
                'limit' => MysqlAdapter::INT_REGULAR,
                'after' => 'c_email_sent',
            ])
            ->addColumn('c_email_body', 'text', [
                'null' => true,
                'limit' => 65535,
                'collation' => 'utf8mb4_unicode_ci',
                'encoding' => 'utf8mb4',
                'after' => 'c_email_result',
            ])
            ->addColumn('c_bad_data', 'text', [
                'null' => true,
                'limit' => 65535,
                'collation' => 'utf8mb4_unicode_ci',
                'encoding' => 'utf8mb4',
                'after' => 'c_email_body',
            ])
            ->addColumn('c_row_hash', 'string', [
                'null' => false,
                'limit' => 255,
                'collation' => 'ascii_general_ci',
                'encoding' => 'ascii',
                'after' => 'c_bad_data',
            ])
            ->addColumn('c_ts_updated', 'timestamp', [
                'null' => false,
                'default' => 'CURRENT_TIMESTAMP',
                'after' => 'c_row_hash',
            ])
            ->create();
        $this->table('t_mail_accounts', [
                'id' => false,
                'primary_key' => ['c_uid'],
                'engine' => 'InnoDB',
                'encoding' => 'utf8mb4',
                'collation' => 'utf8mb4_0900_ai_ci',
                'comment' => 'Mail accounts',
                'row_format' => 'DYNAMIC',
            ])
            ->addColumn('c_data', 'blob', [
                'null' => false,
                'limit' => MysqlAdapter::BLOB_REGULAR,
                'comment' => 'Mail account data',
            ])
            ->addColumn('c_domain_id', 'integer', [
                'null' => false,
                'limit' => MysqlAdapter::INT_REGULAR,
                'after' => 'c_data',
            ])
            ->addColumn('c_frequency', 'integer', [
                'null' => false,
                'limit' => MysqlAdapter::INT_REGULAR,
                'comment' => 'Fetch frequecny: 0 = on demand, 30 = every 30 minutes',
                'after' => 'c_domain_id',
            ])
            ->addColumn('c_name', 'string', [
                'null' => false,
                'limit' => 255,
                'collation' => 'utf8mb4_0900_ai_ci',
                'encoding' => 'utf8mb4',
                'comment' => 'User-friendly name',
                'after' => 'c_frequency',
            ])
            ->addColumn('c_ts_created', 'timestamp', [
                'null' => false,
                'default' => 'CURRENT_TIMESTAMP',
                'comment' => 'Timestamp created',
                'after' => 'c_name',
            ])
            ->addColumn('c_ts_updated', 'timestamp', [
                'null' => false,
                'default' => 'CURRENT_TIMESTAMP',
                'comment' => 'Timestamp updated',
                'after' => 'c_ts_created',
            ])
            ->addColumn('c_uid', 'integer', [
                'null' => false,
                'limit' => MysqlAdapter::INT_REGULAR,
                'identity' => 'enable',
                'comment' => 'Unique row id',
                'after' => 'c_ts_updated',
            ])
            ->addColumn('c_user_id', 'integer', [
                'null' => false,
                'limit' => MysqlAdapter::INT_REGULAR,
                'comment' => 'Creator of the worklog item (account)',
                'after' => 'c_uid',
            ])
            ->addIndex(['c_domain_id'], [
                'name' => 'c_domain_id',
                'unique' => false,
            ])
            ->addIndex(['c_user_id'], [
                'name' => 'c_user_id',
                'unique' => false,
            ])
            ->create();
        $this->table('t_store_items', [
                'id' => false,
                'primary_key' => ['c_uid'],
                'engine' => 'InnoDB',
                'encoding' => 'utf8mb4',
                'collation' => 'utf8mb4_0900_ai_ci',
                'comment' => 'Store items are just regular store items (products and one-time services for purchase)',
                'row_format' => 'DYNAMIC',
            ])
            ->addColumn('c_attr', 'json', [
                'null' => false,
                'comment' => 'Item attributes',
            ])
            ->addColumn('c_json', 'json', [
                'null' => false,
                'comment' => 'Item data (json document)',
                'after' => 'c_attr',
            ])
            ->addColumn('c_seller_id', 'integer', [
                'null' => false,
                'limit' => MysqlAdapter::INT_REGULAR,
                'comment' => 'Seller of this particular item',
                'after' => 'c_json',
            ])
            ->addColumn('c_ts_created', 'timestamp', [
                'null' => true,
                'default' => 'CURRENT_TIMESTAMP',
                'update' => 'CURRENT_TIMESTAMP',
                'comment' => 'Timestamp created',
                'after' => 'c_seller_id',
            ])
            ->addColumn('c_ts_updated', 'timestamp', [
                'null' => true,
                'default' => 'CURRENT_TIMESTAMP',
                'comment' => 'Timestamp updated',
                'after' => 'c_ts_created',
            ])
            ->addColumn('c_uid', 'integer', [
                'null' => false,
                'limit' => MysqlAdapter::INT_REGULAR,
                'identity' => 'enable',
                'comment' => 'Unique row id',
                'after' => 'c_ts_updated',
            ])
            ->addIndex(['c_seller_id'], [
                'name' => 'c_seller_id',
                'unique' => false,
            ])
            ->addForeignKey('c_seller_id', 't_store_sellers', 'c_uid', [
                'constraint' => 't_store_items_ibfk_1',
                'update' => 'NO_ACTION',
                'delete' => 'CASCADE',
            ])
            ->create();
        $this->table('t_store_sellers', [
                'id' => false,
                'primary_key' => ['c_uid'],
                'engine' => 'InnoDB',
                'encoding' => 'utf8mb4',
                'collation' => 'utf8mb4_0900_ai_ci',
                'comment' => 'Sellers are entities who sell stuff at specified conditions (think aliexpress or amazon sellers)',
                'row_format' => 'DYNAMIC',
            ])
            ->addColumn('c_attr', 'json', [
                'null' => false,
                'comment' => 'Seller attributes',
            ])
            ->addColumn('c_json', 'json', [
                'null' => false,
                'comment' => 'Seller data (json document)',
                'after' => 'c_attr',
            ])
            ->addColumn('c_ts_created', 'timestamp', [
                'null' => true,
                'default' => 'CURRENT_TIMESTAMP',
                'update' => 'CURRENT_TIMESTAMP',
                'comment' => 'Timestamp created',
                'after' => 'c_json',
            ])
            ->addColumn('c_ts_updated', 'timestamp', [
                'null' => true,
                'default' => 'CURRENT_TIMESTAMP',
                'comment' => 'Timestamp updated',
                'after' => 'c_ts_created',
            ])
            ->addColumn('c_uid', 'integer', [
                'null' => false,
                'limit' => MysqlAdapter::INT_REGULAR,
                'identity' => 'enable',
                'comment' => 'Unique row id',
                'after' => 'c_ts_updated',
            ])
            ->addColumn('c_user_id', 'integer', [
                'null' => false,
                'limit' => MysqlAdapter::INT_REGULAR,
                'comment' => 'Creator of the seller (account)',
                'after' => 'c_uid',
            ])
            ->addIndex(['c_user_id'], [
                'name' => 'c_user_id',
                'unique' => false,
            ])
            ->create();
        $this->table('t_store_subscriptions', [
                'id' => false,
                'primary_key' => ['c_uid'],
                'engine' => 'InnoDB',
                'encoding' => 'utf8mb4',
                'collation' => 'utf8mb4_0900_ai_ci',
                'comment' => 'Subscriptions are recurring purchase items (donations, memberships, internet subscriptions, periodicals delivery, etc.)',
                'row_format' => 'DYNAMIC',
            ])
            ->addColumn('c_attr', 'json', [
                'null' => false,
                'comment' => 'Subscription attributes',
            ])
            ->addColumn('c_json', 'json', [
                'null' => false,
                'comment' => 'Subscription data (json document)',
                'after' => 'c_attr',
            ])
            ->addColumn('c_seller_id', 'integer', [
                'null' => false,
                'limit' => MysqlAdapter::INT_REGULAR,
                'comment' => 'Seller of this particular subscription',
                'after' => 'c_json',
            ])
            ->addColumn('c_ts_created', 'timestamp', [
                'null' => true,
                'default' => 'CURRENT_TIMESTAMP',
                'update' => 'CURRENT_TIMESTAMP',
                'comment' => 'Timestamp created',
                'after' => 'c_seller_id',
            ])
            ->addColumn('c_ts_updated', 'timestamp', [
                'null' => true,
                'default' => 'CURRENT_TIMESTAMP',
                'comment' => 'Timestamp updated',
                'after' => 'c_ts_created',
            ])
            ->addColumn('c_uid', 'integer', [
                'null' => false,
                'limit' => MysqlAdapter::INT_REGULAR,
                'identity' => 'enable',
                'comment' => 'Unique row id',
                'after' => 'c_ts_updated',
            ])
            ->addIndex(['c_seller_id'], [
                'name' => 'c_seller_id',
                'unique' => false,
            ])
            ->addForeignKey('c_seller_id', 't_store_sellers', 'c_uid', [
                'constraint' => 't_store_subscriptions_ibfk_1',
                'update' => 'NO_ACTION',
                'delete' => 'CASCADE',
            ])
            ->create();
        $this->table('t_store_tickets', [
                'id' => false,
                'primary_key' => ['c_uid'],
                'engine' => 'InnoDB',
                'encoding' => 'utf8mb4',
                'collation' => 'utf8mb4_0900_ai_ci',
                'comment' => 'Tickets are time-limited booking and item offerings (tickets to conferences, workshops, merch, etc.)',
                'row_format' => 'DYNAMIC',
            ])
            ->addColumn('c_attr', 'json', [
                'null' => false,
                'comment' => 'Ticket attributes',
            ])
            ->addColumn('c_json', 'json', [
                'null' => false,
                'comment' => 'Ticket data (json document)',
                'after' => 'c_attr',
            ])
            ->addColumn('c_seller_id', 'integer', [
                'null' => false,
                'limit' => MysqlAdapter::INT_REGULAR,
                'comment' => 'Ticket seller id',
                'after' => 'c_json',
            ])
            ->addColumn('c_ts_created', 'timestamp', [
                'null' => true,
                'default' => 'CURRENT_TIMESTAMP',
                'update' => 'CURRENT_TIMESTAMP',
                'comment' => 'Timestamp created',
                'after' => 'c_seller_id',
            ])
            ->addColumn('c_ts_updated', 'timestamp', [
                'null' => true,
                'default' => 'CURRENT_TIMESTAMP',
                'comment' => 'Timestamp updated',
                'after' => 'c_ts_created',
            ])
            ->addColumn('c_uid', 'integer', [
                'null' => false,
                'limit' => MysqlAdapter::INT_REGULAR,
                'identity' => 'enable',
                'comment' => 'Unique row id',
                'after' => 'c_ts_updated',
            ])
            ->addIndex(['c_seller_id'], [
                'name' => 'c_seller_id',
                'unique' => false,
            ])
            ->addForeignKey('c_seller_id', 't_store_sellers', 'c_uid', [
                'constraint' => 't_store_tickets_ibfk_1',
                'update' => 'NO_ACTION',
                'delete' => 'CASCADE',
            ])
            ->create();
        $this->table('t_worklog_items', [
                'id' => false,
                'primary_key' => ['c_uid'],
                'engine' => 'InnoDB',
                'encoding' => 'utf8mb4',
                'collation' => 'utf8mb4_0900_ai_ci',
                'comment' => 'Sellers are entities who sell stuff at specified conditions (think aliexpress or amazon sellers)',
                'row_format' => 'DYNAMIC',
            ])
            ->addColumn('c_domain_id', 'integer', [
                'null' => false,
                'limit' => MysqlAdapter::INT_REGULAR,
            ])
            ->addColumn('c_json', 'json', [
                'null' => false,
                'comment' => 'Worklog item data (json document)',
                'after' => 'c_domain_id',
            ])
            ->addColumn('c_ts_created', 'timestamp', [
                'null' => false,
                'default' => 'CURRENT_TIMESTAMP',
                'comment' => 'Timestamp created',
                'after' => 'c_json',
            ])
            ->addColumn('c_ts_updated', 'timestamp', [
                'null' => false,
                'default' => 'CURRENT_TIMESTAMP',
                'update' => 'CURRENT_TIMESTAMP',
                'comment' => 'Timestamp updated',
                'after' => 'c_ts_created',
            ])
            ->addColumn('c_uid', 'integer', [
                'null' => false,
                'limit' => MysqlAdapter::INT_REGULAR,
                'identity' => 'enable',
                'comment' => 'Unique row id',
                'after' => 'c_ts_updated',
            ])
            ->addColumn('c_user_id', 'integer', [
                'null' => false,
                'limit' => MysqlAdapter::INT_REGULAR,
                'comment' => 'Creator of the worklog item (account)',
                'after' => 'c_uid',
            ])
            ->addIndex(['c_domain_id'], [
                'name' => 'c_domain_id',
                'unique' => false,
            ])
            ->addIndex(['c_user_id'], [
                'name' => 'c_user_id',
                'unique' => false,
            ])
            ->addForeignKey('c_user_id', 't_core_users', 'c_uid', [
                'constraint' => 't_worklog_items_ibfk_1',
                'update' => 'NO_ACTION',
                'delete' => 'CASCADE',
            ])
            ->addForeignKey('c_domain_id', 't_core_domains', 'c_uid', [
                'constraint' => 't_worklog_items_ibfk_2',
                'update' => 'NO_ACTION',
                'delete' => 'CASCADE',
            ])
            ->create();
        $this->execute('SET unique_checks=1; SET foreign_key_checks=1;');
    }
}
