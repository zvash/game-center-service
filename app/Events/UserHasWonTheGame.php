<?php

namespace App\Events;

use App\Services\AuthService;
use App\Services\BillingService;

class UserHasWonTheGame extends Event
{

    /**
     * @var int $userId
     */
    public $userId;

    /**
     * @var AuthService $authService
     */
    public $authService;

    /**
     * @var BillingService $billingService
     */
    public $billingService;

    /**
     * Create a new event instance.
     *
     * @param int $userId
     * @param AuthService $authService
     * @param BillingService $billingService
     */
    public function __construct(int $userId, AuthService $authService, BillingService $billingService)
    {
        $this->userId = $userId;
        $this->authService = $authService;
        $this->billingService = $billingService;
    }
}
