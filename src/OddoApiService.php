<?php

namespace App;

use App\DTO\AccountDTO;
use App\DTO\PositionDTO;
use App\DTO\AccountWithPositionsDTO;

/**
 * Business logic for Oddo API.
 */
class OddoApiService
{
    private OddoApi $api;

    public function __construct(OddoApi $api)
    {
        $this->api = $api;
    }

    public function fetchAccounts(): array
    {
        $resp = $this->api->request("POST", "accounts/FindLoginAccounts", [
            "CodeBureau" => "",
            "selectedFields" => [
                "valorisation",
                "performance",
                "especes",
                "securityAccountProviderLabel",
                "libelle",
                "CodFront",
            ],
            "culture" => "fr-FR",
        ]);
        $items = $resp["accountsTiers"]["principalsAccounts"] ?? [];
        return array_map(
            fn(array $i) => new AccountDTO([
                "accountNumber" => $i["accountNum"] ?? "",
                "label" => $i["libelle"] ?? "",
                "value" => floatval($i["valorisation"] ?? 0),
            ]),
            $items
        );
    }

    public function fetchPositions(
        string $accountNumber,
        ?string $arreteAu = null
    ): ?array {
        if ($arreteAu === null) {
            $d = new \DateTime("today");
            do {
                $d->modify("-1 day");
            } while (in_array((int) $d->format("N"), [6, 7]));
            $arreteAu = $d->format("Y-m-d");
        }
        $p = [
            "i" => 0,
            "p" => 10,
            "sf" => "",
            "sd" => "",
            "CodeBureau" => "",
            "AccountNums" => [$accountNumber],
            "Type" => 3,
            "ArreteAu" => $arreteAu,
            "culture" => "fr-FR",
        ];
        return $this->api->request(
            "POST",
            "accounts/FindAccountsPositions",
            $p
        );
    }

    public function fetchAccountsWithPositions(): array
    {
        $accounts = $this->fetchAccounts();
        $res = [];
        foreach ($accounts as $acc) {
            $raw = $this->fetchPositions($acc->accountNumber);
            $vals = $raw["values"] ?? [];
            $res[] = new AccountWithPositionsDTO(
                $acc->accountNumber,
                $acc->label,
                $acc->value,
                $vals
            );
        }
        return $res;
    }
}
