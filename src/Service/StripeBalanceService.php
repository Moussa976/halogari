<?php

namespace App\Service;

use Stripe\Balance;
use Stripe\Exception\ApiErrorException;
use Stripe\Stripe;

class StripeBalanceService
{
    private StripeConfigService $stripeConfig;

    public function __construct(StripeConfigService $stripeConfig)
    {
        $this->stripeConfig = $stripeConfig;
    }

    public function platformBalance(): array
    {
        $secretKey = $this->stripeConfig->secretKey();
        $mode = str_starts_with($secretKey, 'sk_live_') ? 'Live' : 'Test';

        if ($secretKey === '') {
            return [
                'configured' => false,
                'available' => 0.0,
                'pending' => 0.0,
                'mode' => 'Non configuré',
                'error' => null,
            ];
        }

        try {
            Stripe::setApiKey($secretKey);
            $balance = Balance::retrieve();

            return [
                'configured' => true,
                'available' => $this->sumCurrency($balance->available ?? [], 'eur'),
                'pending' => $this->sumCurrency($balance->pending ?? [], 'eur'),
                'mode' => $mode,
                'error' => null,
            ];
        } catch (ApiErrorException $exception) {
            return [
                'configured' => true,
                'available' => 0.0,
                'pending' => 0.0,
                'mode' => $mode,
                'error' => $exception->getMessage(),
            ];
        } catch (\Throwable $exception) {
            return [
                'configured' => true,
                'available' => 0.0,
                'pending' => 0.0,
                'mode' => $mode,
                'error' => 'Solde indisponible pour le moment.',
            ];
        }
    }

    private function sumCurrency(iterable $amounts, string $currency): float
    {
        $total = 0;

        foreach ($amounts as $amount) {
            $amountCurrency = strtolower((string) ($amount->currency ?? ''));
            if ($amountCurrency === strtolower($currency)) {
                $total += (int) ($amount->amount ?? 0);
            }
        }

        return round($total / 100, 2);
    }
}
