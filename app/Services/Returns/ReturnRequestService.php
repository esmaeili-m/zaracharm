<?php

namespace App\Services\Returns;

use App\Models\Order;
use App\Models\ReturnRequest;
use App\Models\RowSection;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use App\Support\Sections\ReturnPolicy;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * منطق مرجوعی کالا (مشترک بین «سفارش‌های من» کاربر و داشبورد مدیریت)
 * مهلت مرجوعی از تنظیمات سکشن «شرایط مرجوعی» صفحه‌ساز خوانده می‌شود.
 */
class ReturnRequestService
{
    // انتقال‌های مجاز وضعیت توسط مدیر
    public const TRANSITIONS = [
        'pending' => ['approved', 'rejected'],
        'approved' => ['received', 'rejected'],
        'received' => ['refunded', 'rejected'],
        'refunded' => [],
        'rejected' => [],
        'cancelled' => [],
    ];

    // در طول یک درخواست فقط یک‌بار از دیتابیس خوانده می‌شود
    protected static ?int $returnDays = null;

    /**
     * مهلت مرجوعی (روز) از سکشن فعال «شرایط مرجوعی»؛ در نبود آن مقدار پیش‌فرض
     */
    public function returnDays(): int
    {
        if (static::$returnDays !== null) {
            return static::$returnDays;
        }

        $data = RowSection::query()
            ->where('status', true)
            ->whereHas('section', fn ($q) => $q->where('key', ReturnPolicy::KEY))
            ->latest('updated_at')
            ->value('data');

        if (is_string($data)) {
            $data = json_decode($data, true);
        }

        return static::$returnDays = (int) ($data['return_days'] ?? ReturnPolicy::defaults()['return_days']);
    }

    /**
     * زمان تحویل سفارش: تاریخ تحویل مرسوله، یا برای سفارش تکمیل‌شده بدون مرسوله، آخرین بروزرسانی سفارش
     */
    public function deliveredAt(Order $order): ?Carbon
    {
        if ($order->shipment?->status === 'delivered' && $order->shipment->delivered_at) {
            return $order->shipment->delivered_at;
        }

        return $order->status === 'completed' ? $order->updated_at : null;
    }

    public function deadline(Order $order): ?Carbon
    {
        $deliveredAt = $this->deliveredAt($order);

        return $deliveredAt ? $deliveredAt->copy()->addDays($this->returnDays())->endOfDay() : null;
    }

    /**
     * تعداد قابل مرجوع هر قلم سفارش (تعداد خریداری‌شده - تعداد درخواست‌های باز/انجام‌شده)
     *
     * @return Collection<int, int> [order_item_id => quantity]
     */
    public function returnableQuantities(Order $order): Collection
    {
        $returned = DB::table('return_request_items')
            ->join('return_requests', 'return_requests.id', '=', 'return_request_items.return_request_id')
            ->where('return_requests.order_id', $order->id)
            ->whereNull('return_requests.deleted_at')
            ->whereIn('return_requests.status', ReturnRequest::OPEN_STATUSES)
            ->groupBy('return_request_items.order_item_id')
            ->selectRaw('return_request_items.order_item_id, SUM(return_request_items.quantity) as qty')
            ->pluck('qty', 'order_item_id');

        return $order->items->mapWithKeys(fn ($item) => [
            $item->id => max(0, (int) $item->quantity - (int) ($returned[$item->id] ?? 0)),
        ]);
    }

    /**
     * آیا کاربر می‌تواند برای این سفارش درخواست مرجوعی ثبت کند؟
     *
     * @return array{allowed: bool, message: ?string, deadline: ?Carbon}
     */
    public function eligibility(Order $order): array
    {
        $deliveredAt = $this->deliveredAt($order);
        $deadline = $this->deadline($order);

        $deny = fn (string $message) => ['allowed' => false, 'message' => $message, 'deadline' => $deadline];

        if (! $deliveredAt) {
            return $deny('امکان ثبت مرجوعی فقط برای سفارش‌های تحویل‌شده وجود دارد.');
        }

        if ($this->returnDays() <= 0) {
            return $deny('در حال حاضر امکان ثبت مرجوعی وجود ندارد.');
        }

        if (now()->greaterThan($deadline)) {
            return $deny('مهلت ' . $this->returnDays() . ' روزه مرجوعی این سفارش به پایان رسیده است.');
        }

        if ($this->returnableQuantities($order)->sum() <= 0) {
            return $deny('برای تمام اقلام این سفارش قبلاً درخواست مرجوعی ثبت شده است.');
        }

        return ['allowed' => true, 'message' => null, 'deadline' => $deadline];
    }

