<?php

namespace App\Services\Sms;

use App\Models\SmsContact;
use App\Models\SmsContactList;
use App\Models\SmsSuppression;
use App\Models\SmsTemplate;
use App\Models\User;
use App\Support\OrderStatus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Morilog\Jalali\Jalalian;
use RuntimeException;

/**
 * ساخت فهرست نهایی گیرندگان (سمت سرور):
 * منبع مخاطب → استانداردسازی شماره → حذف نامعتبر/تکراری → فهرست مسدود و انصراف → رضایت (تبلیغاتی)
 * → محاسبه مقدار متغیرهای هر گیرنده (گیرنده با متغیر خالی ارسال نمی‌شود)
 *
 * audience: ['source' => single|manual|customers|list|import, ...پارامترهای منبع]
 * variables: [index => ['source' => کلید SOURCES, 'value' => مقدار ثابت/جایگزین, 'column' => ستون فایل]]
 */
class AudienceBuilder
{
    public const SOURCES = [
        'fixed' => 'مقدار ثابت برای همه',
        'name' => 'نام کامل گیرنده',
        'first_name' => 'نام کوچک گیرنده',
        'mobile' => 'شماره موبایل گیرنده',
        'last_order_number' => 'شماره آخرین سفارش',
        'last_order_amount' => 'مبلغ آخرین سفارش (تومان)',
        'last_order_date' => 'تاریخ آخرین سفارش',
        'last_order_status' => 'وضعیت آخرین سفارش',
        'today' => 'تاریخ امروز',
        'column' => 'ستون فایل واردشده',
    ];

    public const SKIP_REASONS = [
        'invalid' => 'شماره نامعتبر یا خالی',
        'duplicate' => 'شماره تکراری',
        'suppressed' => 'در فهرست مسدود',
        'opted_out' => 'انصراف از پیامک تبلیغاتی',
        'no_consent' => 'بدون رضایت ثبت‌شده برای پیامک تبلیغاتی',
        'missing_vars' => 'مقدار متغیر خالی یا نامعتبر',
        'limit' => 'بیش از سقف مجاز گیرندگان',
    ];

    public const IMPORT_CACHE_PREFIX = 'sms-import:';

    protected const CHUNK = 500;

    protected const MAX_ISSUES = 300;

