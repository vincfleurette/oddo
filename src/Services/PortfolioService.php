<?php

namespace App\Services;

/**
 * Service de calcul des statistiques de portefeuille
 */
class PortfolioService
{
    /**
     * Calcule les statistiques globales du portefeuille
     */
    public function calculateStats(array $accounts): array
    {
        $stats = [
            "totalValue" => 0,
            "totalPmvl" => 0,
            "totalPositions" => 0,
            "accountsCount" => count($accounts),
            "assetClasses" => [],
            "performance" => 0,
            "topPositions" => [],
            "worstPositions" => [],
        ];

        $allPositions = [];

        foreach ($accounts as $account) {
            $stats["totalValue"] += $account["value"];

            if (isset($account["positions"])) {
                foreach ($account["positions"] as $position) {
                    $stats["totalPositions"]++;
                    $stats["totalPmvl"] += $position["pmvl"] ?? 0;

                    $allPositions[] = $position;

                    // Grouper par classe d'actif
                    $assetClass =
                        $position["reportingAssetClassCode"] ?? "Unknown";
                    if (!isset($stats["assetClasses"][$assetClass])) {
                        $stats["assetClasses"][$assetClass] = [
                            "count" => 0,
                            "value" => 0,
                            "pmvl" => 0,
                        ];
                    }

                    $stats["assetClasses"][$assetClass]["count"]++;
                    $stats["assetClasses"][$assetClass]["value"] +=
                        $position["valeurMarcheDeviseSecurite"] ?? 0;
                    $stats["assetClasses"][$assetClass]["pmvl"] +=
                        $position["pmvl"] ?? 0;
                }
            }
        }

        // Calculer la performance globale
        if ($stats["totalValue"] > 0) {
            $stats["performance"] =
                ($stats["totalPmvl"] /
                    ($stats["totalValue"] - $stats["totalPmvl"])) *
                100;
        }

        // Top et worst positions
        $stats["topPositions"] = $this->getTopPositions(
            $allPositions,
            5,
            "performance"
        );
        $stats["worstPositions"] = $this->getWorstPositions(
            $allPositions,
            5,
            "performance"
        );

        return [
            "accounts" => $accounts,
            "stats" => $stats,
        ];
    }

    /**
     * Récupère les meilleures positions
     */
    private function getTopPositions(
        array $positions,
        int $limit,
        string $field
    ): array {
        usort($positions, function ($a, $b) use ($field) {
            $aValue = $a[$field] ?? ($a["perf"] ?? 0);
            $bValue = $b[$field] ?? ($b["perf"] ?? 0);
            return $bValue <=> $aValue; // Tri décroissant
        });

        return array_slice($positions, 0, $limit);
    }

    /**
     * Récupère les pires positions
     */
    private function getWorstPositions(
        array $positions,
        int $limit,
        string $field
    ): array {
        usort($positions, function ($a, $b) use ($field) {
            $aValue = $a[$field] ?? ($a["perf"] ?? 0);
            $bValue = $b[$field] ?? ($b["perf"] ?? 0);
            return $aValue <=> $bValue; // Tri croissant
        });

        return array_slice($positions, 0, $limit);
    }

    /**
     * Ajoute des statistiques de performance aux positions
     */
    public function addPerformanceStats(array $accounts): array
    {
        foreach ($accounts as &$account) {
            if (isset($account["positions"])) {
                foreach ($account["positions"] as &$position) {
                    $position[
                        "performanceFormatted"
                    ] = $this->formatPerformance($position["perf"] ?? 0);
                    $position["pmvlFormatted"] = $this->formatCurrency(
                        $position["pmvl"] ?? 0
                    );
                    $position["valueFormatted"] = $this->formatCurrency(
                        $position["valeurMarcheDeviseSecurite"] ?? 0
                    );
                }
            }
        }

        return $accounts;
    }

    /**
     * Formate une performance en pourcentage
     */
    private function formatPerformance(float $performance): array
    {
        return [
            "value" => $performance,
            "formatted" => sprintf("%+.2f%%", $performance),
            "isPositive" => $performance >= 0,
            "color" => $performance >= 0 ? "green" : "red",
        ];
    }

    /**
     * Formate une valeur monétaire
     */
    private function formatCurrency(float $amount): array
    {
        return [
            "value" => $amount,
            "formatted" => sprintf("%+.2f €", $amount),
            "isPositive" => $amount >= 0,
            "color" => $amount >= 0 ? "green" : "red",
        ];
    }
}
