<?php

namespace App\Services;

use App\Models\PaymentDefinition;
use App\Models\Sharaf;
use App\Models\SharafPayment;

class SharafPaymentService
{
    /**
     * Get or create a payment definition for a sharaf definition.
     *
     * @param int $sharafDefinitionId
     * @param string $name
     * @return PaymentDefinition
     */
    protected function getOrCreatePaymentDefinition(int $sharafDefinitionId, string $name): PaymentDefinition
    {
        return PaymentDefinition::firstOrCreate(
            [
                'sharaf_definition_id' => $sharafDefinitionId,
                'name' => $name,
            ],
            [
                'description' => ucfirst($name) . ' payment',
            ]
        );
    }

    /**
     * Toggle a payment status for a sharaf by payment name.
     *
     * @param int $sharafId
     * @param string $paymentName
     * @param bool $paid
     * @return void
     */
    protected function togglePayment(int $sharafId, string $paymentName, bool $paid): void
    {
        $sharaf = Sharaf::findOrFail($sharafId);
        $paymentDefinition = $this->getOrCreatePaymentDefinition($sharaf->sharaf_definition_id, $paymentName);

        SharafPayment::updateOrCreate(
            [
                'sharaf_id' => $sharafId,
                'payment_definition_id' => $paymentDefinition->id,
            ],
            [
                'payment_status' => $paid ? 1 : 0,
            ]
        );
    }

    /**
     * Toggle the lagat payment status for a sharaf.
     *
     * @param int $sharafId
     * @param bool $paid
     * @return void
     */
    public function toggleLagat(int $sharafId, bool $paid): void
    {
        $this->togglePayment($sharafId, 'lagat', $paid);
    }

    /**
     * Toggle the najwa ada payment status for a sharaf.
     *
     * @param int $sharafId
     * @param bool $paid
     * @return void
     */
    public function toggleNajwaAda(int $sharafId, bool $paid): void
    {
        $this->togglePayment($sharafId, 'najwa_ada', $paid);
    }

    /**
     * Toggle payment status for a sharaf by payment_definition_id.
     * Find or create the sharaf_payments record and update payment_status.
     *
     * @param int $sharafId
     * @param int $paymentDefinitionId
     * @param bool $paid
     * @return SharafPayment
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function togglePaymentByDefinitionId(int $sharafId, int $paymentDefinitionId, bool $paid): SharafPayment
    {
        $sharaf = Sharaf::findOrFail($sharafId);

        $paymentDefinition = PaymentDefinition::where('id', $paymentDefinitionId)
            ->where('sharaf_definition_id', $sharaf->sharaf_definition_id)
            ->firstOrFail();

        return SharafPayment::updateOrCreate(
            [
                'sharaf_id' => $sharafId,
                'payment_definition_id' => $paymentDefinitionId,
            ],
            [
                'payment_status' => $paid ? 1 : 0,
            ]
        );
    }
}
