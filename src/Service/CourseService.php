<?php

namespace App\Service;

class CourseService
{
    private BillingClient $billingClient;
    public function __construct(BillingClient $billingClient) {
        $this->billingClient = $billingClient;
    }

    public function isCoursePaid(string $apiToken, array $billingCourse): bool
    {
        if ($billingCourse['type'] === 'free') {
            return true;
        }
        $transaction = $this->billingClient->getTransactions(
            $apiToken,
            'payment',
            $billingCourse['code'],
            true
        );
        return count($transaction) > 0;
    }

}
