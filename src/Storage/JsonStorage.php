<?php

namespace App\Storage;

use App\DTO\AccountWithPositionsDTO;

class JsonStorage implements StorageInterface
{
    private string $filePath;

    public function __construct(
        string $filePath =
            __DIR__ . "/../../storage/accounts_with_positions.json"
    ) {
        $this->filePath = $filePath;
    }

    public function save(array $data): void
    {
        $payload = [
            "timestamp" => (new \DateTimeImmutable())->format("c"),
            "data" => array_map(
                fn(AccountWithPositionsDTO $dto) => $dto->toArray(),
                $data
            ),
        ];
        file_put_contents(
            $this->filePath,
            json_encode($payload, JSON_PRETTY_PRINT)
        );
    }

    public function load(): array
    {
        if (!file_exists($this->filePath)) {
            return [];
        }
        $raw = json_decode((string) file_get_contents($this->filePath), true);
        $items = $raw["data"] ?? [];
        return array_map(
            fn($d) => new AccountWithPositionsDTO(
                $d["accountNumber"],
                $d["label"],
                $d["value"],
                $d["positions"]
            ),
            $items
        );
    }
}
