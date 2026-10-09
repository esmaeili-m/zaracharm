<?php

namespace App\Services\Accounting;

use App\Models\AccountingAccount;
use App\Models\AccountingEntry;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;

/**
 * ثبت خودکار ورود و خروج پول در دفتر حسابداری (idempotent)
 *
 *  - پرداخت موفق سفارش سایت (کارت‌به‌کارت / درگاه / در محل)  => دریافت در حساب مربوط
 *    پرداخت از کیف پول ثبت نمی‌شود؛ پولی وارد حساب‌ها نشده (موجودی کیف پول قبلاً از مرجوعی آمده)
 *  - بازگشت وجه پرداخت (PaymentService::refund)            => پرداخت از همان حساب
 *  - فاکتور حضوری/دستی پرداخت‌شده (بدون سفارش)              => دریافت در حساب پیش‌فرض
 *  - فاکتور حضوری بازگشت وجه‌شده                            => پرداخت از حساب پیش‌فرض
 *
 * هر منبع فقط یک‌بار ثبت می‌شود (unique: source + reference) — حتی اگر ردیف حذف شده باشد،
 * تا حذف دستی یک ردیف خودکار با همگام‌سازی بعدی برنگردد.
 * کد پرداخت و سفارش تغییری نمی‌کند؛ همگام‌سازی هنگام باز شدن صفحات حسابداری اجرا می‌شود.
 */
class AccountingSync
{
    protected ?AccountingAccount $default = null;
    protected array $cardAccounts = [];
    protected array $gatewayAccounts = [];

    /**
     * @return array{created: int, skipped: int}
     */
    public function run(): array
    {
        $this->default = AccountingAccount::default();

        if (! $this->default) {
            return ['created' => 0, 'skipped' => 0];
        }

        $accounts = AccountingAccount::active()->get(['id', 'bank_card_id', 'payment_gateway_id']);
        $this->cardAccounts = $accounts->whereNotNull('bank_card_id')->pluck('id', 'bank_card_id')->all();
        $this->gatewayAccounts = $accounts->whereNotNull('payment_gateway_id')->pluck('id', 'payment_gateway_id')->all();

        $created = 0;
        $skipped = 0;

        $count = function (bool $ok) use (&$created, &$skipped) {
            $ok ? $created++ : $skipped++;
        };

        // پرداخت‌های موفق (یا موفقی که بعداً بازگشت داده شده‌اند)
        $this->pending(Payment::query()->whereIn('status', ['paid', 'refunded'])->where('method', '!=', 'wallet'), 'payment')
            ->with('order:id,order_number')
            ->chunkById(200, function ($payments) use ($count) {
                foreach ($payments as $payment) {
                    $count($this->post('income', 'payment', $payment, $this->accountForPayment($payment), (int) $payment->amount,
                        $payment->paid_at ?? $payment->created_at,
                        'دریافت سفارش ' . ($payment->order?->order_number ?? '#' . $payment->order_id)));
                }
            });

        $this->pending(Payment::query()->where('status', 'refunded')->where('method', '!=', 'wallet'), 'payment_refund')
            ->with('order:id,order_number')
            ->chunkById(200, function ($payments) use ($count) {
                foreach ($payments as $payment) {
                    $count($this->post('expense', 'payment_refund', $payment, $this->accountForPayment($payment), (int) $payment->amount,
                        $payment->reviewed_at ?? $payment->updated_at,
                        'بازگشت وجه سفارش ' . ($payment->order?->order_number ?? '#' . $payment->order_id)));
                }
            });

        // فاکتورهای حضوری / دستی (فاکتورهای سفارش سایت از طریق پرداخت ثبت شده‌اند)
        $this->pending(Invoice::query()->whereNull('order_id')->whereIn('status', ['paid', 'refunded']), 'invoice')
            ->chunkById(200, function ($invoices) use ($count) {
                foreach ($invoices as $invoice) {
                    $count($this->post('income', 'invoice', $invoice, $this->default->id, (int) $invoice->total_amount,
                        $invoice->paid_at ?? $invoice->created_at,
                        'فروش فاکتور ' . $invoice->invoice_number));
                }
            });

        $this->pending(Invoice::query()->whereNull('order_id')->where('status', 'refunded'), 'invoice_refund')
            ->chunkById(200, function ($invoices) use ($count) {
                foreach ($invoices as $invoice) {
                    $count($this->post('expense', 'invoice_refund', $invoice, $this->default->id, (int) $invoice->total_amount,
                        $invoice->updated_at,
                        'بازگشت وجه فاکتور ' . $invoice->invoice_number));
                }
            });

        return ['created' => $created, 'skipped' => $skipped];
    }

    /**
     * فقط منابعی که هنوز ردیف (حتی حذف‌شده) ندارند
     */
    protected function pending(Builder $query, string $source): Builder
    {
        $model = $query->getModel();

        return $query->whereNotExists(fn ($q) => $q->selectRaw('1')
            ->from('accounting_entries')
            ->where('accounting_entries.source', $source)
            ->where('accounting_entries.reference_type', $model->getMorphClass())
            ->whereColumn('accounting_entries.reference_id', $model->getTable() . '.id'));
    }

    protected function accountForPayment(Payment $payment): int
    {
        return match (true) {
            $payment->method === 'transfer' && isset($this->cardAccounts[$payment->bank_card_id]) => $this->cardAccounts[$payment->bank_card_id],
            $payment->method === 'gateway' && isset($this->gatewayAccounts[$payment->gateway_id]) => $this->gatewayAccounts[$payment->gateway_id],
            default => $this->default->id,
        };
    }

    protected function post(string $type, string $source, $reference, int $accountId, int $amount, $date, string $title): bool
    {
        if ($amount <= 0) {
            return false;
        }

        try {
            AccountingEntry::create([
                'type' => $type,
                'source' => $source,
                'account_id' => $accountId,
                'amount' => $amount,
                'entry_date' => Carbon::parse($date ?? now())->toDateString(),
                'title' => $title,
                'reference_type' => $reference->getMorphClass(),
                'reference_id' => $reference->getKey(),
            ]);

            return true;
        } catch (QueryException $e) {
            // همگام‌سازی هم‌زمان: ردیف توسط درخواست دیگری ثبت شده (unique)
            return false;
        }
    }
}
