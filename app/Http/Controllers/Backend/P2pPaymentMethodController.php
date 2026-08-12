<?php

namespace App\Http\Controllers\Backend;

use Addons\P2PTrading\Models\AdsPaymentMethod;
use Addons\P2PTrading\Models\PaymentMethod;
use Addons\P2PTrading\Models\UserPaymentMethod;
use App\Enums\CurrencyType;
use App\Http\Controllers\Controller;
use App\Models\Currency;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

// [PHASE1] کنترلر مدیریت متدهای پرداخت P2P از پنل ادمین
// متدهای پرداخت مثل: کارت به کارت، انتقال بانکی، عیان‌الرحمن و...
// هر متد به یک کیورنسی فیات متصل است و فیلدهای داینامیک داره
// پرمیشن: p2p-payment-method-manage
class P2pPaymentMethodController extends Controller implements HasMiddleware
{
    public function __construct()
    {
        // [PHASE1] چک فعال بودن ماژول P2P
        abort_if(! addonActive('p2p-trading'), 404);
    }

    public static function middleware()
    {
        return [
            new Middleware('permission:p2p-payment-method-manage'),
        ];
    }

    /**
     * [PHASE1] لیست متدهای پرداخت
     */
    public function index()
    {
        $paymentMethods = PaymentMethod::with('currency')->latest()->paginate(10);

        return view('backend.p2p.payment-method.index', ['paymentMethods' => $paymentMethods]);
    }

    /**
     * [PHASE1] فرم ایجاد متد پرداخت جدید
     * لیست کیورنسی‌های فیات رو می‌فرسته
     */
    public function create()
    {
        $currencies = Currency::where('type', CurrencyType::Fiat)->get();

        return view('backend.p2p.payment-method.create', ['currencies' => $currencies]);
    }

    /**
     * [PHASE1] ذخیره متد پرداخت جدید
     * فیلدهای داینامیک JSON: هر فیلد شامل name, type, validation
     * type‌ها: text, textarea, file
     * validation‌ها: required, nullable
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'currency_id' => 'required|exists:currencies,id',
            'name' => 'required',
            'fields' => 'nullable|array',
            'fields.*.name' => 'required_with:fields',
            'fields.*.type' => 'required_with:fields|in:text,textarea,file',
            'fields.*.validation' => 'required_with:fields|in:required,nullable',
        ]);

        if ($validator->fails()) {
            notify()->error($validator->errors()->first());
            return redirect()->back()->withErrors($validator)->withInput();
        }

        // [PHASE1] فقط کیورنسی فیات مجاز است
        $currency = Currency::findOrFail($request->currency_id);
        if ($currency->type !== CurrencyType::Fiat) {
            notify()->error(__('Only fiat currencies are allowed for payment methods.'));
            return redirect()->back()->withInput();
        }

        try {
            DB::beginTransaction();

            // [PHASE1] فیلتر فیلدهای خالی
            $fields = $request->fields
                ? array_values(array_filter($request->fields, fn($f) => ! empty($f['name'])))
                : [];

            PaymentMethod::create([
                'currency_id' => $request->currency_id,
                'name' => $request->name,
                'fields' => $fields,
            ]);

            DB::commit();
            notify()->success(__('Payment method created successfully!'));
            return redirect()->route('admin.p2p.payment-method.index');
        } catch (\Throwable $throwable) {
            DB::rollBack();
            notify()->error(__('Sorry! Something went wrong. Please try again.'));
            return redirect()->back()->withInput();
        }
    }

    /**
     * [PHASE1] فرم ویرایش متد پرداخت
     */
    public function edit($id)
    {
        $paymentMethod = PaymentMethod::with('currency')->findOrFail($id);
        $currencies = Currency::where('type', CurrencyType::Fiat)->get();

        return view('backend.p2p.payment-method.edit', [
            'paymentMethod' => $paymentMethod,
            'currencies' => $currencies,
        ]);
    }

    /**
     * [PHASE1] بروزرسانی متد پرداخت
     */
    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'currency_id' => 'required|exists:currencies,id',
            'name' => 'required',
            'fields' => 'nullable|array',
            'fields.*.name' => 'required_with:fields',
            'fields.*.type' => 'required_with:fields|in:text,textarea,file',
            'fields.*.validation' => 'required_with:fields|in:required,nullable',
        ]);

        if ($validator->fails()) {
            notify()->error($validator->errors()->first());
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $currency = Currency::findOrFail($request->currency_id);
        if ($currency->type !== CurrencyType::Fiat) {
            notify()->error(__('Only fiat currencies are allowed for payment methods.'));
            return redirect()->back()->withInput();
        }

        try {
            DB::beginTransaction();

            $paymentMethod = PaymentMethod::findOrFail($id);

            $fields = $request->fields
                ? array_values(array_filter($request->fields, fn($f) => ! empty($f['name'])))
                : [];

            $paymentMethod->update([
                'currency_id' => $request->currency_id,
                'name' => $request->name,
                'fields' => $fields,
            ]);

            DB::commit();
            notify()->success(__('Payment method updated successfully!'));
            return redirect()->route('admin.p2p.payment-method.index');
        } catch (\Throwable $throwable) {
            DB::rollBack();
            notify()->error(__('Sorry! Something went wrong. Please try again.'));
            return redirect()->back()->withInput();
        }
    }

    /**
     * [PHASE1] حذف متد پرداخت
     * فقط اگه به هیچ آگهی یا حساب کاربری متصل نباشه
     */
    public function delete($id)
    {
        $paymentMethod = PaymentMethod::findOrFail($id);

        // [PHASE1] چک اتصال به آگهی‌ها و حساب‌های کاربری
        $adsCount = AdsPaymentMethod::where('payment_method_id', $id)->count();
        $userCount = UserPaymentMethod::where('payment_method_id', $id)->count();

        if ($adsCount > 0 || $userCount > 0) {
            notify()->error(__('This payment method is linked to ads or user payment methods and cannot be deleted.'));
            return redirect()->back();
        }

        try {
            DB::beginTransaction();
            $paymentMethod->delete();
            DB::commit();
            notify()->success(__('Payment method deleted successfully!'));
            return redirect()->back();
        } catch (\Throwable $throwable) {
            DB::rollBack();
            notify()->error(__('Sorry! Something went wrong. Please try again.'));
            return redirect()->back();
        }
    }
}
