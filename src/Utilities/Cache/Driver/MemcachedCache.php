<?php

namespace CreationMedia\Utilities\Cache\Driver;

use CreationMedia\Utilities\Cache\DeleteSomeInterface;
use CreationMedia\Utilities\Cache\OverLoadedCacheTrait;
use Doctrine\Common\Cache;

class MemcachedCache extends Cache\MemcachedCache implements DeleteSomeInterface
{
    use OverLoadedCacheTrait;

    public function deleteAll()
    {
        $keys = $this->getAllKeys();
        /*
        foreach ($keys as $key) {
            d($key, $this->delete($key));
        }
        */
        array_walk($keys, [$this, 'delete']);
    }
    public function getAllKeys()
    {
        $mem = @fsockopen('127.0.0.1', 11211);
        if ($mem === false) {
            return -1;
        }

        // retrieve distinct slab
        $r = @fwrite($mem, 'stats items'.chr(10));
        if ($r === false) {
            return -2;
        }

        $slab = array();
        while (($l = @fgets($mem, 1024)) !== false) {
            // sortie ?
            $l = trim($l);
            if ($l == 'END') {
                break;
            }

            $m = array();
            // <STAT items:22:evicted_nonzero 0>
            $r = preg_match('/^STAT\sitems\:(\d+)\:/', $l, $m);
            if ($r != 1) {
                return -3;
            }
            $a_slab = $m[1];

            if (!array_key_exists($a_slab, $slab)) {
                $slab[$a_slab] = array();
            }
        }

        // recuperer les items
        reset($slab);
        foreach ($slab as $a_slab_key => &$a_slab) {
            $r = @fwrite($mem, 'stats cachedump '.$a_slab_key.' 100'.chr(10));
            if ($r === false) {
                return -4;
            }

            while (($l = @fgets($mem, 1024)) !== false) {
                // sortie ?
                $l = trim($l);
                if ($l == 'END') {
                    break;
                }

                $m = array();
                // ITEM 42 [118 b; 1354717302 s]
                $r = preg_match('/^ITEM\s([^\s]+)\s/', $l, $m);
                if ($r != 1) {
                    return -5;
                }
                $a_key = $m[1];

                $a_slab[] = $a_key;
            }
        }

        // close
        @fclose($mem);
        unset($mem);

        // transform it;
        $keys = array();
        reset($slab);
        foreach ($slab as &$a_slab) {
            reset($a_slab);
            foreach ($a_slab as &$a_key) {
                $keys[] = $a_key;
            }
        }
        unset($slab);
        sort($keys);

        return $keys;
    }

    public function save($id, $data, $lifeTime = 0)
    {
        return $this->doSave($this->getNamespacedId($id), $data, $lifeTime);
    }

    public function delete($id)
    {
        return $this->doDelete($this->getNamespacedId($id));
    }

 /*   public function getAllKeys()
    {
        $keys = array();
        $allSlabs = $this->getMemcache()->getExtendedStats('slabs');
        foreach ($allSlabs as $slabs) {
            foreach ($slabs as $slabId => $slabMeta) {
                if (!is_int($slabId)) {
                    continue;
                }
                $raw = current($this->getMemcache()->getExtendedStats('cachedump', $slabId, 0));
                $keys = array_merge($keys, array_keys($raw));
            }
        }
        asort($keys);
        $prefixLength = strlen($this->getNamespace() + 1);

        return array_map(function ($key) {
            $pattern = '/^'.$this->getNamespace().'\[(.+?)\]\[\d+\]$/';
            preg_match($pattern, $key, $match);

            return $match[1];
        }, $keys);
    }
    */
}
