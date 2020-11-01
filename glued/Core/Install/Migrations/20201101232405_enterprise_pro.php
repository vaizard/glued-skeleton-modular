<?php

use Phinx\Db\Adapter\MysqlAdapter;

class EnterprisePro extends Phinx\Migration\AbstractMigration
{
    public function change()
    {
        $this->execute('SET unique_checks=0; SET foreign_key_checks=0;');
        $this->table('t_enterprise_projects', [
                'id' => false,
                'primary_key' => ['c_uid'],
                'engine' => 'InnoDB',
                'encoding' => 'utf8mb4',
                'collation' => 'utf8mb4_0900_ai_ci',
                'comment' => 'projects in enterprise module',
                'row_format' => 'DYNAMIC',
            ])
            ->addColumn('c_json', 'json', [
                'null' => false,
                'comment' => 'Item data (json document)',
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
            ->addColumn('c_stor_name', 'string', [
                'null' => false,
                'default' => '',
                'limit' => 255,
                'collation' => 'utf8mb4_0900_ai_ci',
                'encoding' => 'utf8mb4',
                'comment' => 'Name for the stor CAS',
                'after' => 'c_uid',
            ])
            ->create();
        $this->table('t_enterprise_projects_tree', [
                'id' => false,
                'primary_key' => ['c_parent', 'c_child'],
                'engine' => 'InnoDB',
                'encoding' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
                'comment' => '',
                'row_format' => 'DYNAMIC',
            ])
            ->addColumn('c_parent', 'integer', [
                'null' => false,
                'limit' => MysqlAdapter::INT_REGULAR,
            ])
            ->addColumn('c_child', 'integer', [
                'null' => false,
                'limit' => MysqlAdapter::INT_REGULAR,
                'after' => 'c_parent',
            ])
            ->addIndex(['c_child'], [
                'name' => 'c_child',
                'unique' => false,
            ])
            ->create();
        $this->table('t_fin_accounts', [
                'id' => false,
                'primary_key' => ['c_uid'],
                'engine' => 'InnoDB',
                'encoding' => 'utf8mb4',
                'collation' => 'utf8mb4_0900_ai_ci',
                'comment' => 'Financial transactions',
                'row_format' => 'DYNAMIC',
            ])
            ->changeColumn('c_uid', 'integer', [
                'null' => false,
                'limit' => MysqlAdapter::INT_REGULAR,
                'identity' => 'enable',
                'comment' => 'Unique row/object id',
            ])
            ->save();
        $this->table('t_fin_trx', [
                'id' => false,
                'primary_key' => ['c_uid'],
                'engine' => 'InnoDB',
                'encoding' => 'utf8mb4',
                'collation' => 'utf8mb4_0900_ai_ci',
                'comment' => 'Financial transactions',
                'row_format' => 'DYNAMIC',
            ])
            ->changeColumn('c_uid', 'integer', [
                'null' => false,
                'limit' => MysqlAdapter::INT_REGULAR,
                'identity' => 'enable',
                'comment' => 'Unique row/object id',
            ])
            ->changeColumn('c_account_id', 'integer', [
                'null' => false,
                'limit' => MysqlAdapter::INT_REGULAR,
                'comment' => 'Account relevant to the transaction (t_fin_accounts.uid)',
                'after' => 'c_uid',
            ])
            ->changeColumn('c_ext_order_id', 'string', [
                'null' => true,
                'limit' => 255,
                'collation' => 'utf8mb4_0900_ai_ci',
                'encoding' => 'utf8mb4',
                'after' => 'c_json',
            ])
            ->changeColumn('c_ext_trx_id', 'string', [
                'null' => true,
                'limit' => 255,
                'collation' => 'utf8mb4_0900_ai_ci',
                'encoding' => 'utf8mb4',
                'after' => 'c_ext_order_id',
            ])
            ->changeColumn('c_order_int_id', 'string', [
                'null' => true,
                'limit' => 255,
                'collation' => 'utf8mb4_0900_ai_ci',
                'encoding' => 'utf8mb4',
                'after' => 'c_ext_trx_id',
            ])
            ->changeColumn('c_order_created_by_name', 'string', [
                'null' => true,
                'limit' => 255,
                'collation' => 'utf8mb4_0900_ai_ci',
                'encoding' => 'utf8mb4',
                'after' => 'c_order_int_id',
            ])
            ->changeColumn('c_order_created_by_uid', 'integer', [
                'null' => true,
                'limit' => MysqlAdapter::INT_REGULAR,
                'after' => 'c_order_created_by_name',
            ])
            ->changeColumn('c_order_created_dt', 'char', [
                'null' => true,
                'limit' => 25,
                'collation' => 'utf8mb4_0900_ai_ci',
                'encoding' => 'utf8mb4',
                'after' => 'c_order_created_by_uid',
            ])
            ->changeColumn('c_order_authed_by_name', 'string', [
                'null' => true,
                'limit' => 255,
                'collation' => 'utf8mb4_0900_ai_ci',
                'encoding' => 'utf8mb4',
                'after' => 'c_order_created_dt',
            ])
            ->changeColumn('c_order_authed_by_uid', 'integer', [
                'null' => true,
                'limit' => MysqlAdapter::INT_REGULAR,
                'after' => 'c_order_authed_by_name',
            ])
            ->changeColumn('c_order_authed_dt', 'char', [
                'null' => true,
                'limit' => 25,
                'collation' => 'utf8mb4_0900_ai_ci',
                'encoding' => 'utf8mb4',
                'after' => 'c_order_authed_by_uid',
            ])
            ->changeColumn('c_trx_dt', 'char', [
                'null' => true,
                'limit' => 25,
                'collation' => 'utf8mb4_0900_ai_ci',
                'encoding' => 'utf8mb4',
                'after' => 'c_order_authed_dt',
            ])
            ->changeColumn('c_trx_currency', 'char', [
                'null' => true,
                'limit' => 3,
                'collation' => 'utf8mb4_0900_ai_ci',
                'encoding' => 'utf8mb4',
                'after' => 'c_trx_dt',
            ])
            ->changeColumn('c_trx_volume', 'double', [
                'null' => true,
                'after' => 'c_trx_currency',
            ])
            ->changeColumn('c_trx_offset_name', 'string', [
                'null' => true,
                'limit' => 255,
                'collation' => 'utf8mb4_0900_ai_ci',
                'encoding' => 'utf8mb4',
                'after' => 'c_trx_volume',
            ])
            ->changeColumn('c_trx_offset_id', 'integer', [
                'null' => true,
                'limit' => MysqlAdapter::INT_REGULAR,
                'after' => 'c_trx_offset_name',
            ])
            ->changeColumn('c_trx_offset_bank_code', 'string', [
                'null' => true,
                'limit' => 255,
                'collation' => 'utf8mb4_0900_ai_ci',
                'encoding' => 'utf8mb4',
                'after' => 'c_trx_offset_id',
            ])
            ->changeColumn('c_trx_offset_bank_bic', 'string', [
                'null' => true,
                'limit' => 11,
                'collation' => 'utf8mb4_0900_ai_ci',
                'encoding' => 'utf8mb4',
                'after' => 'c_trx_offset_bank_code',
            ])
            ->changeColumn('c_trx_offset_iban', 'string', [
                'null' => true,
                'limit' => 34,
                'collation' => 'utf8mb4_0900_ai_ci',
                'encoding' => 'utf8mb4',
                'after' => 'c_trx_offset_bank_bic',
            ])
            ->changeColumn('c_trx_offset_account_nr', 'string', [
                'null' => true,
                'limit' => 34,
                'collation' => 'utf8mb4_0900_ai_ci',
                'encoding' => 'utf8mb4',
                'after' => 'c_trx_offset_iban',
            ])
            ->changeColumn('c_trx_flag_electronic', 'integer', [
                'null' => true,
                'limit' => MysqlAdapter::INT_TINY,
                'after' => 'c_trx_offset_account_nr',
            ])
            ->changeColumn('c_trx_flag_cash', 'integer', [
                'null' => true,
                'limit' => MysqlAdapter::INT_TINY,
                'after' => 'c_trx_flag_electronic',
            ])
            ->changeColumn('c_trx_flag_card', 'integer', [
                'null' => true,
                'limit' => MysqlAdapter::INT_TINY,
                'after' => 'c_trx_flag_cash',
            ])
            ->changeColumn('c_trx_flag_fx', 'integer', [
                'null' => true,
                'limit' => MysqlAdapter::INT_TINY,
                'after' => 'c_trx_flag_card',
            ])
            ->changeColumn('c_trx_ref_variable', 'string', [
                'null' => true,
                'limit' => 255,
                'collation' => 'utf8mb4_0900_ai_ci',
                'encoding' => 'utf8mb4',
                'after' => 'c_trx_flag_fx',
            ])
            ->changeColumn('c_trx_ref_specific', 'string', [
                'null' => true,
                'limit' => 255,
                'collation' => 'utf8mb4_0900_ai_ci',
                'encoding' => 'utf8mb4',
                'after' => 'c_trx_ref_variable',
            ])
            ->changeColumn('c_trx_ref_internal', 'string', [
                'null' => true,
                'limit' => 255,
                'collation' => 'utf8mb4_0900_ai_ci',
                'encoding' => 'utf8mb4',
                'after' => 'c_trx_ref_specific',
            ])
            ->changeColumn('c_trx_ref_constant', 'string', [
                'null' => true,
                'limit' => 255,
                'collation' => 'utf8mb4_0900_ai_ci',
                'encoding' => 'utf8mb4',
                'after' => 'c_trx_ref_internal',
            ])
            ->changeColumn('c_trx_intl_volume', 'double', [
                'null' => true,
                'after' => 'c_trx_ref_constant',
            ])
            ->changeColumn('c_trx_intl_currency', 'string', [
                'null' => true,
                'limit' => 3,
                'collation' => 'utf8mb4_0900_ai_ci',
                'encoding' => 'utf8mb4',
                'after' => 'c_trx_intl_volume',
            ])
            ->addColumn('c_stor_name', 'string', [
                'null' => false,
                'limit' => 255,
                'collation' => 'utf8mb4_0900_ai_ci',
                'encoding' => 'utf8mb4',
                'after' => 'c_trx_intl_currency',
            ])
            ->save();
        $this->execute('SET unique_checks=1; SET foreign_key_checks=1;');
    }
}
