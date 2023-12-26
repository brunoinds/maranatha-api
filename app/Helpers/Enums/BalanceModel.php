<?php

namespace App\Helpers\Enums;


enum BalanceModel: string
{
    case Direct = 'Direct';
    case Restitution = 'Restitution';
    case Expense = 'Expense';
}