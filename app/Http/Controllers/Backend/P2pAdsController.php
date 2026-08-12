<?php

namespace App\Http\Controllers\Backend;

use Addons\P2PTrading\Models\Ads;
use Addons\P2PTrading\Services\P2pAdsService;
use App\Http\Controllers\Controller;
use App\Traits\NotifyTrait;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Validator;

// [PHASE1] کنترلر مدیریت آگهی‌های P2P از پنل ادمین
// پرمیشن‌ها:
//   p2p-ads-manage: مشاهده لیست آگهی‌ها و آگهی‌های در انتظار
//   p2p-ads-approve: تایید/رد آگهی‌ها
class P2pAdsController extends Controller implements HasMiddleware
{
    use NotifyTrait;

    public function __construct(
        protected P2pAdsService $p2pAdsService
    ) {
        // [PHASE1] چک فعال بودن ماژول P2P
        abort_if(! addonActive('p2p-trading'), 404);
    }

    public static function middleware()
    {
        return [
            new Middleware('permission:p2p-ads-manage', only: ['index', 'pending']),
            new Middleware('permission:p2p-ads-approve', only: ['action', 'actionNow']),
        ];
    }

    /**
     * [PHASE1] لیست تمام آگهی‌ها با فیلترها
     * فیلترها: وضعیت، نوع، بازار (ایران/چین)، دلار نقد، کیورنسی‌ها، جستجو
     */
    public function index(Request $request)
    {
        $filters = array_filter([
            'status' => $request->status,
            'type' => $request->type,
            'market_type' => $request->market_type,       // [PHASE1] فیلتر بازار
            'is_cash_dollar' => $request->is_cash_dollar,  // [PHASE1] فیلتر دلار نقد
            'fiat_currency' => $request->fiat_currency,
            'asset_currency' => $request->asset_currency,
            'search' => $request->search,
        ]) + ['per_page' => $request->integer('per_page', 15)];

        $ads = $this->p2pAdsService->listAds($filters);

        return view('backend.p2p.ads.index', [
            'ads' => $ads,
        ]);
    }

    /**
     * [PHASE1] لیست آگهی‌های در انتظار تایید
     */
    public function pending(Request $request)
    {
        $filters = array_filter([
            'search' => $request->search,
        ]) + ['per_page' => $request->integer('per_page', 15)];

        $ads = $this->p2pAdsService->listPendingAds($filters);

        return view('backend.p2p.ads.pending', [
            'ads' => $ads,
        ]);
    }

    /**
     * [PHASE1] نمایش مودال تایید/رد آگهی (AJAX)
     */
    public function action($id)
    {
        $ad = $this->p2pAdsService->getAdForApproval($id);

        return view('backend.p2p.ads.include.__ads_action', [
            'ad' => $ad,
            'id' => $id,
        ])->render();
    }

    /**
     * [PHASE1] اعمال تایید یا رد آگهی
     * ادمین می‌تونه پیام هم بفرسته که به کاربر ایمیل می‌شه
     */
    public function actionNow(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|exists:p2p_ads,id',
            'approve' => 'nullable|boolean',
            'reject' => 'nullable|boolean',
            'message' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            notify()->error($validator->errors()->first());
            return back();
        }

        try {
            $ad = Ads::findOrFail($request->id);

            // [PHASE1] تایید آگهی
            if ($request->has('approve') && $request->approve) {
                $result = $this->p2pAdsService->approveAd($ad, $request->message);

                $this->sendNotify(
                    $result['ad']->user->email,
                    $result['template'],
                    'User',
                    $result['shortcodes'],
                    $result['ad']->user->phone,
                    $result['ad']->user->id,
                    ''
                );

                notify()->success(__('Ad approved successfully!'));

            // [PHASE1] رد آگهی
            } elseif ($request->has('reject') && $request->reject) {
                $result = $this->p2pAdsService->rejectAd($ad, $request->message);

                $this->sendNotify(
                    $result['ad']->user->email,
                    $result['template'],
                    'User',
                    $result['shortcodes'],
                    $result['ad']->user->phone,
                    $result['ad']->user->id,
                    ''
                );

                notify()->success(__('Ad rejected successfully!'));
            } else {
                notify()->error(__('Invalid action!'));
                return back();
            }

            return back();
        } catch (\Exception $e) {
            notify()->error(__('Sorry! Something went wrong.'));
            return back();
        }
    }
}