    /**
     * ثبت درخواست مرجوعی توسط کاربر
     *
     * @param  array<int, int>  $quantities  [order_item_id => quantity]
     */
    public function create(User $user, int $orderId, array $quantities, string $reason, ?string $description): ReturnRequest
    {
        return DB::transaction(function () use ($user, $orderId, $quantities, $reason, $description) {

            // قفل سفارش تا دو درخواست هم‌زمان از سقف تعداد عبور نکنند
            $order = Order::query()
                ->where('user_id', $user->id)
                ->with(['items', 'shipment'])
                ->lockForUpdate()
                ->findOrFail($orderId);

            $eligibility = $this->eligibility($order);

            if (! $eligibility['allowed']) {
                throw ValidationException::withMessages(['returnItems' => $eligibility['message']]);
            }

            $returnable = $this->returnableQuantities($order);
            $items = $order->items->keyBy('id');
            $rows = [];

            foreach ($quantities as $itemId => $qty) {
                $qty = (int) $qty;

                if ($qty <= 0) {
                    continue;
                }

                $item = $items->get((int) $itemId);

                if (! $item) {
                    throw ValidationException::withMessages(['returnItems' => 'قلم انتخاب‌شده متعلق به این سفارش نیست.']);
                }

                if ($qty > ($returnable[$item->id] ?? 0)) {
                    throw ValidationException::withMessages([
                        'returnItems' => 'تعداد قابل مرجوع «' . $item->product_name . '» حداکثر ' . ($returnable[$item->id] ?? 0) . ' عدد است.',
                    ]);
                }

                $rows[] = [
                    'order_item_id' => $item->id,
                    'quantity' => $qty,
                    'amount' => (int) $item->price * $qty,
                ];
            }

            if (empty($rows)) {
                throw ValidationException::withMessages(['returnItems' => 'حداقل یک کالا را برای مرجوعی انتخاب کنید.']);
            }

            $request = ReturnRequest::create([
                'return_number' => $this->generateNumber(),
                'order_id' => $order->id,
                'user_id' => $user->id,
                'status' => 'pending',
                'reason' => $reason,
                'description' => $description,
                'refund_amount' => array_sum(array_column($rows, 'amount')),
            ]);

            $request->items()->createMany($rows);

            return $request;
        });
    }

    /**
     * لغو درخواست توسط کاربر (فقط در وضعیت در انتظار بررسی)
     */
    public function cancel(User $user, int $returnRequestId): void
    {
        DB::transaction(function () use ($user, $returnRequestId) {
            $request = ReturnRequest::where('user_id', $user->id)
                ->lockForUpdate()
                ->findOrFail($returnRequestId);

            if (! $request->isPending()) {
                throw ValidationException::withMessages(['returnItems' => 'فقط درخواست‌های در انتظار بررسی قابل لغو هستند.']);
            }

            $request->update(['status' => 'cancelled']);
        });
    }

    /**
     * سقف مبلغ قابل استرداد: مبلغ کل سفارش منهای استردادهای قبلی همان سفارش
     */
    public function maxRefundable(ReturnRequest $request): int
    {
        $alreadyRefunded = ReturnRequest::where('order_id', $request->order_id)
            ->where('status', 'refunded')
            ->where('id', '!=', $request->id)
            ->sum('refund_amount');

        return max(0, (int) $request->order->total_amount - (int) $alreadyRefunded);
    }

    /**
     * تغییر وضعیت توسط مدیر. وضعیت refunded مبلغ را به کیف پول کاربر واریز می‌کند.
     */
    public function changeStatus(int $returnRequestId, string $status, ?string $adminNote, ?int $refundAmount = null): ReturnRequest
    {
        return DB::transaction(function () use ($returnRequestId, $status, $adminNote, $refundAmount) {

            $request = ReturnRequest::with('order')->lockForUpdate()->findOrFail($returnRequestId);

            if (! in_array($status, self::TRANSITIONS[$request->status] ?? [], true)) {
                throw ValidationException::withMessages([
                    'newStatus' => 'تغییر وضعیت از «' . $request->status_label . '» به «' . (ReturnRequest::STATUSES[$status] ?? $status) . '» مجاز نیست.',
                ]);
            }

            $attributes = [
                'status' => $status,
                'admin_note' => $adminNote,
            ];

            if ($status === 'refunded') {
                $amount = (int) ($refundAmount ?? $request->refund_amount);
                $max = $this->maxRefundable($request);

                if ($amount <= 0 || $amount > $max) {
                    throw ValidationException::withMessages([
                        'refundAmount' => 'مبلغ استرداد باید بین ۱ تا ' . number_format($max) . ' تومان باشد.',
                    ]);
                }

                $this->creditWallet($request, $amount);

                $attributes['refund_amount'] = $amount;
                $attributes['refunded_at'] = now();
            }

            $request->update($attributes);

            // کالای مرجوعی دریافت‌شده به موجودی انبار برمی‌گردد (فقط اختلاف اعمال می‌شود)
            if (in_array($status, ['received', 'refunded'], true)) {
                app(\App\Services\Invoices\InvoiceStockService::class)->syncOrder($request->order_id);
            }

            return $request;
        });
    }

    protected function creditWallet(ReturnRequest $request, int $amount): void
    {
        // جلوگیری از واریز تکراری
        if ($request->transactions()->where('category', 'refund')->exists()) {
            throw ValidationException::withMessages(['refundAmount' => 'مبلغ این درخواست قبلاً بازگردانده شده است.']);
        }

        $wallet = Wallet::where('user_id', $request->user_id)->lockForUpdate()->first()
            ?? Wallet::create(['user_id' => $request->user_id, 'balance' => 0]);

        $wallet->increment('balance', $amount);

        Transaction::create([
            'user_id' => $request->user_id,
            'type' => 'credit',
            'category' => 'refund',
            'amount' => $amount,
            'balance_after' => $wallet->fresh()->balance,
            'status' => 'completed',
            'description' => 'استرداد وجه مرجوعی ' . $request->return_number . ' - سفارش ' . $request->order->order_number,
            'order_id' => $request->order_id,
            'reference_type' => ReturnRequest::class,
            'reference_id' => $request->id,
        ]);
    }

    protected function generateNumber(): string
    {
        do {
            $number = 'RT-' . now()->format('ymd') . '-' . Str::upper(Str::random(5));
        } while (ReturnRequest::withTrashed()->where('return_number', $number)->exists());

        return $number;
    }
}
