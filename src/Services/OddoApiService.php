<?php

namespace App\Services;

use App\External\OddoApiClientInterface;
use App\DTO\AccountWithPositionsDTO;

/**
 * Service principal pour la gestion des comptes et positions
 */
class OddoApiService
{
    private OddoApiClientInterface $apiClient;

    public function __construct(OddoApiClientInterface $apiClient)
    {
        $this->apiClient = $apiClient;
    }

    public function fetchAccountsWithPositions(): array
    {
        $accounts = $this->apiClient->getAccounts();
        $result = [];

        foreach ($accounts as $account) {
            $positions = $this->apiClient->getPositions(
                $account->accountNumber
            );
            $result[] = new AccountWithPositionsDTO(
                $account->accountNumber,
                $account->label,
                $account->value,
                $positions
            );
        }

        return $result;
    }
}
