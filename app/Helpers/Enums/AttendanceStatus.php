<?php

namespace App\Helpers\Enums;


enum AttendanceStatus: string
{
    case Present = 'Present';
    case Absent = 'Absent';
    case Justified = 'Justified';
}