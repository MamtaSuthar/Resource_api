<?php

namespace App\Crons;

use App\Vars\GHSCurrencyConversion;

// update currency conversion rate from 1 usd to ghs every six hours
class UpdateCurrency
{
    public function __invoke()
    {
        GHSCurrencyConversion::sync('GHS');
    }
}