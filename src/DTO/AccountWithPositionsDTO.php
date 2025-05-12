<?php

namespace App\DTO;

class AccountWithPositionsDTO
{
    public string $accountNumber;
    public string $label;
    public float $value;
    public array $positions;

    public function __construct(string $a, string $l, float $v, array $raw)
    {
        $this->accountNumber = $a;
        $this->label = $l;
        $this->value = $v;
        $this->positions = array_map(fn($d) => new PositionDTO($d), $raw);
    }

    public function toArray(): array
    {
        return [
            "accountNumber" => $this->accountNumber,
            "label" => $this->label,
            "value" => $this->value,
            "positions" => array_map(fn($p) => $p->toArray(), $this->positions),
        ];
    }
}
