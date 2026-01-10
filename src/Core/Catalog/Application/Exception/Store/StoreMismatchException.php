<?php

declare(strict_types=1);

namespace Pimelo\Core\Catalog\Application\Exception\Store;

class StoreMismatchException extends \Exception
{
    public function __construct()
    {
        parent::__construct('Category and Product belong to different stores.');
    }
}
