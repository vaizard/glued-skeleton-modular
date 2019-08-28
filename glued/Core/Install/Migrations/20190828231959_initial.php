<?php

use Phinx\Migration\AbstractMigration;
use Phinx\Db\Adapter\MysqlAdapter;

class Initial extends AbstractMigration
{
    public function change()
    {
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
                'comment' => 'Object attributes (status: enabled/disabled, allowed IPs, etc.)',
            ])
            ->addColumn('c_password', 'string', [
                'null' => false,
                'limit' => 255,
                'collation' => 'utf8mb4_bin',
                'encoding' => 'utf8mb4',
                'comment' => 'Password',
                'after' => 'c_attr',
            ])
            ->addColumn('c_uid', 'integer', [
                'null' => false,
                'limit' => '10',
                'signed' => false,
                'identity' => 'enable',
                'comment' => 'Unique row/object id',
                'after' => 'c_password',
            ])
            ->addColumn('c_user_id', 'integer', [
                'null' => false,
                'limit' => '10',
                'signed' => false,
                'comment' => 'Corresponds to t_core_users.c_uid',
                'after' => 'c_uid',
            ])
            ->addColumn('c_username', 'string', [
                'null' => false,
                'limit' => 255,
                'collation' => 'utf8mb4_0900_ai_ci',
                'encoding' => 'utf8mb4',
                'comment' => 'Username',
                'after' => 'c_user_id',
            ])
        ->addIndex(['c_uid'], [
                'name' => 'c_uid',
                'unique' => true,
            ])
        ->addIndex(['c_user_id'], [
                'name' => 'c_user_id',
                'unique' => false,
            ])
        ->addIndex(['c_username'], [
                'name' => 'c_username',
                'unique' => false,
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
                'comment' => 'Account attributes (enabled/disabled, GDPR anonymised, etc.)',
            ])
            ->addColumn('c_data', 'json', [
                'null' => true,
                'comment' => 'Profile data',
                'after' => 'c_attr',
            ])
            ->addColumn('c_email', 'string', [
                'null' => false,
                'limit' => 255,
                'collation' => 'utf8mb4_0900_ai_ci',
                'encoding' => 'utf8mb4',
                'comment' => 'Primary email',
                'after' => 'c_data',
            ])
            ->addColumn('c_lang', 'char', [
                'null' => true,
                'limit' => 5,
                'collation' => 'utf8mb4_0900_ai_ci',
                'encoding' => 'utf8mb4',
                'comment' => 'Preferred language',
                'after' => 'c_email',
            ])
            ->addColumn('c_screenname', 'string', [
                'null' => false,
                'limit' => 255,
                'collation' => 'utf8mb4_0900_ai_ci',
                'encoding' => 'utf8mb4',
                'comment' => 'User\'s Visible screen name (nickname)',
                'after' => 'c_lang',
            ])
            ->addColumn('c_ts_created', 'timestamp', [
                'null' => true,
                'comment' => 'Timestamp: account created',
                'after' => 'c_screenname',
            ])
            ->addColumn('c_ts_modified', 'timestamp', [
                'null' => true,
                'comment' => 'Timestamp: account modified',
                'after' => 'c_ts_created',
            ])
            ->addColumn('c_uid', 'integer', [
                'null' => false,
                'limit' => '10',
                'signed' => false,
                'identity' => 'enable',
                'comment' => 'Unique row/object id',
                'after' => 'c_ts_modified',
            ])
        ->addIndex(['c_uid'], [
                'name' => 'c_uid',
                'unique' => true,
            ])
            ->create();
    }
}
