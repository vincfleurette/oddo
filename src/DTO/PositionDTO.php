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
        ];
    }
}
