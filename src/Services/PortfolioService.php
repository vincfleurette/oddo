<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Service de calcul des statistiques de portefeuille compatibles iOS
 */
class PortfolioService
{
    /**
     * Calcule les statistiques complètes du portefeuille pour iOS
     */
    public function calculateStats(array $accounts): array
    {
        $stats = $this->calculatePortfolioStats($accounts);
        $accountsWithStats = $this->addAccountStats($accounts);

        return [
            "accounts" => $accountsWithStats,
            "portfolio" => $stats,
        ];
    }

    /**
     * Calcule les statistiques du portefeuille global
     */
    private function calculatePortfolioStats(array $accounts): array
    {
        $totalValue = 0;
        $totalPMVL = 0;
        $totalPMVR = 0;
        $totalWeight = 0;
        $positionsCount = 0;
        $allPositions = [];
        $assetClasses = [];

        foreach ($accounts as $account) {
            $totalValue += $account["value"];

            if (isset($account["positions"])) {
                foreach ($account["positions"] as $position) {
                    $positionsCount++;
                    $totalPMVL += $position["pmvl"] ?? 0;
                    $totalPMVR += $position["pmvr"] ?? 0;
                    $totalWeight += $position["weightMinute"] ?? 0;

                    // Ajouter le numéro de compte à la position pour le tracking
                    $position["accountNumber"] = $account["accountNumber"];
                    $allPositions[] = $position;

                    // Grouper par classe d'actif
                    $assetClass =
                        $position["reportingAssetClassCode"] ?? "Unknown";
                    if (!isset($assetClasses[$assetClass])) {
                        $assetClasses[$assetClass] = [
                            "totalValue" => 0,
                            "totalWeight" => 0,
                            "positionsCount" => 0,
                            "performanceSum" => 0,
                        ];
                    }

                    $assetClasses[$assetClass]["totalValue"] +=
                        $position["valeurMarcheDeviseSecurite"] ?? 0;
                    $assetClasses[$assetClass]["totalWeight"] +=
                        $position["weightMinute"] ?? 0;
                    $assetClasses[$assetClass]["positionsCount"]++;
                    $assetClasses[$assetClass]["performanceSum"] +=
                        $position["performance"] ?? 0;
                }
            }
        }

        // Calculer la performance pondérée globale
        $weightedPerformance = 0;
        if ($totalWeight > 0) {
            foreach ($allPositions as $position) {
                $weight = ($position["weightMinute"] ?? 0) / $totalWeight;
                $weightedPerformance +=
                    ($position["performance"] ?? 0) * $weight;
            }
        }

        // Préparer les classes d'actif avec statistiques
        $performanceByAssetClass = [];
        foreach ($assetClasses as $className => $data) {
            $averagePerformance =
                $data["positionsCount"] > 0
                    ? $data["performanceSum"] / $data["positionsCount"]
                    : 0;

            $weightedPerformanceForClass = 0;
            if ($data["totalWeight"] > 0) {
                foreach ($allPositions as $position) {
                    if (
                        ($position["reportingAssetClassCode"] ?? "Unknown") ===
                        $className
                    ) {
                        $weight =
                            ($position["weightMinute"] ?? 0) /
                            $data["totalWeight"];
                        $weightedPerformanceForClass +=
                            ($position["performance"] ?? 0) * $weight;
                    }
                }
            }

            $performanceByAssetClass[$className] = [
                "totalValue" => $data["totalValue"],
                "totalWeight" => $data["totalWeight"],
                "weightedPerformance" => $weightedPerformanceForClass,
                "positionsCount" => $data["positionsCount"],
                "averagePerformance" => $averagePerformance,
                "formatted" => [
                    "averagePerformance" => sprintf(
                        "%+.2f%%",
                        $averagePerformance
                    ),
                    "totalValue" => sprintf("%.2f €", $data["totalValue"]),
                    "performanceColor" =>
                        $averagePerformance >= 0 ? "green" : "red",
                ],
            ];
        }

        // Top et worst performers
        $topPerformers = $this->getTopPositions($allPositions, 5);
        $worstPerformers = $this->getWorstPositions($allPositions, 5);

        return [
            "totalValue" => $totalValue,
            "totalPMVL" => $totalPMVL,
            "totalPMVR" => $totalPMVR,
            "weightedPerformance" => $weightedPerformance,
            "totalWeight" => $totalWeight,
            "positionsCount" => $positionsCount,
            "accountsCount" => count($accounts),
            "performanceByAssetClass" => $performanceByAssetClass,
            "topPerformers" => $topPerformers,
            "worstPerformers" => $worstPerformers,
            "lastUpdate" => (new \DateTime())->format("Y-m-d\TH:i:s"),
            "formatted" => [
                "totalValue" => sprintf("%.2f €", $totalValue),
                "totalPMVL" => sprintf("%+.2f €", $totalPMVL),
                "weightedPerformance" => sprintf(
                    "%+.2f%%",
                    $weightedPerformance
                ),
                "pmvlColor" => $totalPMVL >= 0 ? "green" : "red",
                "performanceColor" =>
                    $weightedPerformance >= 0 ? "green" : "red",
            ],
        ];
    }

