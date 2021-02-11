<?php

use Phinx\Db\Adapter\MysqlAdapter;

class CoreAuthReset extends Phinx\Migration\AbstractMigration
{
    public function change()
    {
        $this->execute('SET unique_checks=0; SET foreign_key_checks=0;');
        $this->table('t_core_authn_reset', [
                'id' => false,
                'primary_key' => ['c_uid'],
                'engine' => 'InnoDB',
                'encoding' => 'utf8mb4',
                'collation' => 'utf8mb4_0900_ai_ci',
                'comment' => 'Reset access credentials',
                'row_format' => 'DYNAMIC',
            ])
            ->addColumn('c_user_id', 'integer', [
                'null' => false,
                'limit' => MysqlAdapter::INT_REGULAR,
                'comment' => 'Corresponds to t_core_users.c_uid',
                'after' => 'c_uid',
            ])
            ->addColumn('c_auth_id', 'integer', [
                'null' => false,
                'limit' => MysqlAdapter::INT_REGULAR,
                'comment' => 'Corresponds to t_core_authn.c_uid',
                'after' => 'c_user_id',
            ])
            ->removeColumn('c_user_uid')
            ->removeColumn('c_authn_uid')
            ->removeIndexByName("c_authn_uid")
            ->addIndex(['c_auth_id'], [
                'name' => 'c_authn_uid',
                'unique' => false,
            ])
            ->removeIndexByName("c_user_id")
            ->addIndex(['c_user_id'], [
                'name' => 'c_user_id',
                'unique' => false,
            ])
            ->save();
        $this->execute('SET unique_checks=1; SET foreign_key_checks=1;');
    }
}
