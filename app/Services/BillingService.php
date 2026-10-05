<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Patient;
use App\Models\Payment;
use Illuminate\Support\Collection;

/**
 * Single source of truth for how booked tests, bills (payments) and the
 * patient's running totals relate to each other.
 *
 * Every appointment (one booked test) belongs to one payment (one bill).
 * Bill totals and patient totals are always derived from the appointments.
 */
class BillingService
{
    public const PAYMENT_METHODS = ['Cash', 'Card', 'UPI', 'Net Banking', 'Bank Transfer', 'Online', 'Other'];

    /**
     * Normalise the parallel test_name[] / test_price[] / test_discount[] arrays
     * posted by the forms into a list of rows, skipping blank test names.
     *
     * @return array<int, array{appointment_id: ?int, name: string, price: float, discount: float}>
     */
    public static function rowsFromRequest(array $names, array $prices, array $discounts, array $appointmentIds = []): array
    {
        $rows = [];
        foreach ($names as $index => $name) {
            $name = trim((string) $name);
            if ($name === '') {
                continue;
            }

            $price = max(0, round((float) ($prices[$index] ?? 0), 2));
            $discount = min($price, max(0, round((float) ($discounts[$index] ?? 0), 2)));

            $rows[] = [
                'appointment_id' => !empty($appointmentIds[$index]) ? (int) $appointmentIds[$index] : null,
                'name' => mb_substr($name, 0, 255),
                'price' => $price,
                'discount' => $discount,
            ];
        }

        return $rows;
    }

    /**
     * Create a new bill for the given tests and book each test as an appointment.
     */
    public function createBill(Patient $patient, array $rows, float $advance, ?string $method, string $remarks, ?string $doctorName = null, ?string $date = null): Payment
    {
        $payment = Payment::create([
            'patient_id' => $patient->id,
            'total_amount' => 0,
            'discount' => 0,
            'advance_paid' => 0,
            'net_amount' => 0,
            'balance_due' => 0,
            'payment_status' => 'Unpaid',
            'payment_method' => $method ?: 'Cash',
            'bill_date' => $date ?: now()->toDateString(),
            'remarks' => $remarks,
        ]);

        $appointments = collect();
        foreach ($rows as $row) {
            $appointments->push(Appointment::create([
                'patient_id' => $patient->id,
                'payment_id' => $payment->id,
                'doctor_name' => $doctorName ?: ($patient->reference_dr ?: 'Self'),
                'test_name' => $row['name'],
                'test_price' => $row['price'],
                'discount' => $row['discount'],
                'advance_paid' => 0,
                'balance' => $row['price'] - $row['discount'],
                'total_amount' => $row['price'],
                'appointment_date' => $date ?: now()->toDateString(),
                'appointment_time' => now()->format('H:i'),
                'status' => 'Pending',
            ]));
        }

        $this->distributeAdvance($appointments, $advance);
        $this->recalculatePayment($payment);
        $this->recalculatePatient($patient);

        return $payment->fresh();
    }

    /**
     * Spread an amount paid across appointments in order, writing each one's
     * advance and balance. Any over-payment stays on the last appointment as credit.
     */
    public function distributeAdvance(Collection $appointments, float $advance): void
    {
        $remaining = max(0, round($advance, 2));
        // Cancelled tests are not charged and receive no share of the payment.
        foreach ($appointments->where('status', 'Cancelled') as $cancelled) {
            $cancelled->forceFill(['advance_paid' => 0, 'balance' => 0])->save();
        }
        $appointments = $appointments->where('status', '!=', 'Cancelled')->values();
        $count = $appointments->count();

        foreach ($appointments->values() as $i => $appointment) {
            $net = max(0, (float) $appointment->test_price - (float) $appointment->discount);
            $share = ($i === $count - 1) ? $remaining : min($remaining, $net);
            $remaining = round($remaining - $share, 2);

            $appointment->forceFill([
                'advance_paid' => $share,
                'balance' => round($net - $share, 2),
                'total_amount' => $appointment->test_price,
            ])->save();
        }
    }

    public function recalculatePayment(Payment $payment): void
    {
        $appointments = $payment->appointments()->get();
        if ($appointments->isEmpty()) {
            return;
        }
        // Cancelled tests stay on the bill for the record but are not charged.
        $appointments = $appointments->where('status', '!=', 'Cancelled');

        $total = round($appointments->sum(fn ($a) => (float) $a->test_price), 2);
        $discount = round($appointments->sum(fn ($a) => (float) $a->discount), 2);
        $advance = round($appointments->sum(fn ($a) => (float) $a->advance_paid), 2);
        $net = round($total - $discount, 2);
        $balance = round($net - $advance, 2);

        $payment->forceFill([
            'total_amount' => $total,
            'discount' => $discount,
            'advance_paid' => $advance,
            'net_amount' => $net,
            'balance_due' => $balance,
            'payment_status' => self::status($advance, $balance, $payment->payment_status),
        ])->save();
    }

    public function recalculatePatient(Patient $patient): void
    {
        $appointments = $patient->appointments()->where('status', '!=', 'Cancelled')->get();

        $total = round($appointments->sum(fn ($a) => (float) $a->test_price), 2);
        $discount = round($appointments->sum(fn ($a) => (float) $a->discount), 2);
        $advance = round($appointments->sum(fn ($a) => (float) $a->advance_paid), 2);

        $patient->forceFill([
            'total_amount' => $total,
            'discount' => $discount,
            'advance_paid' => $advance,
            'balance' => round($total - $discount - $advance, 2),
        ])->save();
    }

    /**
     * Remove bills whose tests were all removed, then refresh the remaining ones.
     */
    public function syncPayments(Patient $patient, iterable $paymentIds): void
    {
        foreach (collect($paymentIds)->filter()->unique() as $paymentId) {
            $payment = Payment::find($paymentId);
            if (!$payment) {
                continue;
            }
            if ($payment->appointments()->exists()) {
                $this->recalculatePayment($payment);
            } else {
                $payment->delete();
            }
        }
        $this->recalculatePatient($patient);
    }

    public static function status(float $advance, float $balance, ?string $current = null): string
    {
        if ($current === 'Refunded') {
            return 'Refunded';
        }
        if ($balance <= 0) {
            return 'Paid';
        }

        return $advance > 0 ? 'Partial' : 'Unpaid';
    }
}
