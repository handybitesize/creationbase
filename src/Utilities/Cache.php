<?php

namespace CreationMedia\Utilities;

use CreationMedia\Config;
use CreationMedia\Exception\CacheException;
use CreationMedia\Utilities\Cache\DeleteSomeInterface;
use CreationMedia\Utilities\Cache\Driver\FilesystemCache;
use CreationMedia\Utilities\Cache\Driver\MemcachedCache;
use CreationMedia\Utilities\Cache\Driver\RedisCache;
use CreationMedia\CloudWatchLogger\Logger;

class Cache
{
    protected static $_instance;
    public $driver;

    public static function instance()
    {
        if (!self::$_instance) {
            self::$_instance = new self();
        }

        return self::$_instance;
    }

    protected function __construct()
    {
    }

    public function load($dsn, $prefix = '')
    {
        if ($this->driver) {
            return;
        }
        if (!$dsn) {
            throw new CacheException\DSNNotFoundException('Could not find DSN in config');
        }

        $dsn = explode('=', $dsn);
        if (count($dsn) !== 2) {
            throw new CacheException\InvalidDSNException('DSN must be in format of [CacheType]=[Config]');
        }

        switch ($dsn[0]) {
            case 'folder':
                $cacheDriver = new FilesystemCache($dsn[1]);
                break;
            /*
                        case 'memcached':
                            d('using memcached');
                            $dsn[1] = explode(':', $dsn[1]);
                            if (count($dsn[1]) !== 2) {
                                throw new CacheException\InvalidDSNException('Memcached DSN must be in format of [Host]:[Port]');
                            }
                            $memcached = new \Memcached();
            //                $memcached->setOption(\Memcached::OPT_BINARY_PROTOCOL, true);
                            $memcached->addServer($dsn[1][0], $dsn[1][1]);
                            $cacheDriver = new MemcachedCache();
                            $cacheDriver->setMemcached($memcached);
                            break;
            */
            case 'redis':
                $dsn[1] = explode(':', $dsn[1]);
                if (count($dsn[1]) !== 2) {
                    throw new CacheException\InvalidDSNException('Redis DSN must be in format of [Host]:[Port]');
                }
                $redis = new \Redis();
                $redis->connect($dsn[1][0], $dsn[1][1]);

                $cacheDriver = new RedisCache();
                $cacheDriver->setRedis($redis);
                break;
            default:
                throw new CacheException\UnknownCacheTypeException(sprintf('Unsupported cache type "%s" supplied in DSN', $dsn[0]));
                break;

        }
        $cacheDriver->setNamespace($prefix);
        $this->setDriver($cacheDriver);

        return $this;
    }

    public function getOrSet($key, callable $getter, $ttl = null)
    {
        if (!$this->exists($key, $out)) {
            $out = call_user_func($getter);
            if ($out) {
                $this->set($key, $out, $ttl);
            }
        }

        return $out;
    }
    public function index($key, $ids)
    {
        trigger_error('index is not supported by cache', E_USER_WARNING);
    }
    public function set($key, $val, $ttl = null)
    {
        if (Config::get('CACHE_DISABLE') !== 'true') {
            return false;
        }

        Logger::debug(sprintf('Cache: Setting \'%s\'', $key));
        $this->driver->save($key, serialize($val), $ttl);

        return $this;
    }
    public function list()
    {
        return $this->driver->getAllKeys();
    }
    public function exists($key, &$value = null)
    {
        if (Config::get('CACHE_DISABLE') == 'true') {
            return false;
        }

        $value = $this->get($key);

        return  $value == true;
    }
    public function clear($key)
    {
        Logger::debug(sprintf('Cache: Clearing \'%s\'', $key));

        return strpos($key, '*') !== false ? $this->driver->deleteSome($key) : $this->driver->delete($key);
    }
    public function clearWildcard($key, $ids = array())
    {
        trigger_error('clear wildcard should not be called, use clear instrad.', E_USER_DEPRECATED);

        return $this->clear($key);
    }
    public function reset($suffix = null, $lifetime = 0)
    {
        return $this->driver->deleteAll();
    }
    public function get($key)
    {
        $message = sprintf('Cache: Getting \'%s\'', $key);
        if ($value = $this->driver->fetch($key)) {
            $message .= ' (HIT)';
        } else {
            $message .= ' (MISS)';
        }
        // Logger::debug($message);

        return unserialize($value);
    }

    public function getDriver()
    {
        return $this->driver;
    }

    public function setDriver(DeleteSomeInterface $driver)
    {
        $this->driver = $driver;

        return $this;
    }
    public function resetTwig()
    {
        Logger::info(sprintf('Resetting twig'));
        $twig = new Twig();
        $dir = $twig->getCache();

        $di = new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS);
        $ri = new \RecursiveIteratorIterator($di, \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($ri as $file) {
            $file->isDir() ? rmdir($file) : unlink($file);
        }

        return true;
    }
}
