<?php

namespace Gabrieldev\Netopia;

/**
 * Statusurile unei plăți în NETOPIA Payments API v2.
 */
enum PaymentStatus: int
{
    case New = 1;
    case Opened = 2;
    case Paid = 3;
    case Canceled = 4;
    case Confirmed = 5;
    case Pending = 6;
    case Scheduled = 7;
    case Credit = 8;
    case ChargebackInit = 9;
    case ChargebackAccept = 10;
    case Error = 11;
    case Declined = 12;
    case Fraud = 13;
    case PendingAuthorization = 14;
    case ThreeDSecureAuthorization = 15;
    case ChargebackRepresentment = 16;
    case Reversed = 17;
    case PendingAny = 18;
    case ProgrammedRecurring = 19;
    case CanceledRecurring = 20;
    case TrialPending = 21;
    case Trial = 22;
    case Expired = 23;

    /** Banii sunt încasați (plată autorizată sau confirmată). */
    public function isSuccessful(): bool
    {
        return in_array($this, [self::Paid, self::Confirmed], true);
    }

    /** Plata a eșuat definitiv. */
    public function isFailed(): bool
    {
        return in_array($this, [self::Canceled, self::Error, self::Declined, self::Fraud, self::Expired, self::Reversed], true);
    }

    public function isPending(): bool
    {
        return ! $this->isSuccessful() && ! $this->isFailed();
    }
}
