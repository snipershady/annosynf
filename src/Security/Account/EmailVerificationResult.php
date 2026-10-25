<?php

declare(strict_types=1);

namespace App\Security\Account;

enum EmailVerificationResult
{
    case Verified;
    case AlreadyVerified;
    case Expired;
    case Invalid;
}
