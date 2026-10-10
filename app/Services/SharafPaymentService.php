<?php

namespace App\Services;

use App\Models\PaymentDefinition;
use App\Models\Sharaf;
use App\Models\SharafPayment;
use Illuminate\Support\Facades\Storage;

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
                'user_type' => 'Finance',
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
     * When marking paid, paid_amount/paid_currency record what was actually received
     * (may differ from the agreed payment_amount/payment_currency).
     * payment_method is cash, transfer (DEH Receipt), or other.
     * Other requires payment_method_detail. Cash and transfer clear it.
     * A transfer may keep a stored receipt. Cash and other clear it.
     *
     * @param int $sharafId
     * @param int $paymentDefinitionId
     * @param bool $paid
     * @param float|string|null $paidAmount
     * @param string|null $paidCurrency
     * @param string|null $paymentMethod
     * @param string|null $paymentCity
     * @param string|null $paymentMethodDetail
     * @param string|null $receiptPath
     * @param bool $updateReceipt
     * @return SharafPayment
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function togglePaymentByDefinitionId(
        int $sharafId,
        int $paymentDefinitionId,
        bool $paid,
        $paidAmount = null,
        ?string $paidCurrency = null,
        ?string $paymentMethod = null,
        ?string $paymentCity = null,
        ?string $paymentMethodDetail = null,
        ?string $receiptPath = null,
        bool $updateReceipt = false
    ): SharafPayment {
        $sharaf = Sharaf::findOrFail($sharafId);

        PaymentDefinition::where('id', $paymentDefinitionId)
            ->where('sharaf_definition_id', $sharaf->sharaf_definition_id)
            ->firstOrFail();

        $payment = SharafPayment::firstOrNew([
            'sharaf_id' => $sharafId,
            'payment_definition_id' => $paymentDefinitionId,
        ]);

        if ($payment->payment_amount === null) {
            $payment->payment_amount = 0;
        }
        if (!$payment->payment_currency) {
            $payment->payment_currency = 'LKR';
        }

        $payment->payment_status = $paid ? 1 : 0;
        $previousReceipt = $payment->receipt_path;

        if ($paid) {
            $amount = $paidAmount ?? $payment->paid_amount ?? $payment->payment_amount ?? 0;
            $currency = $paidCurrency ?? $payment->paid_currency ?? $payment->payment_currency ?? 'LKR';
            $payment->paid_amount = $amount;
            $payment->paid_currency = strtoupper(substr((string) $currency, 0, 3));
            if ($paymentMethod !== null) {
                $payment->payment_method = $paymentMethod;
                $payment->payment_method_detail = $paymentMethod === 'other'
                    ? ($paymentMethodDetail !== null && $paymentMethodDetail !== '' ? $paymentMethodDetail : null)
                    : null;
            }
            if ($paymentCity !== null) {
                $payment->payment_city = $paymentCity;
            }
            $clearsReceipt = in_array($paymentMethod, ['cash', 'other'], true);
            if ($updateReceipt || $clearsReceipt) {
                $payment->receipt_path = $clearsReceipt ? null : $receiptPath;
            }
        } else {
            $payment->payment_method = null;
            $payment->payment_method_detail = null;
            $payment->payment_city = null;
            $payment->receipt_path = null;
        }

        $payment->save();

        if ($previousReceipt && $previousReceipt !== $payment->receipt_path) {
            Storage::disk('local')->delete($previousReceipt);
        }

        return $payment;
    }
}
