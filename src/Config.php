<?php
/**
 * Created by PhpStorm.
 * User: carey
 * Date: 30/08/16
 * Time: 14:06
 */

namespace CreationMedia;
use Aws\Credentials\Credentials;
use Aws\Exception\CredentialsException;
use CreationMedia\Utilities\General;
use DB\SQL;
use GuzzleHttp\Promise;
use GuzzleHttp\Promise\RejectedPromise;


class Config
{

    public static function bootstrap($router = 'web')
    {
        date_default_timezone_set('UTC');
        ini_set('session.gc_maxlifetime', 7200);

        $f3 = \Base::instance();

        if (General::isAWS()) {
            if (file_exists('/etc/profile.d/eb_envvars.sh')) {
                // its ec2 cli
                self::loadEbEnvVars();
            }
        } else {
            $configFile = sprintf("%s/LocalConfig.php", $f3->get('ROOT'));

            require($configFile);
            new \LocalConfig();
        }

        $f3->set('DEBUG', Config::get('F3_DEBUG'));
        $f3->set('DB', self::loadDb());

        if (self::get('APPLICATION_TYPE') == 'web') {
            new Session();
        }

        \Kint::$enabled_mode = (self::get('ENVIRONMENT') == 'development');
        \Kint::$aliases[] = 'ddd';
        \Kint::$aliases[] = 'sd';
        \Kint::$plugins[] = 'Kint_Parser_Microtime';


        require_once dirname(__FILE__).'/init_helpers.php';


        if ($f3->get('SERVER.HTTP_HOST')) {
            $host = $_SERVER['HTTP_HOST'];
        } else {
            $host = self::get('BASE_URL');
            $hostarray = explode('/', $host);
            $host = array_pop($hostarray);
        }
        DEFINE('HOST_TYPE', explode('.', $host)[0]);
        //ini_set('error_log', sprintf('/tmp/php-%s-errors.log', $router));

        if (substr(self::get('BASE_PATH'), -1) != '/') {
            die('BASE_PATH must end with a trailing slash');
        }

        if (PHP_SAPI === 'cli') {
            DEFINE('EOL', PHP_EOL);
        } else {
            DEFINE('EOL', '<br/>');
        }
    }



    public static function loadDb()
    {
        $dbConStr = sprintf(
            'mysql:host=%s;port=3306;dbname=%s',
            self::get('DB_HOSTNAME'),
            self::get('DB_DATABASE')
        );
        $dbConStr = str_replace('"', '', $dbConStr);
        $dbOptions = [];
        if (!array_key_exists('EB_ROOT', $_ENV)) {
            $dbOptions = array(
                \PDO::ATTR_PERSISTENT => true,  // we want to use persistent connections
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION
            );
        }

        try {
            $db = null;
            $limit = 10;
            $counter = 0;
            while (true) {
                try {
                    $db = new SQL(
                        $dbConStr,
                        self::get('DB_USERNAME'),
                        self::get('DB_PASSWORD'),
                        $dbOptions
                    );
                    $db->exec( "SET CHARACTER SET utf8" );
                    break;
                }
                catch (\PDOException $e) {
                    $db = null;
                    $counter++;
                    if ($counter == $limit) {
                        throw $e;
                    }
                }
            }
            return $db;
        } catch (\PDOException $e) {
            switch ($e->getCode()) {
                case 2002:
                default:
                    die ('Cannot connect to database');
                    break;
            }

        }
    }

    public static function loadEbEnvVars()
    {
        $vars = trim(file_get_contents('/etc/profile.d/eb_envvars.sh'));
        $vars = explode("\n", $vars);
        foreach ($vars as $var) {
            $var = str_replace('export ', '', $var);
            putenv(str_replace('"', '', trim($var)));
        }
    }

    /**
     * @param $var
     *
     * @return int
     * @throws \Exception
     */

    public static function get($var)
    {
        if (array_key_exists($var, $_ENV)) {
            return $_ENV[$var];
        } elseif (getenv($var)) {
            return getenv($var);
        } else {
            throw new \Exception('Unknown variable: '.$var);
        }
    }

    public static function getInstanceId()
    {
        $hv_uuid = @exec('cat /sys/hypervisor/uuid');
        if ($hv_uuid) {
            return file_get_contents("http://169.254.169.254/latest/meta-data/instance-id");
        }
        return gethostname();
    }



// This function CREATES a credential provider.
    public static function get_aws_creds()
    {
        // This function IS the credential provider.
        return function () {
            // Use credentials from environment variables, if available
            $key = self::get('AWS_ACCESS_KEY_ID');
            $secret = self::get('AWS_SECRET_KEY');
            if ($key && $secret) {
                return Promise\promise_for(
                    new Credentials($key, $secret)
                );
            }
            $msg = 'Could not find environment variable '
                . 'credentials in AWS_ACCESS_KEY_ID/AWS_SECRET_KEY';
            return new RejectedPromise(new CredentialsException($msg));
        };
    }
}
