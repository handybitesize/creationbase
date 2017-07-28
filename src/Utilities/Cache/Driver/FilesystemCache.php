<?php

namespace CreationMedia\Utilities\Cache\Driver;

use CreationMedia\Utilities\Cache\DeleteSomeInterface;
use CreationMedia\Utilities\Cache\OverLoadedCacheTrait;
use Doctrine\Common\Cache;
use League\Flysystem\Adapter\Local;
use League\Flysystem\Filesystem;

class FilesystemCache extends Cache\FilesystemCache implements DeleteSomeInterface
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

    public function getAllKeys()
    {
        $fly = new Filesystem(new Local($this->directory));

        return array_map(function ($file) {
            return $file['extension'];
        }, $fly->listContents());
    }

    public function deleteAll()
    {
        $keys = $this->getAllKeys();
        array_walk($keys, [$this, 'delete']);
    }
}
