<?php

namespace App\Enums;

enum DocumentType: string
{
    case MasterDirection = 'master_direction';
    case Circular = 'circular';
    case Notification = 'notification';
    case PressRelease = 'press_release';
    case Faq = 'faq';
    case Other = 'other';
}
