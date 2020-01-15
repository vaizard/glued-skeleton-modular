<?php
namespace Glued\Core\Install;
use Composer\Script\Event;
use Glued\Core\Classes\Crypto\Crypto;

class ComposerHooks
{
    public static function preInstall(Event $event) {
        $io = $event->getIO();
        if ($io->askConfirmation("Are you sure you want to proceed? ", false)) {
            return true;
        }
        exit;
    }

    public static function postPackageInstall(Event $event) {
        $installedPackage = $event->getComposer()->getPackage();
        // any tasks to run after the package is installed
    }

    public static function configTool(Event $event) {
        echo "*** STARTING THE COFIGURATION TESTING AND SETUP TOOL" . PHP_EOL;
        // get access to the current Composer instance
        $composer = $event->getComposer();
        // get access to the current ComposerIOConsoleIO
        // stream for terminal input/output
        $io = $event->getIO();
        // paths
        $fn['privkey']  = getcwd().'/private/crypto/private.key';
        $fn['pubkey']   = getcwd().'/private/crypto/public.key';
        $fn['phinx']    = getcwd().'/phinx.yml';
        $fn['settings'] = getcwd().'/glued/settings.php';
        // get settings interactively
        if ( !file_exists($fn['phinx']) or !file_exists($fn['settings']) ) {
          $ioresp['dbhost'] = $io->ask(">>> Mysql database host [127.0.0.1]: ", "127.0.0.1");
          $ioresp['dbname'] = $io->ask(">>> Mysql database name [glued]: ", "glued");
          $ioresp['dbuser'] = $io->ask(">>> Mysql database user [glued]: ", "glued");
          $ioresp['dbpass'] = $io->ask(">>> Mysql database pass [glued-pw]: ", "glued-pw");
        }
        if ( !file_exists($fn['privkey']) ) {
          $ioresp['rsabit'] = $io->ask(">>> What rsa key bitsize do you want to use, should be >=1024 [2048]: ", "2048");
        }
        // sanity check
        if ( !file_exists($fn['phinx']) or !file_exists($fn['settings']) ) {
          echo "*** Testing MySQL connection ..." . PHP_EOL;
          $link = mysqli_connect($ioresp['dbhost'], $ioresp['dbuser'], $ioresp['dbpass'], $ioresp['dbname']);
          if (!$link) {
            echo "!!! Unable to connect to MySQL." . PHP_EOL;
            echo "!!! Debugging errno: " . mysqli_connect_errno() . PHP_EOL;
            echo "!!! Debugging error: " . mysqli_connect_error() . PHP_EOL;
            die();
          }
          echo "+++ MySQL connection OK to " . mysqli_get_host_info($link) . PHP_EOL;
          mysqli_close($link);
        }
        // do whats missing
        if ( !file_exists($fn['privkey']) ) {
          echo "*** Generating private key, this can take a while. If it goes on for too long, reduce keybit size." . PHP_EOL;
          exec("openssl genrsa -out ".$fn['privkey']." ".$ioresp['rsabit']);
          if ( file_exists($fn['pubkey']) ) { rename($fn['pubkey'], $fn['pubkey'].'.bak'); }
        }
        if ( !file_exists($fn['pubkey']) ) {
          echo "*** Generating public key." . PHP_EOL;
          exec("openssl rsa -in ".$fn['privkey']." -pubout -out ".$fn['pubkey']);
        }
        if ( !file_exists($fn['phinx']) ) {
          echo "*** Generating phinx.yml." . PHP_EOL;
          $str=file_get_contents(getcwd().'/phinx.dist.yml');
          $str=str_replace("db_host", $ioresp['dbhost'], $str);
          $str=str_replace("production_db", $ioresp['dbname'], $str);
          $str=str_replace("db_user", $ioresp['dbuser'], $str);
          $str=str_replace("db_pass", $ioresp['dbpass'], $str);
          file_put_contents($fn['phinx'], $str);
          exec("php vendor/bin/phinx test -e production");
        }
        if ( !file_exists($fn['settings']) ) {
          echo "*** Generating /glued/settings.php." . PHP_EOL;
          $crypto = new Crypto;
          $str=file_get_contents(getcwd().'/glued/settings.dist.php');
          $str=str_replace("mail-encryption-key", $crypto->genkey_base64(), $str);
          $str=str_replace("db_host", $ioresp['dbhost'], $str);
          $str=str_replace("db_name", $ioresp['dbname'], $str);
          $str=str_replace("db_user", $ioresp['dbuser'], $str);
          $str=str_replace("db_pass", $ioresp['dbpass'], $str);
          file_put_contents($fn['settings'], $str);
        }
        echo "+++ ALL IS WELL CONFIGURED." . PHP_EOL;
    }

}