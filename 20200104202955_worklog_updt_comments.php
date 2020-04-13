<?php

use Phinx\Db\Adapter\MysqlAdapter;

class WorklogUpdtComments extends Phinx\Migration\AbstractMigration
{
    public function change()
    {
        $this->execute('SET unique_checks=0; SET foreign_key_checks=0;');
        $this->table('t_calendar_uris', [
                'id' => false,
                'primary_key' => ['c_uid'],
                'engine' => 'InnoDB',
                'encoding' => 'utf8mb4',
                'collation' => 'utf8mb4_0900_ai_ci',
                'comment' => 'Uris to ICAL calendars and friends.',
                'row_format' => 'DYNAMIC',
            ])
            ->changeColumn('c_attr', 'json', [
                'null' => false,
                'comment' => 'Calendar attributes',
            ])
            ->changeColumn('c_json', 'json', [
                'null' => false,
                'comment' => 'Calendar data (json document)',
                'after' => 'c_attr',
            ])
            ->changeColumn('c_ts_created', 'timestamp', [
                'null' => true,
                'default' => 'CURRENT_TIMESTAMP',
                'update' => 'CURRENT_TIMESTAMP',
                'comment' => 'Timestamp created',
                'after' => 'c_json',
            ])
            ->changeColumn('c_ts_updated', 'timestamp', [
                'null' => true,
                'default' => 'CURRENT_TIMESTAMP',
                'comment' => 'Timestamp updated',
                'after' => 'c_ts_created',
            ])
            ->changeColumn('c_uid', 'integer', [
                'null' => false,
                'limit' => '10',
                'signed' => false,
                'identity' => 'enable',
                'comment' => 'Unique row id',
                'after' => 'c_ts_updated',
            ])
            ->changeColumn('c_user_id', 'integer', [
                'null' => false,
                'limit' => '10',
                'signed' => false,
                'comment' => 'Creator of the calendar (account)',
                'after' => 'c_uid',
            ])
            ->save();
        $this->table('t_core_authn', [
                'id' => false,
                'primary_key' => ['c_uid'],
                'engine' => 'InnoDB',
                'encoding' => 'utf8mb4',
                'collation' => 'utf8mb4_0900_ai_ci',
                'comment' => 'Access credentials to users defined in t_core_users. Each user is allowed a secondary name/pass combination (plausible deniability)',
                'row_format' => 'DYNAMIC',
            ])
            ->changeColumn('c_attr', 'json', [
                'null' => true,
                'comment' => 'Object attributes (status: enabled/disabled, allowed IPs, scope - i.e. allwed/forbidden actions, rate limits, etc.)',
            ])
            ->changeColumn('c_hash', 'string', [
                'null' => false,
                'limit' => 255,
                'collation' => 'ascii_bin',
                'encoding' => 'ascii',
                'comment' => 'Password or api key hash (according to c_type)',
                'after' => 'c_attr',
            ])
            ->changeColumn('c_ts_created', 'timestamp', [
                'null' => true,
                'default' => 'CURRENT_TIMESTAMP',
                'after' => 'c_hash',
            ])
            ->changeColumn('c_ts_modified', 'timestamp', [
                'null' => true,
                'default' => 'CURRENT_TIMESTAMP',
                'update' => 'CURRENT_TIMESTAMP',
                'after' => 'c_ts_created',
            ])
            ->changeColumn('c_type', 'integer', [
                'null' => false,
                'limit' => MysqlAdapter::INT_TINY,
                'comment' => '0 password, 1 api key',
                'after' => 'c_ts_modified',
            ])
            ->changeColumn('c_uid', 'integer', [
                'null' => false,
                'limit' => '10',
                'signed' => false,
                'identity' => 'enable',
                'comment' => 'Unique row/object id',
                'after' => 'c_type',
            ])
            ->changeColumn('c_user_uid', 'integer', [
                'null' => true,
                'limit' => '10',
                'signed' => false,
                'comment' => 'Corresponds to t_core_users.c_uid',
                'after' => 'c_uid',
            ])
            ->save();
        $this->table('t_core_domains', [
                'id' => false,
                'primary_key' => ['c_uid'],
                'engine' => 'InnoDB',
                'encoding' => 'utf8mb4',
                'collation' => 'utf8mb4_0900_ai_ci',
                'comment' => 'Domains (as in RBAC domains)',
                'row_format' => 'DYNAMIC',
            ])
            ->changeColumn('c_name', 'string', [
                'null' => false,
                'limit' => 255,
                'collation' => 'utf8mb4_0900_ai_ci',
                'encoding' => 'utf8mb4',
                'comment' => 'Domain name',
            ])
            ->changeColumn('c_ts_created', 'timestamp', [
                'null' => true,
                'default' => 'CURRENT_TIMESTAMP',
                'update' => 'CURRENT_TIMESTAMP',
                'comment' => 'Timestamp created',
                'after' => 'c_name',
            ])
            ->changeColumn('c_ts_updated', 'timestamp', [
                'null' => true,
                'default' => 'CURRENT_TIMESTAMP',
                'comment' => 'Timestamp updated',
                'after' => 'c_ts_created',
            ])
            ->changeColumn('c_uid', 'integer', [
                'null' => false,
                'limit' => '10',
                'signed' => false,
                'identity' => 'enable',
                'comment' => 'Unique row id',
                'after' => 'c_ts_updated',
            ])
            ->changeColumn('c_user_id', 'integer', [
                'null' => false,
                'limit' => '10',
                'signed' => false,
                'comment' => 'Creator of the domain',
                'after' => 'c_uid',
            ])
            ->save();
        $this->table('t_core_profiles', [
                'id' => false,
                'primary_key' => ['c_uid'],
                'engine' => 'InnoDB',
                'encoding' => 'utf8mb4',
                'collation' => 'utf8mb4_0900_ai_ci',
                'comment' => 'Users\' system profiles (personal, invoicing, etc.)',
                'row_format' => 'DYNAMIC',
            ])
            ->changeColumn('c_json', 'json', [
                'null' => false,
                'comment' => 'Profile data',
            ])
            ->changeColumn('c_uid', 'integer', [
                'null' => false,
                'limit' => '10',
                'signed' => false,
                'identity' => 'enable',
                'comment' => 'Unique row id',
                'after' => 'c_json',
            ])
            ->changeColumn('c_users_uid', 'integer', [
                'null' => false,
                'limit' => '10',
                'signed' => false,
                'comment' => 'Profile owner',
                'after' => 'c_uid',
            ])
            ->save();
        $this->table('t_core_users', [
                'id' => false,
                'primary_key' => ['c_uid'],
                'engine' => 'InnoDB',
                'encoding' => 'utf8mb4',
                'collation' => 'utf8mb4_0900_ai_ci',
                'comment' => 'Users\' table, their profile and their attributes',
                'row_format' => 'DYNAMIC',
            ])
            ->changeColumn('c_attr', 'json', [
                'null' => true,
                'comment' => 'User\'s account attributes (enabled/disabled, GDPR anonymised, etc.)',
            ])
            ->changeColumn('c_email', 'string', [
                'null' => false,
                'limit' => 255,
                'collation' => 'utf8mb4_0900_ai_ci',
                'encoding' => 'utf8mb4',
                'comment' => 'Primary email',
                'after' => 'c_attr',
            ])
            ->changeColumn('c_lang', 'char', [
                'null' => true,
                'default' => 'en_US',
                'limit' => 5,
                'collation' => 'utf8mb4_0900_ai_ci',
                'encoding' => 'utf8mb4',
                'comment' => 'Preferred language',
                'after' => 'c_email',
            ])
            ->changeColumn('c_ts_created', 'timestamp', [
                'null' => true,
                'default' => 'CURRENT_TIMESTAMP',
                'comment' => 'Timestamp: account created',
                'after' => 'c_name',
            ])
            ->changeColumn('c_ts_modified', 'timestamp', [
                'null' => true,
                'default' => 'CURRENT_TIMESTAMP',
                'update' => 'CURRENT_TIMESTAMP',
                'comment' => 'Timestamp: account modified',
                'after' => 'c_ts_created',
            ])
            ->changeColumn('c_uid', 'integer', [
                'null' => false,
                'limit' => '10',
                'signed' => false,
                'identity' => 'enable',
                'comment' => 'Unique row/object id',
                'after' => 'c_ts_modified',
            ])
            ->save();
        $this->table('t_store_items', [
                'id' => false,
                'primary_key' => ['c_uid'],
                'engine' => 'InnoDB',
                'encoding' => 'utf8mb4',
                'collation' => 'utf8mb4_0900_ai_ci',
                'comment' => 'Store items are just regular store items (products and one-time services for purchase)',
                'row_format' => 'DYNAMIC',
            ])
            ->changeColumn('c_attr', 'json', [
                'null' => false,
                'comment' => 'Item attributes',
            ])
            ->changeColumn('c_json', 'json', [
                'null' => false,
                'comment' => 'Item data (json document)',
                'after' => 'c_attr',
            ])
            ->changeColumn('c_seller_id', 'integer', [
                'null' => false,
                'limit' => '10',
                'signed' => false,
                'comment' => 'Seller of this particular item',
                'after' => 'c_json',
            ])
            ->changeColumn('c_ts_created', 'timestamp', [
                'null' => true,
                'default' => 'CURRENT_TIMESTAMP',
                'update' => 'CURRENT_TIMESTAMP',
                'comment' => 'Timestamp created',
                'after' => 'c_seller_id',
            ])
            ->changeColumn('c_ts_updated', 'timestamp', [
                'null' => true,
                'default' => 'CURRENT_TIMESTAMP',
                'comment' => 'Timestamp updated',
                'after' => 'c_ts_created',
            ])
            ->changeColumn('c_uid', 'integer', [
                'null' => false,
                'limit' => '10',
                'signed' => false,
                'identity' => 'enable',
                'comment' => 'Unique row id',
                'after' => 'c_ts_updated',
            ])
            ->save();
        $this->table('t_store_sellers', [
                'id' => false,
                'primary_key' => ['c_uid'],
                'engine' => 'InnoDB',
                'encoding' => 'utf8mb4',
                'collation' => 'utf8mb4_0900_ai_ci',
                'comment' => 'Sellers are entities who sell stuff at specified conditions (think aliexpress or amazon sellers)',
                'row_format' => 'DYNAMIC',
            ])
            ->changeColumn('c_attr', 'json', [
                'null' => false,
                'comment' => 'Seller attributes',
            ])
            ->changeColumn('c_json', 'json', [
                'null' => false,
                'comment' => 'Seller data (json document)',
                'after' => 'c_attr',
            ])
            ->changeColumn('c_ts_created', 'timestamp', [
                'null' => true,
                'default' => 'CURRENT_TIMESTAMP',
                'update' => 'CURRENT_TIMESTAMP',
                'comment' => 'Timestamp created',
                'after' => 'c_json',
            ])
            ->changeColumn('c_ts_updated', 'timestamp', [
                'null' => true,
                'default' => 'CURRENT_TIMESTAMP',
                'comment' => 'Timestamp updated',
                'after' => 'c_ts_created',
            ])
            ->changeColumn('c_uid', 'integer', [
                'null' => false,
                'limit' => '10',
                'signed' => false,
                'identity' => 'enable',
                'comment' => 'Unique row id',
                'after' => 'c_ts_updated',
            ])
            ->changeColumn('c_user_id', 'integer', [
                'null' => false,
                'limit' => '10',
                'signed' => false,
                'comment' => 'Creator of the seller (account)',
                'after' => 'c_uid',
            ])
            ->save();
        $this->table('t_store_subscriptions', [
                'id' => false,
                'primary_key' => ['c_uid'],
                'engine' => 'InnoDB',
                'encoding' => 'utf8mb4',
                'collation' => 'utf8mb4_0900_ai_ci',
                'comment' => 'Subscriptions are recurring purchase items (donations, memberships, internet subscriptions, periodicals delivery, etc.)',
                'row_format' => 'DYNAMIC',
            ])
            ->changeColumn('c_attr', 'json', [
                'null' => false,
                'comment' => 'Subscription attributes',
            ])
            ->changeColumn('c_json', 'json', [
                'null' => false,
                'comment' => 'Subscription data (json document)',
                'after' => 'c_attr',
            ])
            ->changeColumn('c_seller_id', 'integer', [
                'null' => false,
                'limit' => '10',
                'signed' => false,
                'comment' => 'Seller of this particular subscription',
                'after' => 'c_json',
            ])
            ->changeColumn('c_ts_created', 'timestamp', [
                'null' => true,
                'default' => 'CURRENT_TIMESTAMP',
                'update' => 'CURRENT_TIMESTAMP',
                'comment' => 'Timestamp created',
                'after' => 'c_seller_id',
            ])
            ->changeColumn('c_ts_updated', 'timestamp', [
                'null' => true,
                'default' => 'CURRENT_TIMESTAMP',
                'comment' => 'Timestamp updated',
                'after' => 'c_ts_created',
            ])
            ->changeColumn('c_uid', 'integer', [
                'null' => false,
                'limit' => '10',
                'signed' => false,
                'identity' => 'enable',
                'comment' => 'Unique row id',
                'after' => 'c_ts_updated',
            ])
            ->save();
        $this->table('t_store_tickets', [
                'id' => false,
                'primary_key' => ['c_uid'],
                'engine' => 'InnoDB',
                'encoding' => 'utf8mb4',
                'collation' => 'utf8mb4_0900_ai_ci',
                'comment' => 'Tickets are time-limited booking and item offerings (tickets to conferences, workshops, merch, etc.)',
                'row_format' => 'DYNAMIC',
            ])
            ->changeColumn('c_attr', 'json', [
                'null' => false,
                'comment' => 'Ticket attributes',
            ])
            ->changeColumn('c_json', 'json', [
                'null' => false,
                'comment' => 'Ticket data (json document)',
                'after' => 'c_attr',
            ])
            ->changeColumn('c_seller_id', 'integer', [
                'null' => false,
                'limit' => '10',
                'signed' => false,
                'comment' => 'Ticket seller id',
                'after' => 'c_json',
            ])
            ->changeColumn('c_ts_created', 'timestamp', [
                'null' => true,
                'default' => 'CURRENT_TIMESTAMP',
                'update' => 'CURRENT_TIMESTAMP',
                'comment' => 'Timestamp created',
                'after' => 'c_seller_id',
            ])
            ->changeColumn('c_ts_updated', 'timestamp', [
                'null' => true,
                'default' => 'CURRENT_TIMESTAMP',
                'comment' => 'Timestamp updated',
                'after' => 'c_ts_created',
            ])
            ->changeColumn('c_uid', 'integer', [
                'null' => false,
                'limit' => '10',
                'signed' => false,
                'identity' => 'enable',
                'comment' => 'Unique row id',
                'after' => 'c_ts_updated',
            ])
            ->save();
        $this->table('t_worklog_items', [
                'id' => false,
                'primary_key' => ['c_uid'],
                'engine' => 'InnoDB',
                'encoding' => 'utf8mb4',
                'collation' => 'utf8mb4_0900_ai_ci',
                'comment' => 'Sellers are entities who sell stuff at specified conditions (think aliexpress or amazon sellers)',
                'row_format' => 'DYNAMIC',
            ])
            ->changeColumn('c_domain_id', 'integer', [
                'null' => false,
                'limit' => '10',
                'signed' => false,
            ])
            ->changeColumn('c_json', 'json', [
                'null' => false,
                'comment' => 'Worklog item data (json document)',
                'after' => 'c_domain_id',
            ])
            ->changeColumn('c_ts_created', 'timestamp', [
                'null' => false,
                'default' => 'CURRENT_TIMESTAMP',
                'comment' => 'Timestamp created',
                'after' => 'c_json',
            ])
            ->changeColumn('c_ts_updated', 'timestamp', [
                'null' => false,
                'default' => 'CURRENT_TIMESTAMP',
                'update' => 'CURRENT_TIMESTAMP',
                'comment' => 'Timestamp updated',
                'after' => 'c_ts_created',
            ])
            ->changeColumn('c_uid', 'integer', [
                'null' => false,
                'limit' => '10',
                'signed' => false,
                'identity' => 'enable',
                'comment' => 'Unique row id',
                'after' => 'c_ts_updated',
            ])
            ->changeColumn('c_user_id', 'integer', [
                'null' => false,
                'limit' => '10',
                'signed' => false,
                'comment' => 'Creator of the worklog item (account)',
                'after' => 'c_uid',
            ])
            ->save();
        $this->execute('SET unique_checks=1; SET foreign_key_checks=1;');
    }
}
