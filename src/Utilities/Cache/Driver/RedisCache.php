<?php

namespace CreationMedia\Utilities\Cache\Driver;

use CreationMedia\Utilities\Cache\DeleteSomeInterface;
use CreationMedia\Utilities\Cache\OverLoadedCacheTrait;
use Doctrine\Common\Cache;

class RedisCache extends Cache\RedisCache implements DeleteSomeInterface
{
    use OverLoadedCacheTrait;

    protected function getFilename($id)
    {
        $filename = $id;

        return $this->directory
            .DIRECTORY_SEPARATOR
            .$filename
            .$this->extension;
    }

    public function deleteAll()
    {
        $keys = $this->getAllKeys();
        array_walk($keys, [$this, 'delete']);
    }
    public function getAllKeys()
    {
        return array_map(
            function ($key) {
                return substr($key, strlen($this->getNamespace()) + 1);
            },
            array_filter($this->getRedis()->keys('*'), function ($key) {
                return substr($key, 0, strlen($this->getNamespace()) + 1) === sprintf('%s.', $this->getNamespace());
            }));
    }

    public function getKeys($filter = null)
    {
        return array_filter($this->getAllKeys(), function ($key) use ($filter) {
            if (!$filter) {
                return true;
            }

            return substr($key, 0, strlen($filter)) === $filter;
        });
    }
}
