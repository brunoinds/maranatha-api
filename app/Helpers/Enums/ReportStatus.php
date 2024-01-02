<?php

namespace App\Helpers\Enums;


enum ReportStatus: string
{
    case Approved = 'Approved';
    case Rejected = 'Rejected';
    case Submitted = 'Submitted';
    case Draft = 'Draft';
    case Restituted = 'Restituted';
}