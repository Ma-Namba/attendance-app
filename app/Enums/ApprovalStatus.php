<?php

namespace App\Enums;

// 🌟「class」ではなく「enum」とし、文字列型「: string」を指定
enum ApprovalStatus: string
{
    case PENDING = '承認待ち';
    case APPROVED = '承認済み';
}
