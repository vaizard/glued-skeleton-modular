<?php

use Phinx\Db\Adapter\MysqlAdapter;

class RenameCalendarUris extends Phinx\Migration\AbstractMigration
{
    /**
     * Migrate Up.
     */
    public function up()
    {
        $table = $this->table('t_calendar_uris');
        $table->rename('t_calendar_sources');
        $table->save();
    }

    /**
     * Migrate Down.
     */
    public function down()
    {
        $table = $this->table('t_calendar_sources');
        $table->rename('t_calendar_uris');
        $table->save();
    }
}