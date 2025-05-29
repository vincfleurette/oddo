<?php

declare(strict_types=1);

namespace App\DTO;

class PositionDTO
{
    public string $isinCode;
    public string $libInstrument;
    public float $valorisationAchatNette;
    public float $valeurMarcheDeviseSecurite;
    public string $dateArrete; // Format ISO8601 pour iOS
    public float $quantityMinute;
    public float $pmvl;
    public float $pmvr;
    public float $weightMinute;
    public string $reportingAssetClassCode;
    public float $performance;
    public string $classActif;
    public float $closingPriceInListingCurrency;

    public function __construct(array $d)
    {
        $this->isinCode = $d["isinCode"] ?? "";
        $this->libInstrument = $d["libInstrument"] ?? "";
        $this->valorisationAchatNette = floatval(
            $d["valorisationAchatNette"] ?? 0
        );
        $this->valeurMarcheDeviseSecurite = floatval(
            $d["valeurMarcheDeviseSecurite"] ?? 0
        );

        // Convertir la date au format ISO8601 attendu par iOS
        $this->dateArrete = $this->formatDateForIOS($d["dateArrete"] ?? "");

        $this->quantityMinute = floatval($d["quantityMinute"] ?? 0);
        $this->pmvl = floatval($d["pmvl"] ?? 0);
        $this->pmvr = floatval($d["pmvr"] ?? 0);
        $this->weightMinute = floatval($d["weightMinute"] ?? 0);
        $this->reportingAssetClassCode = $d["reportingAssetClassCode"] ?? "";
        $this->performance = floatval($d["perf"] ?? 0);
        $this->classActif = $d["classActif"] ?? "";
        $this->closingPriceInListingCurrency = floatval(
            $d["closingPriceInListingCurrency"] ?? 0
        );
    }

    public function toArray(): array
    {
        return [
            "isinCode" => $this->isinCode,
            "libInstrument" => $this->libInstrument,
            "valorisationAchatNette" => $this->valorisationAchatNette,
            "valeurMarcheDeviseSecurite" => $this->valeurMarcheDeviseSecurite,
            "dateArrete" => $this->dateArrete, // Format ISO8601
            "quantityMinute" => $this->quantityMinute,
            "pmvl" => $this->pmvl,
            "pmvr" => $this->pmvr,
            "weightMinute" => $this->weightMinute,
            "reportingAssetClassCode" => $this->reportingAssetClassCode,
            "performance" => $this->performance, // iOS s'attend à "performance"
            "classActif" => $this->classActif,
            "closingPriceInListingCurrency" =>
                $this->closingPriceInListingCurrency,
        ];
    }

    /**
     * Convertit une date au format ISO8601 attendu par iOS (yyyy-MM-dd'T'HH:mm:ss)
     */
    private function formatDateForIOS(string $dateInput): string
    {
        if (empty($dateInput)) {
            return (new \DateTime())->format("Y-m-d\TH:i:s");
        }

        try {
            // Si c'est déjà une date, la convertir
            if ($dateInput instanceof \DateTime) {
                return $dateInput->format("Y-m-d\TH:i:s");
            }

            // Si c'est une string, essayer de la parser
            $date = new \DateTime($dateInput);
            return $date->format("Y-m-d\TH:i:s");
        } catch (\Exception $e) {
            // En cas d'erreur, retourner la date actuelle
            return (new \DateTime())->format("Y-m-d\TH:i:s");
        }
    }

    /**
     * Retourne la performance formatée avec couleur
     */
    public function getFormattedPerformance(): array
    {
        return [
            "value" => $this->performance,
            "formatted" => sprintf("%+.2f%%", $this->performance),
            "isPositive" => $this->performance >= 0,
            "color" => $this->performance >= 0 ? "green" : "red",
        ];
    }

    /**
     * Retourne les plus/moins values formatées
     */
    public function getFormattedPMVL(): array
    {
        return [
            "value" => $this->pmvl,
            "formatted" => sprintf("%+.2f €", $this->pmvl),
            "isPositive" => $this->pmvl >= 0,
            "color" => $this->pmvl >= 0 ? "green" : "red",
        ];
    }
}
