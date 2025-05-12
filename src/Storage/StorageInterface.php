<?php

namespace App\Storage;

use App\DTO\AccountWithPositionsDTO;

interface StorageInterface
{
    public function save(array $data): void;
    public function load(): array;
}
