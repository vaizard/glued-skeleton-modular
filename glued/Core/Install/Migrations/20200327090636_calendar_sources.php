<?php

use Phinx\Db\Adapter\MysqlAdapter;

class CalendarSources extends Phinx\Migration\AbstractMigration
{
    public function change()
    {
        $this->execute('SET unique_checks=0; SET foreign_key_checks=0;');
        $this->table('t_calendar_sources', [
                'id' => false,
                'primary_key' => ['c_uid'],
                'engine' => 'InnoDB',
                'encoding' => 'utf8mb4',
                'collation' => 'utf8mb4_0900_ai_ci',
                'comment' => 'Uris to ICAL calendars and friends.',
                'row_format' => 'DYNAMIC',
            ])
            ->addColumn('c_domain_id', 'integer', [
                'null' => false,
                'limit' => '10',
                'signed' => false,
                'comment' => 'Domain id',
                'after' => 'c_attr',
            ])
            ->addColumn('c_flag_deleted', 'char', [
                'null' => false,
                'limit' => 5,
                'collation' => 'utf8mb4_0900_ai_ci',
                'encoding' => 'utf8mb4',
                'after' => 'c_domain_id',
            ])
            ->addIndex(['c_domain_id'], [
                'name' => 'c_domain_id',
                'unique' => false,
            ])
            ->addForeignKey('c_domain_id', 't_core_domains', 'c_uid', [
                'constraint' => 't_calendar_sources_ibfk_3',
                'update' => 'RESTRICT',
                'delete' => 'CASCADE',
            ])
            ->update();
        $this->table('t_calendar_uris')->drop()->save();
        $this->execute('SET unique_checks=1; SET foreign_key_checks=1;');
    }
}
