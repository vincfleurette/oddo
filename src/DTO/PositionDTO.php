<?php

namespace App\DTO;

class PositionDTO
{
    public string $isinCode;
    public string $libInstrument;
    public float $valorisationAchatNette;
    public float $valeurMarcheDeviseSecurite;
    public string $dateArrete;
    public float $quantityMinute;
    public float $pmvl;
    public float $pmvr;
    public float $weightMinute;
    public string $reportingAssetClassCode;
    public float $performance; // Nouveau champ performance
    public string $classActif; // Nouveau champ classe d'actif
    public float $closingPriceInListingCurrency; // Prix de clôture

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
        $this->dateArrete = $d["dateArrete"] ?? "";
        $this->quantityMinute = floatval($d["quantityMinute"] ?? 0);
        $this->pmvl = floatval($d["pmvl"] ?? 0);
        $this->pmvr = floatval($d["pmvr"] ?? 0);
        $this->weightMinute = floatval($d["weightMinute"] ?? 0);
        $this->reportingAssetClassCode = $d["reportingAssetClassCode"] ?? "";

        // Nouveaux champs
        $this->performance = floatval($d["perf"] ?? 0); // Correction: "perf" au lieu de "performance"
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
            "dateArrete" => $this->dateArrete,
            "quantityMinute" => $this->quantityMinute,
            "pmvl" => $this->pmvl,
            "pmvr" => $this->pmvr,
            "weightMinute" => $this->weightMinute,
            "reportingAssetClassCode" => $this->reportingAssetClassCode,
            "performance" => $this->performance,
            "classActif" => $this->classActif,
            "closingPriceInListingCurrency" =>
                $this->closingPriceInListingCurrency,
        ];
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
