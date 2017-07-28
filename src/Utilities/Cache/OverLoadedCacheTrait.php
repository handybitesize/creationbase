<?php

namespace Creationmedia\Utilities\Cache;

use CreationMedia\CloudWatchLogger\Logger;

trait OverLoadedCacheTrait
{
    public function deleteSome($key)
    {
        $pattern = '/'.str_replace('*', '.+?', $key).'/i';
        $keys = preg_grep($pattern, $this->getAllKeys());

        array_walk($keys, [$this, 'delete']);
    }

    protected function getNamespacedId($id)
    {
        return sprintf('%s.%s', $this->getNamespace(), $id);
    }
    public function delete($id)
    {
        Logger::debug('DELETING '.$this->getNamespacedId($id));

        return $this->doDelete($this->getNamespacedId($id));
    }

    public function fetch($id)
    {
        return $this->doFetch($this->getNamespacedId($id));
    }

    public function save($id, $data, $lifeTime = 0)
    {
        return $this->doSave($this->getNamespacedId($id), $data, $lifeTime);
    }
    public function fetchMultiple(array $keys)
    {
        if (empty($keys)) {
            return array();
        }

        $namespacedKeys = array_combine($keys, array_map(array($this, 'getNamespacedId'), $keys));
        $items = $this->doFetchMultiple($namespacedKeys);
        $foundItems = array();

        foreach ($namespacedKeys as $requestedKey => $namespacedKey) {
            if (isset($items[$namespacedKey]) || array_key_exists($namespacedKey, $items)) {
                $foundItems[$requestedKey] = $items[$namespacedKey];
            }
        }

        return $foundItems;
    }

    public function contains($id)
    {
        return $this->doContains($this->getNamespacedId($id));
    }

    public function saveMultiple(array $keysAndValues, $lifetime = 0)
    {
        $namespacedKeysAndValues = array();
        foreach ($keysAndValues as $key => $value) {
            $namespacedKeysAndValues[$this->getNamespacedId($key)] = $value;
        }

        return $this->doSaveMultiple($namespacedKeysAndValues, $lifetime);
    }
}
