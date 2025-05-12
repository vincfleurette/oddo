<?php

namespace App\DTO;

class AccountDTO
{
    public string $accountNumber;
    public string $label;
    public float $value;

    public function __construct(array $data)
    {
        $this->accountNumber = $data["accountNumber"];
        $this->label = $data["label"];
        $this->value = $data["value"];
    }

    public function toArray(): array
    {
        return [
            "accountNumber" => $this->accountNumber,
            "label" => $this->label,
            "value" => $this->value,
        ];
    }
}