    /**
     * Ajoute des statistiques à chaque compte
     */
    private function addAccountStats(array $accounts): array
    {
        return array_map(function ($account) {
            $totalPMVL = 0;
            $totalWeight = 0;
            $performanceSum = 0;
            $positionsCount = count($account["positions"] ?? []);

            foreach ($account["positions"] ?? [] as $position) {
                $totalPMVL += $position["pmvl"] ?? 0;
                $totalWeight += $position["weightMinute"] ?? 0;
                $performanceSum += $position["performance"] ?? 0;
            }

            $weightedPerformance =
                $positionsCount > 0 ? $performanceSum / $positionsCount : 0;

            $account["stats"] = [
                "totalPMVL" => $totalPMVL,
                "weightedPerformance" => $weightedPerformance,
                "totalWeight" => $totalWeight,
                "positionsCount" => $positionsCount,
                "formatted" => [
                    "totalPMVL" => sprintf("%+.2f €", $totalPMVL),
                    "weightedPerformance" => sprintf(
                        "%+.2f%%",
                        $weightedPerformance
                    ),
                    "pmvlColor" => $totalPMVL >= 0 ? "green" : "red",
                    "performanceColor" =>
                        $weightedPerformance >= 0 ? "green" : "red",
                ],
            ];

            return $account;
        }, $accounts);
    }

    /**
     * Récupère les meilleures positions avec format iOS
     */
    private function getTopPositions(array $positions, int $limit): array
    {
        usort($positions, function ($a, $b) {
            $aPerf = $a["performance"] ?? 0;
            $bPerf = $b["performance"] ?? 0;
            return $bPerf <=> $aPerf; // Tri décroissant
        });

        return array_map(function ($position) {
            return [
                "isinCode" => $position["isinCode"] ?? "",
                "libInstrument" => $position["libInstrument"] ?? "",
                "performance" => $position["performance"] ?? 0,
                "valeurMarcheDeviseSecurite" =>
                    $position["valeurMarcheDeviseSecurite"] ?? 0,
                "weightMinute" => $position["weightMinute"] ?? 0,
                "accountNumber" => $position["accountNumber"] ?? "",
                "classActif" => $position["classActif"] ?? "",
                "formatted" => [
                    "performance" => sprintf(
                        "%+.2f%%",
                        $position["performance"] ?? 0
                    ),
                    "value" => sprintf(
                        "%.2f €",
                        $position["valeurMarcheDeviseSecurite"] ?? 0
                    ),
                    "weight" => sprintf(
                        "%.1f%%",
                        $position["weightMinute"] ?? 0
                    ),
                    "performanceColor" =>
                        ($position["performance"] ?? 0) >= 0 ? "green" : "red",
                ],
            ];
        }, array_slice($positions, 0, $limit));
    }

    /**
     * Récupère les pires positions avec format iOS
     */
    private function getWorstPositions(array $positions, int $limit): array
    {
        usort($positions, function ($a, $b) {
            $aPerf = $a["performance"] ?? 0;
            $bPerf = $b["performance"] ?? 0;
            return $aPerf <=> $bPerf; // Tri croissant
        });

        return array_map(function ($position) {
            return [
                "isinCode" => $position["isinCode"] ?? "",
                "libInstrument" => $position["libInstrument"] ?? "",
                "performance" => $position["performance"] ?? 0,
                "valeurMarcheDeviseSecurite" =>
                    $position["valeurMarcheDeviseSecurite"] ?? 0,
                "weightMinute" => $position["weightMinute"] ?? 0,
                "accountNumber" => $position["accountNumber"] ?? "",
                "classActif" => $position["classActif"] ?? "",
                "formatted" => [
                    "performance" => sprintf(
                        "%+.2f%%",
                        $position["performance"] ?? 0
                    ),
                    "value" => sprintf(
                        "%.2f €",
                        $position["valeurMarcheDeviseSecurite"] ?? 0
                    ),
                    "weight" => sprintf(
                        "%.1f%%",
                        $position["weightMinute"] ?? 0
                    ),
                    "performanceColor" =>
                        ($position["performance"] ?? 0) >= 0 ? "green" : "red",
                ],
            ];
        }, array_slice($positions, 0, $limit));
    }
}
