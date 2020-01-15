<?php

use Phinx\Db\Adapter\MysqlAdapter;

class MailProcesslog extends Phinx\Migration\AbstractMigration
{
    public function change()
    {
        $this->execute('SET unique_checks=0; SET foreign_key_checks=0;');
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
                'limit' => '10',
                'signed' => false,
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
                'limit' => '10',
                'signed' => false,
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
                'signed' => false,
                'identity' => 'enable',
                'comment' => 'Unique row id',
                'after' => 'c_ts_finished',
            ])
            ->addColumn('c_user_id', 'integer', [
                'null' => false,
                'limit' => '10',
                'signed' => false,
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
                'limit' => '10',
                'signed' => false,
                'after' => 'c_data',
            ])
            ->addColumn('c_frequency', 'integer', [
                'null' => false,
                'limit' => '10',
                'signed' => false,
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
                'limit' => '10',
                'signed' => false,
                'identity' => 'enable',
                'comment' => 'Unique row id',
                'after' => 'c_ts_updated',
            ])
            ->addColumn('c_user_id', 'integer', [
                'null' => false,
                'limit' => '10',
                'signed' => false,
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
        $this->execute('SET unique_checks=1; SET foreign_key_checks=1;');
    }
}
