<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;

/**
 * Class AdminLicenseController
 *
 * کنترلر مدیریت فروشگاه لایسنس‌های نرم‌افزار، صندوق امن کلیدها،
 * سفارشات، تحویل دیجیتال، و داوری اختلافات کلیدهای نامعتبر.
 */
class AdminLicenseController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:license-manage', only: ['index', 'orders', 'showOrder', 'inventory']),
            new Middleware('permission:license-action', only: ['importKeys', 'resolveDispute', 'revokeKey', 'storeProduct', 'updateProduct']),
        ];
    }

    /**
     * کاتالوگ محصولات لایسنس و آمار موجودی انبار کلیدها
     */
    public function index(Request $request)
    {
        $filters = [
            'category' => $request->input('category', 'all'),
            'search'   => $request->input('search'),
            'per_page' => $request->integer('per_page', 15),
        ];

        $stats = $this->getLicenseStats();
        $products = $this->queryProducts($filters);

        if ($request->expectsJson() || $request->wantsJson()) {
            return response()->json([
                'status'  => true,
                'message' => 'لیست محصولات لایسنس دریافت شد.',
                'data'    => [
                    'stats'    => $stats,
                    'filters'  => $filters,
                    'products' => $products,
                ],
            ]);
        }

        return view('backend.licenses.index', [
            'stats'    => $stats,
            'filters'  => $filters,
            'products' => $products,
        ]);
    }

    /**
     * لیست سفارشات مشتریان همراه با وضعیت پرداخت، تحویل و اختلافات
     */
    public function orders(Request $request)
    {
        $filters = [
            'status'   => $request->input('status', 'all'),
            'search'   => $request->input('search'),
            'per_page' => $request->integer('per_page', 15),
        ];

        $orders = $this->queryOrders($filters);

        if ($request->expectsJson() || $request->wantsJson()) {
            return response()->json([
                'status'  => true,
                'message' => 'لیست سفارشات لایسنس با موفقیت بارگذاری شد.',
                'data'    => $orders,
            ]);
        }

        return view('backend.licenses.orders', [
            'orders'  => $orders,
            'filters' => $filters,
        ]);
    }

    /**
     * مشاهده جزئیات سفارش، کلید تحویل‌شده و تایم‌لاین فعال‌سازی
     */
    public function showOrder(Request $request, $id)
    {
        $order = $this->findOrder($id);

        if (!$order) {
            if ($request->expectsJson()) {
                return response()->json(['status' => false, 'message' => 'سفارش لایسنس یافت نشد.'], 404);
            }
            if (function_exists('notify')) notify()->error('سفارش یافت نشد.');
            return redirect()->route('admin.licenses.orders');
        }

        if ($request->expectsJson()) {
            return response()->json(['status' => true, 'data' => $order]);
        }

        return view('backend.licenses.order_show', ['order' => $order]);
    }

    /**
     * صندوق امن انبار کلیدها (Vault Inventory)
     */
    public function inventory(Request $request)
    {
        $filters = [
            'product_id' => $request->input('product_id'),
            'status'     => $request->input('status', 'AVAILABLE'),
            'per_page'   => $request->integer('per_page', 20),
        ];

        $inventory = $this->queryInventory($filters);

        if ($request->expectsJson()) {
            return response()->json(['status' => true, 'data' => $inventory]);
        }

        return view('backend.licenses.inventory', ['inventory' => $inventory, 'filters' => $filters]);
    }

    /**
     * ورود دسته‌ای کلیدهای رمزگذاری‌شده به صندوق انبار
     */
    public function importKeys(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'product_id' => 'required',
            'edition'    => 'required|string|max:50',
            'keys_text'  => 'required|string', // هر کلید در یک سطر
        ]);

        if ($validator->fails()) return $this->errorResponse($request, $validator->errors()->first());

        try {
            DB::beginTransaction();

            $lines = explode("\n", str_replace("\r", "", $request->keys_text));
            $count = 0;

            foreach ($lines as $line) {
                $rawKey = trim($line);
                if (empty($rawKey)) continue;

                if (Schema::hasTable('license_keys_vault')) {
                    DB::table('license_keys_vault')->insert([
                        'product_id'     => $request->product_id,
                        'edition'        => $request->edition,
                        'encrypted_key'  => encrypt($rawKey),
                        'key_masked'     => substr($rawKey, 0, 4) . '****' . substr($rawKey, -4),
                        'status'         => 'AVAILABLE',
                        'imported_by'    => Auth::id() ?? 1,
                        'created_at'     => now(),
                    ]);
                }
                $count++;
            }

            $this->logActivity('license_keys_imported', $request->product_id, 'LicenseProduct', ['count' => $count]);

            DB::commit();

            $msg = "تعداد {$count} عدد کلید لایسنس جدید با موفقیت به صندوق انبار اضافه گردید.";
            if ($request->expectsJson()) {
                return response()->json(['status' => true, 'message' => $msg, 'imported_count' => $count]);
            }

            if (function_exists('notify')) notify()->success($msg);
            return back();

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse($request, $e->getMessage());
        }
    }

    /**
     * داوری اختلاف سفارش لایسنس (تعویض کلید از انبار یا استرداد وجه به والت)
     */
    public function resolveDispute(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'resolution_type' => 'required|in:REPLACE_KEY,REFUND_WALLET,REJECT_DISPUTE',
            'admin_note'      => 'required|string|max:1000',
        ]);

        if ($validator->fails()) return $this->errorResponse($request, $validator->errors()->first());

        $admin = Auth::user();
        $adminId = $admin ? $admin->id : 1;

        try {
            DB::beginTransaction();

            $order = $this->findOrder($id);
            if (!$order) throw new \Exception('سفارش یافت نشد.');

            $type = $request->resolution_type;

            if ($type === 'REPLACE_KEY') {
                // اختصاص یک کلید معتبر دیگر از انبار
                $newKey = null;
                if (Schema::hasTable('license_keys_vault')) {
                    $newKey = DB::table('license_keys_vault')
                        ->where('product_id', $order['product_id'] ?? 0)
                        ->where('status', 'AVAILABLE')
                        ->lockForUpdate()
                        ->first();

                    if (!$newKey) throw new \Exception('کلید جایگزین موجود در انبار یافت نشد.');

                    DB::table('license_keys_vault')->where('id', $newKey->id)->update([
                        'status'      => 'ASSIGNED',
                        'order_id'    => $id,
                        'assigned_at' => now(),
                    ]);

                    // ابطال کلید قبلی
                    if (!empty($order['license_key_id'])) {
                        DB::table('license_keys_vault')->where('id', $order['license_key_id'])->update(['status' => 'REVOKED']);
                    }
                }

                if (Schema::hasTable('license_orders')) {
                    DB::table('license_orders')->where('id', $id)->update([
                        'status'         => 'DELIVERED',
                        'license_key_id' => $newKey ? $newKey->id : null,
                        'dispute_status' => 'RESOLVED_REPLACED',
                        'admin_note'     => $request->admin_note,
                        'updated_at'     => now(),
                    ]);
                }
                $msg = 'کلید جدید جایگزین و برای مشتری ارسال گردید.';

            } elseif ($type === 'REFUND_WALLET') {
                // استرداد وجه به کیف پول با لجر دوطرفه
                $amount = (float) ($order['total_amount'] ?? 0);
                $userId = $order['user_id'] ?? null;

                if ($amount > 0 && $userId && Schema::hasTable('wallets') && Schema::hasTable('journal_entries')) {
                    $wallet = DB::table('wallets')->where('user_id', $userId)->orderBy('id', 'asc')->lockForUpdate()->first();
                    if ($wallet) {
                        DB::table('wallets')->where('id', $wallet->id)->increment('balance', $amount);

                        DB::table('journal_entries')->insert([
                            'reference_type' => 'LICENSE_ORDER_REFUND',
                            'reference_id'   => $id,
                            'debit_account'  => 'LICENSE_MERCHANT_REVENUE',
                            'credit_account' => "WALLET_{$wallet->id}",
                            'amount'         => $amount,
                            'currency'       => $order['currency'] ?? 'USD',
                            'operator_id'    => $adminId,
                            'created_at'     => now(),
                        ]);
                    }
                }

                if (Schema::hasTable('license_orders')) {
                    DB::table('license_orders')->where('id', $id)->update([
                        'status'         => 'REFUNDED',
                        'dispute_status' => 'RESOLVED_REFUNDED',
                        'admin_note'     => $request->admin_note,
                        'updated_at'     => now(),
                    ]);
                }
                $msg = 'مبلغ سفارش به کیف پول کاربر عودت داده شد و کلید باطل گردید.';

            } else {
                // رد ادعای کاربر
                if (Schema::hasTable('license_orders')) {
                    DB::table('license_orders')->where('id', $id)->update([
                        'dispute_status' => 'REJECTED',
                        'admin_note'     => $request->admin_note,
                        'updated_at'     => now(),
                    ]);
                }
                $msg = 'ادعای اختلاف پس از بررسی توسط ادمین رد شد.';
            }

            $this->logActivity('license_dispute_resolved', $id, 'LicenseOrder', [
                'type' => $type,
                'note' => $request->admin_note,
            ]);

            DB::commit();

            if ($request->expectsJson()) {
                return response()->json(['status' => true, 'message' => $msg]);
            }

            if (function_exists('notify')) notify()->success($msg);
            return back();

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse($request, $e->getMessage());
        }
    }

    /**
     * ابطال دستی یک کلید لایسنس
     */
    public function revokeKey(Request $request, $keyId)
    {
        try {
            if (Schema::hasTable('license_keys_vault')) {
                DB::table('license_keys_vault')->where('id', $keyId)->update([
                    'status'     => 'REVOKED',
                    'updated_at' => now(),
                ]);
            }

            $this->logActivity('license_key_revoked', $keyId, 'LicenseKey');

            if ($request->expectsJson()) return response()->json(['status' => true, 'message' => 'کلید ابطال شد.']);
            if (function_exists('notify')) notify()->success('کلید ابطال شد.');
            return back();
        } catch (\Exception $e) {
            return $this->errorResponse($request, $e->getMessage());
        }
    }

    // -------------------------------------------------------------
    // متدهای کمکی داخلی
    // -------------------------------------------------------------

    protected function getLicenseStats(): array
    {
        if (Schema::hasTable('license_orders')) {
            return [
                'total_orders'    => DB::table('license_orders')->count(),
                'disputed_orders' => DB::table('license_orders')->where('dispute_status', 'OPEN')->count(),
                'delivered_count' => DB::table('license_orders')->where('status', 'DELIVERED')->count(),
                'keys_available'  => Schema::hasTable('license_keys_vault') ? DB::table('license_keys_vault')->where('status', 'AVAILABLE')->count() : 0,
            ];
        }

        return ['total_orders' => 0, 'disputed_orders' => 0, 'delivered_count' => 0, 'keys_available' => 0];
    }

    protected function queryProducts(array $filters): array
    {
        if (Schema::hasTable('license_products')) {
            $query = DB::table('license_products');
            if (!empty($filters['category']) && $filters['category'] !== 'all') {
                $query->where('category', $filters['category']);
            }
            if (!empty($filters['search'])) {
                $s = $filters['search'];
                $query->where('name', 'like', "%{$s}%")->orWhere('slug', 'like', "%{$s}%");
            }
            $paginator = $query->orderBy('name')->paginate($filters['per_page']);
            return [
                'current_page' => $paginator->currentPage(),
                'total'        => $paginator->total(),
                'last_page'    => $paginator->lastPage(),
                'data'         => $paginator->items(),
            ];
        }
        return ['current_page' => 1, 'total' => 0, 'last_page' => 1, 'data' => []];
    }

    protected function queryOrders(array $filters): array
    {
        if (Schema::hasTable('license_orders')) {
            $query = DB::table('license_orders as lo')
                ->leftJoin('users as u', 'lo.user_id', '=', 'u.id')
                ->select('lo.*', 'u.name as user_name', 'u.email as user_email');

            if (!empty($filters['status']) && $filters['status'] !== 'all') {
                $query->where('lo.status', $filters['status']);
            }
            if (!empty($filters['search'])) {
                $s = $filters['search'];
                $query->where(function ($q) use ($s) {
                    $q->where('lo.order_number', 'like', "%{$s}%")
                      ->orWhere('u.name', 'like', "%{$s}%")
                      ->orWhere('u.email', 'like', "%{$s}%");
                });
            }
            $paginator = $query->orderByDesc('lo.id')->paginate($filters['per_page']);
            return [
                'current_page' => $paginator->currentPage(),
                'total'        => $paginator->total(),
                'last_page'    => $paginator->lastPage(),
                'data'         => $paginator->items(),
            ];
        }
        return ['current_page' => 1, 'total' => 0, 'last_page' => 1, 'data' => []];
    }

    protected function findOrder($id): ?array
    {
        if (Schema::hasTable('license_orders')) {
            $rec = DB::table('license_orders as lo')
                ->leftJoin('users as u', 'lo.user_id', '=', 'u.id')
                ->select('lo.*', 'u.name as user_name', 'u.email as user_email')
                ->where('lo.id', $id)
                ->first();

            return $rec ? (array) $rec : null;
        }
        return null;
    }

    protected function queryInventory(array $filters): array
    {
        if (Schema::hasTable('license_keys_vault')) {
            $query = DB::table('license_keys_vault');
            if (!empty($filters['product_id'])) $query->where('product_id', $filters['product_id']);
            if (!empty($filters['status'])) $query->where('status', $filters['status']);
            $paginator = $query->orderByDesc('id')->paginate($filters['per_page']);
            return [
                'current_page' => $paginator->currentPage(),
                'total'        => $paginator->total(),
                'last_page'    => $paginator->lastPage(),
                'data'         => $paginator->items(),
            ];
        }
        return ['current_page' => 1, 'total' => 0, 'last_page' => 1, 'data' => []];
    }

    protected function logActivity(string $action, $id, string $type, array $details = []): void
    {
        try {
            if (Schema::hasTable('admin_audit_logs')) {
                DB::table('admin_audit_logs')->insert([
                    'admin_id'    => Auth::id() ?? 1,
                    'action'      => $action,
                    'entity_id'   => $id,
                    'entity_type' => $type,
                    'details'     => json_encode($details, JSON_UNESCAPED_UNICODE),
                    'ip_address'  => request()->ip(),
                    'created_at'  => now(),
                ]);
            } else {
                Log::info("[AdminAuditLog] {$action} on {$type}#{$id}", $details);
            }
        } catch (\Exception $e) {
            Log::warning("[AdminAuditLog Error] " . $e->getMessage());
        }
    }

    protected function errorResponse(Request $request, string $msg, int $code = 400)
    {
        if ($request->expectsJson()) return response()->json(['status' => false, 'message' => $msg], $code);
        if (function_exists('notify')) notify()->error($msg);
        return back()->withInput();
    }
}
