?php


use Phinx\Migration\AbstractMigration;

class MyNewMigration extends AbstractMigration
{
    /**
     * Change Method.
     *
     * Write your reversible migrations using this method.
     *
     * More information on writing migrations is available here:
     * http://docs.phinx.org/en/latest/migrations.html#the-abstractmigration-class
     *
     * The following commands can be used in this method and Phinx will
     * automatically reverse them when rolling back:
     *
     *    createTable
     *    renameTable
     *    addColumn
     *    addCustomColumn
     *    renameColumn
     *    addIndex
     *    addForeignKey
     *
     * Any other destructive changes will result in an error when trying to
     * rollback the migration.
     *
     * Remember to call "create()" or "update()" and NOT "save()" when working
     * with the Table class.
     */
    public function change()
    {

        // create, klice, autoincrementy
        $count = $this->execute("

SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";
SET time_zone = \"+00:00\";

-- --------------------------------------------------------


--
-- Table structure for `t_core_authn_pw`
--

CREATE TABLE `t_core_authn_pw` (
  `c_uid` int(10) UNSIGNED NOT NULL COMMENT 'Unique row/object id' AUTO_INCREMENT,
  `c_user_id` int(10) UNSIGNED NOT NULL COMMENT 'Corresponds to t_core_users.c_uid',
  `c_username` varchar(255) NOT NULL,
  `c_password` varchar(255) COLLATE utf8mb4_bin DEFAULT NULL,
  `c_attr` json DEFAULT NULL COMMENT 'Object attributes (status: enabled/disabled, allowed IPs, etc.)',
  UNIQUE (c_uid),
  KEY `c_user_id` (`c_user_id`),
  KEY `c_username` (`c_username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Data for username/password based authentication';

-- --------------------------------------------------------


--
-- Table structure for `t_core_authn_token`
--

CREATE TABLE `t_core_authn_token` (
  `c_uid` int(10) UNSIGNED NOT NULL COMMENT 'Unique row/object id' AUTO_INCREMENT,
  `c_user_id` int(10) UNSIGNED NOT NULL COMMENT 'Corresponds to t_core_users.c_uid',
  `c_token` text NOT NULL,
  `c_attr` json DEFAULT NULL COMMENT 'Object attributes (status: enabled/disabled, allowed IPs, rate-limiting, etc.)',
  UNIQUE (c_uid),
  KEY `c_user_id` (`c_user_id`),
  KEY `c_token` (c_token(20))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Data for token-based authentication';

-- --------------------------------------------------------


--
-- Table structure for `t_core_users`
--

CREATE TABLE `t_core_users` (
  `c_uid` int(10) UNSIGNED NOT NULL COMMENT 'Unique row/object id' AUTO_INCREMENT,
  `c_screen_name` varchar(100) NOT NULL COMMENT 'User\'s Visible screen name (nickname)',
  `c_stor_name` varchar(100) GENERATED ALWAYS AS (`c_screen_name`) VIRTUAL COMMENT 'User\'s screen name in stor, glued\'s core CAS storage',
  `c_data` json DEFAULT NULL COMMENT 'User\'s profile data',
  `c_attr` json DEFAULT NULL COMMENT 'User\'s attributes (i.e. gdpr attributes)',
   UNIQUE (c_uid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Users table, their profile and their attributes';
        ");
    }
}