    /**
     * @return array{ready: list<array>, skipped: array<string, int>, issues: list<array>, input: int, samples: list<array>, parts: int, estimated_cost: ?int}
     */
    public function build(SmsTemplate $template, array $audience, array $variables): array
    {
        $definitions = $template->variableDefinitions();
        $marketing = $template->category === 'marketing';
        $max = (int) config('sms.max_recipients', 20000);

        $ready = [];
        $seen = [];
        $issues = [];
        $input = 0;
        $skipped = array_fill_keys(array_keys(self::SKIP_REASONS), 0);
        $today = Jalalian::now()->format('Y/m/d');

        $skip = function (string $reason, string $display, ?string $detail = null) use (&$skipped, &$issues) {
            $skipped[$reason]++;
            if (count($issues) < self::MAX_ISSUES) {
                $issues[] = ['value' => $display, 'reason' => $reason, 'detail' => $detail];
            }
        };

        foreach ($this->chunks($audience) as $chunk) {
            // ۱) استانداردسازی و حذف نامعتبر/تکراری
            $rows = [];
            foreach ($chunk as $row) {
                $input++;
                $mobile = PhoneNumber::normalize($row['raw'] ?? null);

                if ($mobile === null) {
                    $skip('invalid', (string) ($row['raw'] ?? '') ?: '(خالی)', $row['name'] ?? null);
                    continue;
                }

                if (isset($seen[$mobile])) {
                    $skip('duplicate', $mobile, $row['name'] ?? null);
                    continue;
                }

                $seen[$mobile] = true;
                $rows[] = $row + ['mobile' => $mobile];
            }

            if (! $rows) {
                continue;
            }

            // ۲) اطلاعات تکمیلی در چند کوئری برای کل chunk
            $mobiles = array_column($rows, 'mobile');
            $suppressions = SmsSuppression::whereIn('mobile', $mobiles)->pluck('scope', 'mobile');
            $contacts = SmsContact::whereIn('mobile', $mobiles)->get(['id', 'mobile', 'name', 'user_id', 'marketing_consent'])->keyBy('mobile');

            $userIds = array_filter(array_column($rows, 'user_id'));
            $users = User::query()
                ->where(fn ($q) => $q->whereIn('id', $userIds ?: [0])->orWhereIn('mobile', $mobiles))
                ->get(['id', 'first_name', 'last_name', 'mobile']);
            $usersById = $users->keyBy('id');
            $usersByMobile = $users->keyBy('mobile');

            $resolvedUserIds = [];
            foreach ($rows as $i => $row) {
                $user = ($row['user_id'] ?? null) ? $usersById->get($row['user_id']) : $usersByMobile->get($row['mobile']);
                $rows[$i]['user'] = $user;
                $rows[$i]['contact'] = $contacts->get($row['mobile']);
                if ($user) {
                    $resolvedUserIds[] = $user->id;
                }
            }

            $consenting = $marketing && $resolvedUserIds
                ? DB::table('notification_preferences')->whereIn('user_id', $resolvedUserIds)->where('promotions', true)->pluck('user_id')->flip()
                : collect();
            $lastOrders = $this->needsOrders($variables, $definitions) && $resolvedUserIds
                ? $this->lastOrders($resolvedUserIds)
                : collect();

            // ۳) مسدودی، رضایت و متغیرها
            foreach ($rows as $row) {
                $user = $row['user'];
                $contact = $row['contact'];
                $name = ($row['name'] ?? null) ?: ($contact?->name ?: ($user ? trim($user->first_name . ' ' . $user->last_name) : null));
                $name = $name !== '' ? $name : null;
                $display = $row['mobile'] . ($name ? ' — ' . $name : '');

                $scope = $suppressions->get($row['mobile']);
                if ($scope === 'all') {
                    $skip('suppressed', $display);
                    continue;
                }

                if ($marketing) {
                    if ($scope === 'marketing') {
                        $skip('opted_out', $display);
                        continue;
                    }

                    $consent = ($contact && $contact->marketing_consent) || ($user && $consenting->has($user->id));
                    if (! $consent) {
                        $skip('no_consent', $display);
                        continue;
                    }
                }

                $context = [
                    'name' => $name,
                    'first_name' => $user?->first_name ?: ($name ? explode(' ', $name)[0] : null),
                    'mobile' => $row['mobile'],
                    'today' => $today,
                    'order' => $user ? $lastOrders->get($user->id) : null,
                    'columns' => $row['columns'] ?? [],
                ];

                $args = [];
                $missing = [];
                foreach ($definitions as $index => $definition) {
                    $spec = $variables[$index] ?? ['source' => $definition['source']];
                    $value = $this->value($spec, $context);
                    $error = TemplateBody::valueError($value);

                    // مقدار جایگزین برای گیرنده‌ای که داده ندارد
                    if ($error && ($spec['source'] ?? 'fixed') !== 'fixed' && trim((string) ($spec['value'] ?? '')) !== '') {
                        $value = trim((string) $spec['value']);
                        $error = TemplateBody::valueError($value);
                    }

                    if ($error) {
                        $missing[] = $definition['label'] . ': ' . $error;
                    }

                    $args[$index] = trim((string) $value);
                }

                if ($missing) {
                    $skip('missing_vars', $display, implode('، ', $missing));
                    continue;
                }

                if (count($ready) >= $max) {
                    $skip('limit', $display);
                    continue;
                }

                $ready[] = [
                    'mobile' => $row['mobile'],
                    'name' => $name ? mb_substr($name, 0, 190) : null,
                    'user_id' => $user?->id,
                    'contact_id' => $contact?->id,
                    'args' => array_values($args),
                ];
            }
        }

        $samples = array_map(fn ($r) => [
            'mobile' => $r['mobile'],
            'name' => $r['name'],
            'text' => TemplateBody::render($template->body, $r['args']),
        ], array_slice($ready, 0, 5));

        $parts = $samples ? max(array_map(fn ($s) => TemplateBody::parts($s['text']), $samples)) : TemplateBody::parts($template->body);
        $price = config('sms.estimated_price');

        return [
            'ready' => $ready,
            'skipped' => $skipped,
            'issues' => $issues,
            'input' => $input,
            'samples' => $samples,
            'parts' => $parts,
            'estimated_cost' => $price !== null ? count($ready) * $parts * (int) $price : null,
        ];
    }

