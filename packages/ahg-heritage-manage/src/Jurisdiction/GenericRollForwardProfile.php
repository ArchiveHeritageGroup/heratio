<?php

namespace AhgHeritageManage\Jurisdiction;

use AhgHeritageManage\Contracts\RollForwardProfile;

class GenericRollForwardProfile implements RollForwardProfile
{
    public function standardCode(): string
    {
        return '*';
    }

    public function title(): string
    {
        return 'Reconciliation of carrying amount';
    }

    public function lineLabels(): array
    {
        return [
            'opening' => 'Carrying amount at start of period',
            'additions' => 'Additions',
            'revaluation_increase' => 'Revaluation increases',
            'revaluation_decrease' => 'Revaluation decreases',
            'impairment' => 'Impairment losses',
            'reversal' => 'Reversals of impairment',
            'disposals' => 'Disposals and derecognition',
            'closing' => 'Carrying amount at end of period',
        ];
    }
}
