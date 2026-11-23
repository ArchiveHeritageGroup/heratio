<?php

namespace AhgHeritageManage\Jurisdiction;

/** South African market module: GRAP 103 wording on the shared calculation. */
class Grap103RollForwardProfile extends GenericRollForwardProfile
{
    public function standardCode(): string
    {
        return 'GRAP103';
    }

    public function title(): string
    {
        return 'Reconciliation of the carrying amount of heritage assets (GRAP 103)';
    }

    public function lineLabels(): array
    {
        return array_merge(parent::lineLabels(), [
            'opening' => 'Carrying amount at beginning of the reporting period',
            'revaluation_increase' => 'Increases from revaluation',
            'revaluation_decrease' => 'Decreases from revaluation',
            'impairment' => 'Impairment losses recognised',
            'reversal' => 'Impairment losses reversed',
            'disposals' => 'Derecognition and disposals',
            'closing' => 'Carrying amount at end of the reporting period',
        ]);
    }
}