    /**
     * منابع مخاطب به‌صورت تکه‌های ۵۰۰تایی (هیچ‌وقت همه مشتریان یکجا بارگذاری نمی‌شوند)
     *
     * @return \Generator<list<array>>
     */
    protected function chunks(array $audience): \Generator
    {
        $source = $audience['source'] ?? '';

        switch ($source) {
            case 'single':
                yield [['raw' => (string) ($audience['mobile'] ?? ''), 'name' => trim((string) ($audience['name'] ?? '')) ?: null]];
                break;

            case 'manual':
                $lines = preg_split('/[\r\n,;،\t]+/u', (string) ($audience['numbers'] ?? '')) ?: [];
                $lines = array_values(array_filter(array_map('trim', $lines), fn ($l) => $l !== ''));
                foreach (array_chunk($lines, self::CHUNK) as $chunk) {
                    yield array_map(fn ($line) => ['raw' => $line], $chunk);
                }
                break;

            case 'customers':
                $query = CustomerFilter::query($audience['filters'] ?? []);
                if (($audience['selection'] ?? 'all') === 'selected') {
                    $query->whereIn('users.id', array_map('intval', $audience['selected_ids'] ?? []) ?: [0]);
                } elseif (! empty($audience['excluded_ids'])) {
                    $query->whereNotIn('users.id', array_map('intval', $audience['excluded_ids']));
                }

                $buffer = [];
                foreach ($query->select('users.id', 'users.first_name', 'users.last_name', 'users.mobile')->lazyById(self::CHUNK, 'users.id', 'id') as $user) {
                    $buffer[] = ['raw' => $user->mobile, 'user_id' => $user->id, 'name' => trim($user->first_name . ' ' . $user->last_name) ?: null];
                    if (count($buffer) >= self::CHUNK) {
                        yield $buffer;
                        $buffer = [];
                    }
                }
                if ($buffer) {
                    yield $buffer;
                }
                break;

            case 'list':
                $list = SmsContactList::findOrFail((int) ($audience['list_id'] ?? 0));
                $buffer = [];
                foreach ($list->contacts()->select('sms_contacts.id', 'sms_contacts.mobile', 'sms_contacts.name', 'sms_contacts.user_id')->lazyById(self::CHUNK, 'sms_contacts.id', 'id') as $contact) {
                    $buffer[] = ['raw' => $contact->mobile, 'name' => $contact->name, 'user_id' => $contact->user_id];
                    if (count($buffer) >= self::CHUNK) {
                        yield $buffer;
                        $buffer = [];
                    }
                }
                if ($buffer) {
                    yield $buffer;
                }
                break;

            case 'import':
                $data = self::importData((string) ($audience['import_token'] ?? ''));
                $mobileColumn = (int) ($audience['mobile_column'] ?? 0);
                $nameColumn = isset($audience['name_column']) && $audience['name_column'] !== '' ? (int) $audience['name_column'] : null;

                foreach (array_chunk($data['rows'], self::CHUNK) as $chunk) {
                    yield array_map(fn ($row) => [
                        'raw' => $row[$mobileColumn] ?? '',
                        'name' => $nameColumn !== null ? (trim($row[$nameColumn] ?? '') ?: null) : null,
                        'columns' => $row,
                    ], $chunk);
                }
                break;

            default:
                throw new RuntimeException('منبع مخاطب نامعتبر است.');
        }
    }

    protected function value(array $spec, array $context): ?string
    {
        $order = $context['order'];

        return match ($spec['source'] ?? 'fixed') {
            'fixed' => $spec['value'] ?? null,
            'name' => $context['name'],
            'first_name' => $context['first_name'],
            'mobile' => $context['mobile'],
            'today' => $context['today'],
            'last_order_number' => $order?->order_number,
            'last_order_amount' => $order ? number_format((int) $order->total_amount) : null,
            'last_order_date' => $order ? Jalalian::fromCarbon(\Illuminate\Support\Carbon::parse($order->created_at))->format('Y/m/d') : null,
            'last_order_status' => $order ? OrderStatus::badge($order->status)['title'] : null,
            'column' => isset($spec['column']) && $spec['column'] !== '' ? ($context['columns'][(int) $spec['column']] ?? null) : null,
            default => null,
        };
    }

    protected function needsOrders(array $variables, array $definitions): bool
    {
        foreach ($definitions as $index => $definition) {
            if (str_starts_with((string) ($variables[$index]['source'] ?? $definition['source']), 'last_order_')) {
                return true;
            }
        }

        return false;
    }

    /** آخرین سفارش هر کاربر */
    protected function lastOrders(array $userIds): \Illuminate\Support\Collection
    {
        $latest = DB::table('orders')->selectRaw('max(id) as id')->whereIn('user_id', $userIds)->groupBy('user_id');

        return DB::table('orders')
            ->whereIn('id', $latest)
            ->get(['id', 'user_id', 'order_number', 'total_amount', 'status', 'created_at'])
            ->keyBy('user_id');
    }

    /**
     * داده فایل واردشده (در کش، فقط برای کاربری که بارگذاری کرده)
     *
     * @return array{headers: list<string>, rows: list<list<string>>, user_id: int}
     */
    public static function importData(string $token): array
    {
        $data = $token !== '' ? Cache::get(self::IMPORT_CACHE_PREFIX . $token) : null;

        if (! $data || (int) ($data['user_id'] ?? 0) !== (int) auth()->id()) {
            throw new RuntimeException('فایل واردشده منقضی شده است؛ دوباره بارگذاری کنید.');
        }

        return $data;
    }

    public static function storeImport(array $data): string
    {
        $token = (string) \Illuminate\Support\Str::uuid();
        Cache::put(self::IMPORT_CACHE_PREFIX . $token, $data + ['user_id' => (int) auth()->id()], now()->addHours(3));

        return $token;
    }
}
