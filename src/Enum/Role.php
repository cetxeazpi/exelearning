<?php

namespace App\Enum;

enum Role: string
{
    case OWNER = 'OWNER';
    case COLLABORATOR = 'COLLABORATOR';
    case VIEWER = 'VIEWER';
}