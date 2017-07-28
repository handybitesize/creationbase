<?php

namespace CreationMedia\Utilities\Cache;

interface DeleteSomeInterface
{
    public function deleteSome($key);

    public function getAllKeys();
}
