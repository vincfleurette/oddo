<?php

/**
 * Interface pour le client API Oddo
 */
namespace App\External;

use App\DTO\AccountDTO;

interface OddoApiClientInterface
{
    public function login(string $username, string $password): bool;
    public function getToken(): ?string;
    public function getUuid(): ?string;
    public function setToken(string $token): void;
    public function setUuid(?string $uuid): void;
    public function getAccounts(): array;
    public function getPositions(
        string $accountNumber,
        ?string $arreteAu = null
    ): array;
}
