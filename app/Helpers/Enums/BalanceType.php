<?php

namespace App\Helpers\Enums;


enum BalanceType: string
{
    case Credit = 'Credit';
    case Debit = 'Debit';
}