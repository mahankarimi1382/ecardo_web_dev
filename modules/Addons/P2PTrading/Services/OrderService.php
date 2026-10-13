<?php

namespace Addons\P2PTrading\Services;

use Addons\P2PTrading\Enums\AdsStatus;
use Addons\P2PTrading\Enums\AdsType;
use Addons\P2PTrading\Enums\OrderMessageActorType;
use Addons\P2PTrading\Enums\OrderStatus;
use Addons\P2PTrading\Models\Ads;
use Addons\P2PTrading\Models\AdsOrder;
use Addons\P2PTrading\Models\AdsOrderMessage;
use App\Enums\TxnStatus;
use App\Enums\TxnType;
use App\Models\Admin;
use App\Models\Currency;
use App\Models\Transaction;
use App\Models\User;
use App\Models\UserWallet;
use App\Services\Core\LedgerService;
use App\Traits\ImageUpload;
use App\Traits\NotifyTrait;
use Exception;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OrderService
{
    use ImageUpload, NotifyTrait;

    protected const AMOUNT_EPSILON = 0.00000001;

    /**
     * List user orders where the user is buyer or seller.
     */
    public function listUserOrders(User $user, array $filters = []): LengthAwarePaginator
    {
        return AdsOrder::with([
            'ad.paymentMethods',
            'seller',
            'buyer',
            'assetCurrency',
            'fiatCurrency',
            'paymentMethod',
        ])
            ->where(function (Builder $query) use ($user) {
                $query->where('buyer_user_id', $user->id)
                    ->orWhere('seller_user_id', $user->id);
            })
            ->when(
                isset($filters['order_number']) && ! empty($filters['order_number']),
                function (Builder $query) use ($filters) {
                    $orderNumber = Str::replaceFirst('ORD-', '', $filters['order_number']);
                    $query->whereLike('id', '%' . $orderNumber . '%');
                }
            )
            ->when(
                isset($filters['status']) && in_array($filters['status'], array_column(OrderStatus::cases(), 'value'), true),
                fn(Builder $query) => $query->where('status', OrderStatus::from($filters['status']))
            )
            ->latest()
            ->paginate($filters['per_page'] ?? 15);
    }

    /**
     * List all orders for admin panel.
     */
    public function listOrders(array $filters = []): LengthAwarePaginator
    {
        return AdsOrder::with([
            'ad.paymentMethods',
            'seller',
            'buyer',
            'assetCurrency',
            'fiatCurrency',
            'paymentMethod',
        ])
            ->when(
                isset($filters['status']) && in_array($filters['status'], array_column(OrderStatus::cases(), 'value'), true),
                fn(Builder $query) => $query->where('status', OrderStatus::from($filters['status']))
            )
            ->when(! empty($filters['market_type']), function (Builder $query) use ($filters) {
                // v55: فیلتر market_type از طریق رابطه ad
                $query->whereHas('ad', fn(Builder $sq) => $sq->where('market_type', $filters['market_type']));
            })
            ->when(! empty($filters['search']), function (Builder $query) use ($filters) {
                $search = $filters['search'];
                $query->where(function (Builder $q) use ($search) {
                    $q->where('id', $search)
                        ->orWhereHas('buyer', fn(Builder $b) => $b->where('username', 'like', '%' . $search . '%')->orWhere('email', 'like', '%' . $search . '%'))
                        ->orWhereHas('seller', fn(Builder $s) => $s->where('username', 'like', '%' . $search . '%')->orWhere('email', 'like', '%' . $search . '%'));
                });
            })
            ->latest()
            ->paginate($filters['per_page'] ?? 15);
    }

    /**
     * Get one order for user.
     */
    public function getUserOrder(User $user, int $id): ?AdsOrder
    {
        return AdsOrder::with([
            'ad.paymentMethods',
            'seller',
            'buyer',
            'assetCurrency',
            'fiatCurrency',
            'paymentMethod',
        ])
            ->where('id', $id)
            ->where(function (Builder $query) use ($user) {
                $query->where('buyer_user_id', $user->id)
                    ->orWhere('seller_user_id', $user->id);
            })
            ->first();
    }

    /**
     * Get one order for admin.
     */
    public function getOrder(int $id): AdsOrder
    {
        return AdsOrder::with([
            'ad.paymentMethods',
            'ad.user',
            'seller',
            'buyer',
            'creator',
            'resolver',
            'assetCurrency',
            'fiatCurrency',
            'paymentMethod',
            'messages',
        ])->findOrFail($id);
    }

    /**
     * Create a new order from an ad.
     */
    public function createOrder(User $creator, array $data): AdsOrder
    {
        return DB::transaction(function () use ($creator, $data) {
            /** @var Ads $ad */
            $ad = Ads::with(['paymentMethods'])
                ->where('id', $data['ad_id'])
                ->lockForUpdate()
                ->firstOrFail();

            if ($ad->status !== AdsStatus::Active) {
                throw new Exception(__('This ad is not active.'));
            }

            $price = (float) $ad->price;
            if ($price <= 0) {
                throw new Exception(__('Invalid ad price.'));
            }

            // Buy ads should accept fiat input, sell ads should accept crypto input.
            if ($ad->type === AdsType::Buy) {
                $inputFiatAmount = $this->normalizeAmountInput($data['fiat_amount'] ?? $data['asset_amount'] ?? null);
                if ($inputFiatAmount === null || $inputFiatAmount <= 0) {
                    throw new Exception(__('Fiat amount is required for buy ads.'));
                }

                $fiatAmount = round($inputFiatAmount, 8);
                $assetAmount = round($fiatAmount / $price, 8);
            } else {
                $inputAssetAmount = $this->normalizeAmountInput($data['asset_amount'] ?? null);
                if ($inputAssetAmount === null || $inputAssetAmount <= 0) {
                    throw new Exception(__('Asset amount is required for sell ads.'));
                }

                $assetAmount = round($inputAssetAmount, 8);
                $fiatAmount = round($assetAmount * $price, 8);
            }

            if ((int) $ad->user_id === (int) $creator->id) {
                throw new Exception(__('You cannot create an order on your own ad.'));
            }

            // Min/max limits are set as fiat order limits.
            if ($fiatAmount + self::AMOUNT_EPSILON < (float) $ad->min_amount) {
                throw new Exception(__('Order amount is below minimum amount.'));
            }

            if ($fiatAmount - self::AMOUNT_EPSILON > (float) $ad->max_amount) {
                throw new Exception(__('Order amount exceeds maximum amount.'));
            }

            if ($assetAmount - self::AMOUNT_EPSILON > (float) $ad->total_amount) {
                throw new Exception(__('Insufficient ad liquidity.'));
            }

            $paymentMethodAllowed = $ad->paymentMethods()->where('payment_method_id', $data['payment_method_id'])->exists();
            if (! $paymentMethodAllowed) {
                throw new Exception(__('Selected payment method is not available for this ad.'));
            }

            $feePercent = (float) setting('order_fee_percent', 'p2p');
            $feeAssetAmount = ($assetAmount * $feePercent) / 100;
            $lockedAmount = $assetAmount + $feeAssetAmount;

            $seller = $ad->type === AdsType::Sell ? $ad->user : $creator;
            $buyer = $ad->type === AdsType::Sell ? $creator : $ad->user;

            $sellerWallet = $this->debitUserBalance($seller, (int) $ad->asset_currency_id, $lockedAmount);

            $order = AdsOrder::create([
                'ads_id' => $ad->id,
                'ad_owner_id' => $ad->user_id,
                'order_creator_id' => $creator->id,
                'seller_user_id' => $seller->id,
                'buyer_user_id' => $buyer->id,
                'asset_currency_id' => $ad->asset_currency_id,
                'fiat_currency_id' => $ad->fiat_currency_id,
                'payment_method_id' => $data['payment_method_id'],
                'asset_amount' => $assetAmount,
                'fiat_amount' => $fiatAmount,
                'price' => $ad->price,
                'fee_percent' => $feePercent,
                'fee_asset_amount' => $feeAssetAmount,
                'seller_locked_asset' => $lockedAmount,
                'seller_payment_snapshot' => $this->buildSellerPaymentSnapshot($seller, (int) $data['payment_method_id']),
                'status' => OrderStatus::PendingPayment,
                'payment_deadline_at' => now()->addMinutes((int) $ad->payment_duration),
            ]);

            if (! empty(trim((string) $ad->auto_response_message))) {
                AdsOrderMessage::create([
                    'order_id' => $order->id,
                    'sender_type' => OrderMessageActorType::User,
                    'sender_id' => $ad->user_id,
                    'message' => $ad->auto_response_message,
                ]);
            }

            $ad->decrement('total_amount', $assetAmount);
            $this->updateAdStatusByLiquidity($ad->fresh());

            $this->createTransaction(
                user: $seller,
                wallet: $sellerWallet,
                type: TxnType::Debit,
                description: 'P2P Order Escrow Lock #' . $order->id,
                amount: $lockedAmount,
                orderId: $order->id
            );

            // 📒 ثبت در لجر: قفل دارایی فروشنده در اسکرو P2P
            $this->postOrderEscrowLockLedger($order, $seller, $sellerWallet, (float) $lockedAmount);

            // Notify Seller
            $this->notifyOrder($order, 'p2p_order_created_seller', $seller);
            // Notify Buyer
            $this->notifyOrder($order, 'p2p_order_created_buyer', $buyer);

            return $order->fresh([
                'ad.paymentMethods',
                'seller',
                'buyer',
                'assetCurrency',
                'fiatCurrency',
                'paymentMethod',
            ]);
        });
    }

    /**
     * Update order payment method.
     */
    public function updatePaymentMethod(AdsOrder $order, User $actor, int $paymentMethodId): AdsOrder
    {
        return DB::transaction(function () use ($order, $actor, $paymentMethodId) {
            /** @var AdsOrder $lockedOrder */
            $lockedOrder = AdsOrder::with(['ad.paymentMethods'])->where('id', $order->id)->lockForUpdate()->firstOrFail();

            if ($lockedOrder->status !== OrderStatus::PendingPayment) {
                throw new Exception(__('Only pending orders can have their payment method updated.'));
            }

            if ($lockedOrder->payment_deadline_at && now()->greaterThan($lockedOrder->payment_deadline_at)) {
                throw new Exception(__('Payment window has expired.'));
            }

            $paymentMethodAllowed = $lockedOrder->ad->paymentMethods()->where('payment_method_id', $paymentMethodId)->exists();
            if (! $paymentMethodAllowed) {
                throw new Exception(__('Selected payment method is not available for this ad.'));
            }

            $lockedOrder->update([
                'payment_method_id' => $paymentMethodId,
            ]);

            return $lockedOrder->fresh(['ad.paymentMethods', 'seller', 'buyer', 'assetCurrency', 'fiatCurrency', 'paymentMethod']);
        });
    }

    /**
     * Buyer marks order as paid.
     * تسویه: خریدار می‌تواند شماره پیگیری/مرجع پرداخت را ضمیمه کند (اختیاری).
     */
    public function markPaid(AdsOrder $order, User $actor, ?string $paymentReference = null): AdsOrder
    {
        return DB::transaction(function () use ($order, $actor, $paymentReference) {
            /** @var AdsOrder $lockedOrder */
            $lockedOrder = AdsOrder::where('id', $order->id)->lockForUpdate()->firstOrFail();

            if ((int) $lockedOrder->buyer_user_id !== (int) $actor->id) {
                throw new Exception(__('Only buyer can mark this order as paid.'));
            }

            if ($lockedOrder->status !== OrderStatus::PendingPayment) {
                throw new Exception(__('Only pending orders can be marked as paid.'));
            }

            if ($lockedOrder->payment_deadline_at && now()->greaterThan($lockedOrder->payment_deadline_at)) {
                throw new Exception(__('Payment window has expired.'));
            }

            $lockedOrder->update([
                'status' => OrderStatus::Paid,
                'marked_paid_at' => now(),
                'payment_reference' => $paymentReference,
            ]);

            // Notify Seller
            $this->notifyOrder($lockedOrder, 'p2p_order_paid_seller', $lockedOrder->seller);

            return $lockedOrder->fresh(['ad.paymentMethods', 'seller', 'buyer', 'assetCurrency', 'fiatCurrency', 'paymentMethod']);
        });
    }

    /**
     * Seller releases asset to buyer.
     */
    public function release(AdsOrder $order, User $actor): AdsOrder
    {
        return DB::transaction(function () use ($order, $actor) {
            /** @var AdsOrder $lockedOrder */
            $lockedOrder = AdsOrder::where('id', $order->id)->lockForUpdate()->firstOrFail();

            if ((int) $lockedOrder->seller_user_id !== (int) $actor->id) {
                throw new Exception(__('Only seller can release this order.'));
            }

            if ($lockedOrder->status !== OrderStatus::Paid) {
                throw new Exception(__('Only paid orders can be released.'));
            }

            $buyer = User::where('id', $lockedOrder->buyer_user_id)->lockForUpdate()->firstOrFail();
            $buyerWallet = $this->creditUserBalance($buyer, (int) $lockedOrder->asset_currency_id, (float) $lockedOrder->asset_amount);

            $lockedOrder->update([
                'status' => OrderStatus::Completed,
                'completed_at' => now(),
                'resolved_at' => now(),
            ]);

            $this->createTransaction(
                user: $buyer,
                wallet: $buyerWallet,
                type: TxnType::Credit,
                description: 'P2P Order Release #' . $lockedOrder->id,
                amount: (float) $lockedOrder->asset_amount,
                orderId: $lockedOrder->id
            );

            // تسویه کارمزد تیکر به حساب پلتفرم (اگر p2p_fee_recipient_user_id ست باشد)
            $this->settleFee($lockedOrder);

            // 📒 ثبت در لجر: آزادسازی دارایی اسکرو به خریدار + کارمزد
            $this->postOrderReleaseLedger($lockedOrder, $buyer, $buyerWallet);

            // Notify Buyer
            $this->notifyOrder($lockedOrder, 'p2p_order_released_buyer', $buyer);

            return $lockedOrder->fresh(['ad.paymentMethods', 'seller', 'buyer', 'assetCurrency', 'fiatCurrency', 'paymentMethod']);
        });
    }

    /**
     * Cancel an order before payment is marked.
     */
    public function cancel(AdsOrder $order, User $actor): AdsOrder
    {
        return DB::transaction(function () use ($order, $actor) {
            /** @var AdsOrder $lockedOrder */
            $lockedOrder = AdsOrder::where('id', $order->id)->lockForUpdate()->firstOrFail();

            if (! in_array($actor->id, [$lockedOrder->buyer_user_id, $lockedOrder->seller_user_id], true)) {
                throw new Exception(__('You are not authorized to cancel this order.'));
            }

            if ($lockedOrder->status !== OrderStatus::PendingPayment) {
                throw new Exception(__('Only pending orders can be cancelled.'));
            }

            $this->refundAndRestoreAd($lockedOrder);

            $lockedOrder->update([
                'status' => OrderStatus::Cancelled,
                'cancelled_at' => now(),
                'resolved_at' => now(),
            ]);

            $extra = ['[[cancelled_by_name]]' => $actor->full_name];
            // Notify Seller
            $this->notifyOrder($lockedOrder, 'p2p_order_cancelled', $lockedOrder->seller, $extra);
            // Notify Buyer
            $this->notifyOrder($lockedOrder, 'p2p_order_cancelled', $lockedOrder->buyer, $extra);

            return $lockedOrder->fresh(['ad.paymentMethods', 'seller', 'buyer', 'assetCurrency', 'fiatCurrency', 'paymentMethod']);
        });
    }

    /**
     * Open dispute on a paid order.
     */
    public function dispute(AdsOrder $order, User $actor, ?string $reason): AdsOrder
    {
        return DB::transaction(function () use ($order, $actor, $reason) {
            /** @var AdsOrder $lockedOrder */
            $lockedOrder = AdsOrder::where('id', $order->id)->lockForUpdate()->firstOrFail();

            if (! in_array($actor->id, [$lockedOrder->buyer_user_id, $lockedOrder->seller_user_id], true)) {
                throw new Exception(__('You are not authorized to dispute this order.'));
            }

            if ($lockedOrder->status !== OrderStatus::Paid) {
                throw new Exception(__('Only paid orders can be disputed.'));
            }

            $lockedOrder->update([
                'status' => OrderStatus::Disputed,
                'disputed_at' => now(),
                'dispute_reason' => $reason,
            ]);

            $extra = ['[[disputed_by_name]]' => $actor->full_name];
            // Notify Seller
            $this->notifyOrder($lockedOrder, 'p2p_order_disputed', $lockedOrder->seller, $extra);
            // Notify Buyer
            $this->notifyOrder($lockedOrder, 'p2p_order_disputed', $lockedOrder->buyer, $extra);

            return $lockedOrder->fresh(['ad.paymentMethods', 'seller', 'buyer', 'assetCurrency', 'fiatCurrency', 'paymentMethod']);
        });
    }

    /**
     * Resolve dispute from admin.
     */
    public function resolveDispute(AdsOrder $order, ?Admin $admin, string $action, ?string $note): AdsOrder
    {
        return DB::transaction(function () use ($order, $admin, $action, $note) {
            /** @var AdsOrder $lockedOrder */
            $lockedOrder = AdsOrder::where('id', $order->id)->lockForUpdate()->firstOrFail();

            if (! in_array($lockedOrder->status, [OrderStatus::Disputed, OrderStatus::Paid], true)) {
                throw new Exception(__('This order cannot be resolved.'));
            }

            $adminId = ($admin && $admin->id > 0) ? $admin->id : null;

            if ($action === 'release_to_buyer') {
                $buyer = User::where('id', $lockedOrder->buyer_user_id)->lockForUpdate()->firstOrFail();
                $buyerWallet = $this->creditUserBalance($buyer, (int) $lockedOrder->asset_currency_id, (float) $lockedOrder->asset_amount);

                $lockedOrder->update([
                    'status' => OrderStatus::Completed,
                    'completed_at' => now(),
                    'resolved_at' => now(),
                    'resolved_by_admin_id' => $adminId,
                    'resolution_note' => $note,
                ]);

                $this->createTransaction(
                    user: $buyer,
                    wallet: $buyerWallet,
                    type: TxnType::Credit,
                    description: 'P2P Order Release #' . $lockedOrder->id,
                    amount: (float) $lockedOrder->asset_amount,
                    orderId: $lockedOrder->id
                );

                // تسویه کارمزد تیکر به حساب پلتفرم (اگر p2p_fee_recipient_user_id ست باشد)
                $this->settleFee($lockedOrder);

                // 📒 ثبت در لجر: رأی داوری ادمین — آزادسازی اسکرو به خریدار + کارمزد
                $this->postOrderReleaseLedger($lockedOrder, $buyer, $buyerWallet, $admin, $note);
            } elseif ($action === 'refund_to_seller') {
                $sellerWallet = $this->refundAndRestoreAd($lockedOrder, $admin, $note);

                $lockedOrder->update([
                    'status' => OrderStatus::Cancelled,
                    'cancelled_at' => now(),
                    'resolved_at' => now(),
                    'resolved_by_admin_id' => $adminId,
                    'resolution_note' => $note,
                ]);
            } else {
                throw new Exception(__('Invalid resolution action.'));
            }

            $extra = [
                '[[resolution_action]]' => str_replace('_', ' ', $action),
                '[[resolution_note]]' => $note ?? __('N/A'),
            ];
            // Notify Seller
            $this->notifyOrder($lockedOrder, 'p2p_order_resolved', $lockedOrder->seller, $extra);
            // Notify Buyer
            $this->notifyOrder($lockedOrder, 'p2p_order_resolved', $lockedOrder->buyer, $extra);

            return $lockedOrder->fresh(['ad.paymentMethods', 'seller', 'buyer', 'assetCurrency', 'fiatCurrency', 'paymentMethod']);
        });
    }

    /**
     * Get order messages for user.
     */
    public function listUserMessages(AdsOrder $order, User $user): Collection
    {
        $this->ensureUserParticipant($order, $user);

        return AdsOrderMessage::where('order_id', $order->id)
            ->with(['user', 'admin'])
            ->orderBy('id')
            ->get();
    }

    /**
     * Get order messages for admin.
     */
    public function listOrderMessages(AdsOrder $order): Collection
    {
        return AdsOrderMessage::where('order_id', $order->id)
            ->with(['user', 'admin'])
            ->orderBy('id')
            ->get();
    }

    /**
     * Post order message for user.
     */
    public function postUserMessage(AdsOrder $order, User $user, ?string $message, ?UploadedFile $attachment): AdsOrderMessage
    {
        $this->ensureUserParticipant($order, $user);
        $this->ensureChatAllowedForUser($order);

        return $this->saveMessage($order, OrderMessageActorType::User, $user->id, $message, $attachment);
    }

    /**
     * Post order message for admin.
     */
    public function postAdminMessage(AdsOrder $order, Admin $admin, ?string $message, ?UploadedFile $attachment): AdsOrderMessage
    {
        return $this->saveMessage($order, OrderMessageActorType::Admin, $admin->id, $message, $attachment);
    }

    /**
     * Expire unpaid orders and auto-dispute stale paid orders.
     */
    public function processTimeouts(): array
    {
        $expired = 0;
        $disputed = 0;

        DB::transaction(function () use (&$expired) {
            $orders = AdsOrder::where('status', OrderStatus::PendingPayment)
                ->whereNotNull('payment_deadline_at')
                ->where('payment_deadline_at', '<', now())
                ->lockForUpdate()
                ->get();

            foreach ($orders as $order) {
                $this->refundAndRestoreAd($order);
                $order->update([
                    'status' => OrderStatus::Expired,
                    'expired_at' => now(),
                    'resolved_at' => now(),
                ]);

                // Notify Seller
                $this->notifyOrder($order, 'p2p_order_expired', $order->seller);
                // Notify Buyer
                $this->notifyOrder($order, 'p2p_order_expired', $order->buyer);

                $expired++;
            }
        });

        // ── گذر خودکار به disputed ──────────────────────────────────────
        // QC-P2P-2026-09-29: مهلت داوری اکنون ذخیره می‌شود (شکاف ۱).
        // قبلاً فقط یک $deadline موقتی ساخته می‌شد و پس از disputed شدن،
        // سفارش هیچ تایمری نداشت.
        $hasDisputeDeadline = \Illuminate\Support\Facades\Schema::hasColumn('p2p_ads_orders', 'dispute_deadline');

        DB::transaction(function () use (&$disputed, $hasDisputeDeadline) {
            $orders = AdsOrder::with('ad')
                ->where('status', OrderStatus::Paid)
                ->whereNotNull('marked_paid_at')
                ->lockForUpdate()
                ->get();

            foreach ($orders as $order) {
                $graceMinutes = $this->disputeGraceMinutes();
                $deadline = $order->marked_paid_at?->copy()->addMinutes($graceMinutes);

                if ($deadline && now()->greaterThan($deadline)) {
                    $update = [
                        'status' => OrderStatus::Disputed,
                        'disputed_at' => now(),
                    ];

                    if ($hasDisputeDeadline) {
                        // مهلت رسیدگی ادمین از لحظه باز شدن اختلاف.
                        $update['dispute_deadline'] = now()->addMinutes($this->disputeGraceMinutes());
                    }

                    $order->update($update);

                    $extra = ['[[disputed_by_name]]' => __('System (Auto)')];
                    // Notify Seller
                    $this->notifyOrder($order, 'p2p_order_disputed', $order->seller, $extra);
                    // Notify Buyer
                    $this->notifyOrder($order, 'p2p_order_disputed', $order->buyer, $extra);

                    $disputed++;
                }
            }
        });

        // ── رأی خودکار اختلاف سرریزشده (QC-P2P-2026-09-29 شکاف ۲) ──────
        $autoReleased = $this->autoReleaseExpiredDisputes();

        if ($autoReleased['released'] > 0) {
            \Log::info('P2P Timeout: auto-released disputes', [
                'released' => $autoReleased['released'],
            ]);
        }

        return [
            'expired'      => $expired,
            'disputed'     => $disputed,
            'auto_released'=> $autoReleased['released'],
            'errors'       => $autoReleased['errors'],
        ];
    }

    /**
     * Find one order message for participants/admin checks.
     */
    public function findOrderMessage(AdsOrder $order, int $messageId): AdsOrderMessage
    {
        return AdsOrderMessage::where('order_id', $order->id)->where('id', $messageId)->firstOrFail();
    }

    /**
     * Ensure user is part of order.
     */
    public function ensureUserParticipant(AdsOrder $order, User $user): void
    {
        if (! in_array($user->id, [$order->buyer_user_id, $order->seller_user_id], true)) {
            throw new Exception(__('You are not authorized to access this order.'));
        }
    }

    protected function ensureChatAllowedForUser(AdsOrder $order): void
    {
        if (in_array($order->status, [OrderStatus::Completed, OrderStatus::Cancelled, OrderStatus::Expired], true)) {
            throw new Exception(__('Chat is closed for this order.'));
        }
    }

    protected function saveMessage(AdsOrder $order, OrderMessageActorType $senderType, int $senderId, ?string $message, ?UploadedFile $attachment): AdsOrderMessage
    {
        $attachmentData = [
            'attachment_path' => null,
            'attachment_name' => null,
            'attachment_mime' => null,
            'attachment_size' => null,
        ];

        if ($attachment) {

            $attachmentName = $attachment->getClientOriginalName();
            $attachmentMime = $attachment->getClientMimeType();
            $attachmentSize = (int) $attachment->getSize();
            $uploadPath = $this->imageUploadTrait($attachment, null, 'p2p/orders/chat');

            $attachmentData = [
                'attachment_path' => $uploadPath,
                'attachment_name' => $attachmentName,
                'attachment_mime' => $attachmentMime,
                'attachment_size' => $attachmentSize,
            ];
        }

        $messageRecord = AdsOrderMessage::create([
            'order_id' => $order->id,
            'sender_type' => $senderType,
            'sender_id' => $senderId,
            'message' => $message,
            ...$attachmentData,
        ]);

        // Send notification to the other participant
        $this->notifyMessage($order, $messageRecord, $senderType, $senderId);

        return $messageRecord;
    }

    /**
     * QC-P2P-2026-09-29 — شکاف ۱ و ۲: مهلت اختلاف و رأی خودکار.
     *
     * رفتار قبلی در processTimeouts():
     *   - مهلت گریس (seller_release_grace_minutes) خوانده و یک $deadline موقتی
     *     ساخته می‌شد ولی ذخیره نمی‌شد.
     *   - سفارش به disputed می‌رفت و دیگر هیچ تایمری نداشت ⇒ برای همیشه
     *     منتظر داوری ادمین می‌ماند.
     *
     * اکنون: dispute_deadline روی خود سفارش ثبت می‌شود و تایمر هر دقیقه
     * سفارش‌های اختلافیِ سرریزشده را خودکار به نفع خریدار آزاد می‌کند
     * (معادل T4 در سرویس Escrow، ولی مستقل — دو سرویس جدا هستند).
     *
     * نکته: اگر ستون dispute_deadline هنوز مایگریشن نشده باشد (مثلاً محیطی که
     * مایگریشن اجرا نشده)، تایمر با Schema::hasColumn محافظ شده و به‌جای
     * خطای 500 رد می‌شود.
     */
    protected function disputeGraceMinutes(): int
    {
        // اولویت با کلید اختصاصی اختلاف؛ اگر نبود همان کلید فروشنده استفاده
        // می‌شود تا رفتار قبلی حفظ شود.
        $specific = (int) setting('p2p_dispute_grace_minutes', 'p2p', 0);
        if ($specific > 0) {
            return $specific;
        }

        return (int) setting('seller_release_grace_minutes', 'p2p', 300);
    }

    /**
     * رأی خودکار: اختلافی که از مهلتش گذشته ⇒ آزادسازی به خریدار.
     *
     * @return array{released:int, errors:array<int,string>}
     */
    protected function autoReleaseExpiredDisputes(): array
    {
        $released = 0;
        $errors   = [];

        if (! \Illuminate\Support\Facades\Schema::hasColumn('p2p_ads_orders', 'dispute_deadline')) {
            // مایگریشن هنوز اجرا نشده — بی‌سروصدا رد می‌شویم، نه خطا.
            \Illuminate\Support\Facades\Log::warning('P2P Timeout: dispute_deadline column missing (run migrations)', [
                'hint' => 'php artisan migrate',
            ]);

            return ['released' => 0, 'errors' => $errors];
        }

        $stale = AdsOrder::where('status', OrderStatus::Disputed)
            ->whereNotNull('dispute_deadline')
            ->where('dispute_deadline', '<=', now())
            ->orderBy('dispute_deadline')
            ->limit(50)                       // جلوی قفل‌شدن جدول در یک اجرا
            ->pluck('id');

        foreach ($stale as $orderId) {
            try {
                // همان مکانیزم داوری ادمین، ولی خودکار:
                // قفل سطر، اعتبار خریدار، تسویه کارمزد، ثبت یادداشت.
                $this->resolveDispute(
                    AdsOrder::where('id', $orderId)->firstOrFail(),
                    // resolved_by_admin_id در این مسیر خالی می‌ماند (کاربر
                    // سیستمی نیست) و از طریق resolution_note قابل تشخیص است.
                    new Admin(['id' => 0, 'name' => __('System (Auto)')]),
                    'release_to_buyer',
                    __('AUTO-RULING: no admin action within the dispute window — released to buyer automatically.')
                );

                $released++;
            } catch (\Throwable $e) {
                // یک سفارش خراب نباید بقیه را متوقف کند.
                $errors[] = "AutoRelease Order #{$orderId}: " . $e->getMessage();
                \Illuminate\Support\Facades\Log::error('P2P Timeout: auto-release failed', [
                    'order_id' => $orderId,
                    'error'   => $e->getMessage(),
                ]);
            }
        }

        return ['released' => $released, 'errors' => $errors];
    }

    protected function notifyMessage(AdsOrder $order, AdsOrderMessage $messageRecord, OrderMessageActorType $senderType, int $senderId): void
    {
        $senderName = __('System');
        if ($senderType === OrderMessageActorType::User) {
            $sender = User::find($senderId);
            $senderName = $sender?->full_name ?? __('User');

            // Notify the other user
            $recipientId = ($senderId === $order->buyer_user_id) ? $order->seller_user_id : $order->buyer_user_id;
            $recipient = User::find($recipientId);
            if ($recipient) {
                $this->notifyOrder($order, 'p2p_order_message', $recipient, ['[[sender_name]]' => $senderName]);
            }
        } elseif ($senderType === OrderMessageActorType::Admin) {
            $sender = Admin::find($senderId);
            $senderName = $sender?->name ?? __('Admin');

            // Notify both participants
            $this->notifyOrder($order, 'p2p_order_message', $order->buyer, ['[[sender_name]]' => $senderName]);
            $this->notifyOrder($order, 'p2p_order_message', $order->seller, ['[[sender_name]]' => $senderName]);
        }
    }

    protected function notifyOrder(AdsOrder $order, string $templateCode, User $user, array $extraCodes = []): void
    {
        // Ensure relations are loaded for shortcodes
        $order->loadMissing(['assetCurrency', 'fiatCurrency', 'seller', 'buyer']);

        $shortcodes = [
            '[[seller_name]]' => $order->seller->full_name,
            '[[buyer_name]]' => $order->buyer->full_name,
            '[[full_name]]' => $user->full_name,
            '[[order_id]]' => $order->id,
            '[[asset_amount]]' => $order->asset_amount,
            '[[asset_symbol]]' => $order->assetCurrency->code,
            '[[fiat_amount]]' => $order->fiat_amount,
            '[[fiat_symbol]]' => $order->fiatCurrency->code,
            ...$extraCodes
        ];

        $this->sendNotify(
            email: $user->email,
            code: $templateCode,
            for: 'User',
            shortcodes: $shortcodes,
            phone: $user->phone,
            userId: $user->id,
            action: ''
        );
    }

    /**
     * P2P settlement completion (2026-09-19): اسنپ‌شات اطلاعات حساب فروشنده
     * در لحظه‌ی ثبت سفارش — شواهد تسویه برای حل اختلاف فریز می‌شود.
     * اگر فروشنده برای این متد حساب ثبت‌شده نداشته باشد null می‌ماند (رفتار قبلی).
     */
    protected function buildSellerPaymentSnapshot(User $seller, int $paymentMethodId): ?string
    {
        $account = \Addons\P2PTrading\Models\UserPaymentMethod::with('paymentMethod')
            ->where('user_id', $seller->id)
            ->where('payment_method_id', $paymentMethodId)
            ->first();

        if (! $account) {
            return null;
        }

        return json_encode([
            'user_payment_method_id' => $account->id,
            'method_name' => $account->paymentMethod?->name,
            'fields' => $account->fields,
            'captured_at' => now()->toDateTimeString(),
        ], JSON_UNESCAPED_UNICODE);
    }

    /**
     * P2P settlement completion (2026-09-19): کارمزد تیکر (order_fee_percent) قبلاً
     * از فروشنده کم می‌شد ولی به هیچ حسابی واریز نمی‌شد (از گردش پول حذف می‌شد).
     * اگر p2p_fee_recipient_user_id ست شده باشد، هنگام release کارمزد به کیف همان
     * کاربر واریز و تراکنش ثبت می‌شود؛ خالی بودن تنظیم = رفتار قبلی.
     */
    protected function settleFee(AdsOrder $order): void
    {
        $fee = (float) $order->fee_asset_amount;
        if ($fee <= self::AMOUNT_EPSILON) {
            return;
        }

        $recipientId = (int) setting('p2p_fee_recipient_user_id', 'p2p');
        if ($recipientId <= 0) {
            return;
        }

        $recipient = User::where('id', $recipientId)->lockForUpdate()->first();
        if (! $recipient) {
            return;
        }

        $wallet = $this->creditUserBalance($recipient, (int) $order->asset_currency_id, $fee);

        $this->createTransaction(
            user: $recipient,
            wallet: $wallet,
            type: TxnType::Credit,
            description: 'P2P Order Fee #' . $order->id,
            amount: $fee,
            orderId: $order->id
        );
    }

    protected function refundAndRestoreAd(AdsOrder $order, ?Admin $admin = null, ?string $note = null): ?UserWallet
    {
        $seller = User::where('id', $order->seller_user_id)->lockForUpdate()->firstOrFail();
        $sellerWallet = $this->creditUserBalance($seller, (int) $order->asset_currency_id, (float) $order->seller_locked_asset);

        $ad = Ads::where('id', $order->ads_id)->lockForUpdate()->first();
        if ($ad) {
            $ad->increment('total_amount', (float) $order->asset_amount);
            $this->updateAdStatusByLiquidity($ad->fresh());
        }

        $this->createTransaction(
            user: $seller,
            wallet: $sellerWallet,
            type: TxnType::Credit,
            description: 'P2P Order Refund #' . $order->id,
            amount: (float) $order->seller_locked_asset,
            orderId: $order->id
        );

        // 📒 ثبت در لجر: بازگشت دارایی اسکرو به فروشنده
        $this->postOrderRefundLedger($order, $seller, $sellerWallet, $admin, $note);

        return $sellerWallet;
    }

    protected function updateAdStatusByLiquidity(?Ads $ad): void
    {
        if (! $ad) {
            return;
        }

        $remainingAsset = (float) $ad->total_amount;

        if ($remainingAsset <= 0) {
            $ad->update(['status' => AdsStatus::Completed]);
            return;
        }

        $price = (float) $ad->price;
        if ($price <= 0) {
            $ad->update(['status' => AdsStatus::Inactive]);
            return;
        }

        // Liquidity is stored in asset amount, but order limits are fiat.
        $remainingFiatLiquidity = $remainingAsset * $price;

        if ($remainingFiatLiquidity + self::AMOUNT_EPSILON < (float) $ad->min_amount) {
            $ad->update(['status' => AdsStatus::Inactive]);
            return;
        }

        if ($ad->status !== AdsStatus::Active) {
            $ad->update(['status' => AdsStatus::Active]);
        }
    }

    protected function debitUserBalance(User $user, int $currencyId, float $amount): ?UserWallet
    {
        $wallet = $this->getLockedWallet($user, $currencyId);

        if ($wallet) {
            if ((float) $wallet->balance < $amount) {
                throw new Exception(__('Insufficient wallet balance for :currency.', ['currency' => $wallet->currency->name]));
            }
            $wallet->decrement('balance', $amount);
            return $wallet->refresh();
        }

        $user = User::where('id', $user->id)->lockForUpdate()->firstOrFail();
        if ((float) $user->balance < $amount) {
            throw new Exception(__('Insufficient balance.'));
        }
        $user->decrement('balance', $amount);

        return null;
    }

    protected function creditUserBalance(User $user, int $currencyId, float $amount): ?UserWallet
    {
        $wallet = $this->getLockedWallet($user, $currencyId);

        if ($wallet) {
            $wallet->increment('balance', $amount);
            return $wallet->refresh();
        }

        $user = User::where('id', $user->id)->lockForUpdate()->firstOrFail();
        $user->increment('balance', $amount);

        return null;
    }

    protected function getLockedWallet(User $user, int $currencyId): ?UserWallet
    {
        if (! setting('multiple_currency', 'permission')) {
            return null;
        }

        $wallet = UserWallet::where('user_id', $user->id)
            ->where('currency_id', $currencyId)
            ->lockForUpdate()
            ->first();

        // v55: اگر wallet موجود نبود، یکی بساز (به‌جای پرتاب exception)
        if (! $wallet) {
            $wallet = UserWallet::create([
                'user_id' => $user->id,
                'currency_id' => $currencyId,
                'balance' => 0,
            ]);
            // refresh با lockForUpdate
            $wallet = UserWallet::where('id', $wallet->id)->lockForUpdate()->first();
        }

        return $wallet;
    }

    protected function createTransaction(User $user, ?UserWallet $wallet, TxnType $type, string $description, float $amount, int $orderId): void
    {
        Transaction::create([
            'user_id' => $user->id,
            'description' => $description,
            'type' => $type,
            'status' => TxnStatus::Success,
            'amount' => $amount,
            'charge' => 0,
            'final_amount' => $amount,
            'wallet_type' => $wallet ? $wallet->id : 'default',
            'method' => 'P2P Order',
            'target_type' => 'p2p_order',
            'target_id' => $orderId,
        ]);
    }

    protected function normalizeAmountInput(mixed $value): ?float
    {
        if ($value === null) {
            return null;
        }

        $normalized = str_replace(',', '', trim((string) $value));
        if ($normalized === '' || ! is_numeric($normalized)) {
            return null;
        }

        return (float) $normalized;
    }

    /**
     * 📒 ثبت در لجر: قفل دارایی فروشنده در حساب اسکرو P2P پلتفرم
     * بدهکار: حساب بدهی فروشنده (کاهش تعهد پلتفرم به فروشنده)
     * بستانکار: حساب تعهد اسکرو P2P (افزایش موجودی امانی در اسکرو)
     */
    protected function postOrderEscrowLockLedger(AdsOrder $order, User $seller, ?UserWallet $sellerWallet, float $lockedAmount): void
    {
        try {
            if (! class_exists(\App\Services\Core\LedgerService::class)) {
                return;
            }

            $order->loadMissing('assetCurrency');
            $currencyCode = $order->assetCurrency?->code
                ?? \App\Models\Currency::find($order->asset_currency_id)?->code
                ?? setting('site_currency', 'global');

            $amountStr = sprintf('%.8F', $lockedAmount);
            if (bccomp($amountStr, '0', 8) <= 0) {
                return;
            }

            $sellerAccount = $sellerWallet
                ? "liability:user_wallet:{$sellerWallet->id}"
                : "liability:user_default:{$seller->id}";
            $escrowAccount = "liability:p2p_escrow:{$currencyCode}";

            $entries = [
                ['account' => $sellerAccount, 'direction' => 'debit', 'amount' => $amountStr],
                ['account' => $escrowAccount, 'direction' => 'credit', 'amount' => $amountStr],
            ];

            $ledger = app(\App\Services\Core\LedgerService::class);
            $ledger->post('p2p_order_lock', $entries, $currencyCode, [
                'reference' => 'P2P-ORD-' . $order->id,
                'description' => "P2P Order Escrow Lock #{$order->id}",
                'created_by' => $seller->id,
                'meta' => [
                    'order_id' => $order->id,
                    'seller_id' => $seller->id,
                    'amount' => $amountStr,
                    'currency' => $currencyCode,
                ],
            ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('p2p.ledger_lock_failed', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * 📒 ثبت در لجر: آزادسازی دارایی اسکرو به خریدار (و تسویه کارمزد)
     * بدهکار: حساب تعهد اسکرو P2P (کاهش تعهد اسکرو)
     * بستانکار: حساب بدهی خریدار (افزایش موجودی خریدار)
     * بستانکار: حساب کارمزد (کاربر کارمزدگیرنده یا درآمد کارمزد P2P)
     */
    protected function postOrderReleaseLedger(AdsOrder $order, User $buyer, ?UserWallet $buyerWallet, ?Admin $admin = null, ?string $note = null): void
    {
        try {
            if (! class_exists(\App\Services\Core\LedgerService::class)) {
                return;
            }

            $order->loadMissing('assetCurrency');
            $currencyCode = $order->assetCurrency?->code
                ?? \App\Models\Currency::find($order->asset_currency_id)?->code
                ?? setting('site_currency', 'global');

            $assetAmount = sprintf('%.8F', (float) $order->asset_amount);
            $feeAmount = sprintf('%.8F', (float) $order->fee_asset_amount);
            // محاسبه دقیق مجموع قفل‌شده با bcmath برای توازن قطعی لجر
            $totalLocked = bcadd($assetAmount, $feeAmount, 8);

            if (bccomp($totalLocked, '0', 8) <= 0) {
                return;
            }

            $escrowAccount = "liability:p2p_escrow:{$currencyCode}";
            $buyerAccount = $buyerWallet
                ? "liability:user_wallet:{$buyerWallet->id}"
                : "liability:user_default:{$buyer->id}";

            $entries = [
                ['account' => $escrowAccount, 'direction' => 'debit', 'amount' => $totalLocked],
                ['account' => $buyerAccount, 'direction' => 'credit', 'amount' => $assetAmount],
            ];

            if (bccomp($feeAmount, '0', 8) > 0) {
                $recipientId = (int) setting('p2p_fee_recipient_user_id', 'p2p');
                $recipient = $recipientId > 0 ? User::find($recipientId) : null;

                if ($recipient) {
                    $recipientWallet = $this->getLockedWallet($recipient, (int) $order->asset_currency_id);
                    $feeAccount = $recipientWallet
                        ? "liability:user_wallet:{$recipientWallet->id}"
                        : "liability:user_default:{$recipient->id}";
                } else {
                    $feeAccount = "revenue:fee:p2p:{$currencyCode}";
                }

                $entries[] = ['account' => $feeAccount, 'direction' => 'credit', 'amount' => $feeAmount];
            }

            $eventType = $admin ? 'p2p_dispute_release' : 'p2p_order_release';
            $adminId = ($admin && $admin->id > 0) ? $admin->id : 0;
            $actorLabel = $admin ? "Admin #{$adminId}" : 'Seller';
            $description = "P2P Order Release #{$order->id} to Buyer #{$buyer->id} by {$actorLabel}";

            $ledger = app(\App\Services\Core\LedgerService::class);
            $ledger->post($eventType, $entries, $currencyCode, [
                'reference' => 'P2P-ORD-' . $order->id,
                'description' => $description,
                'created_by' => $admin ? $adminId : $order->seller_user_id,
                'meta' => [
                    'order_id' => $order->id,
                    'action' => $admin ? 'admin_dispute_release' : 'seller_release',
                    'buyer_id' => $buyer->id,
                    'seller_id' => $order->seller_user_id,
                    'admin_id' => $adminId ?: null,
                    'note' => $note,
                    'asset_amount' => $assetAmount,
                    'fee_amount' => $feeAmount,
                    'currency' => $currencyCode,
                ],
            ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('p2p.ledger_release_failed', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * 📒 ثبت در لجر: بازگشت دارایی اسکرو به فروشنده
     * بدهکار: حساب تعهد اسکرو P2P (کاهش تعهد اسکرو)
     * بستانکار: حساب بدهی فروشنده (افزایش مجدد موجودی فروشنده)
     */
    protected function postOrderRefundLedger(AdsOrder $order, User $seller, ?UserWallet $sellerWallet, ?Admin $admin = null, ?string $note = null): void
    {
        try {
            if (! class_exists(\App\Services\Core\LedgerService::class)) {
                return;
            }

            $order->loadMissing('assetCurrency');
            $currencyCode = $order->assetCurrency?->code
                ?? \App\Models\Currency::find($order->asset_currency_id)?->code
                ?? setting('site_currency', 'global');

            $refundAmount = sprintf('%.8F', (float) $order->seller_locked_asset);
            if (bccomp($refundAmount, '0', 8) <= 0) {
                return;
            }

            $escrowAccount = "liability:p2p_escrow:{$currencyCode}";
            $sellerAccount = $sellerWallet
                ? "liability:user_wallet:{$sellerWallet->id}"
                : "liability:user_default:{$seller->id}";

            $entries = [
                ['account' => $escrowAccount, 'direction' => 'debit', 'amount' => $refundAmount],
                ['account' => $sellerAccount, 'direction' => 'credit', 'amount' => $refundAmount],
            ];

            $eventType = $admin ? 'p2p_dispute_refund' : 'p2p_order_refund';
            $adminId = ($admin && $admin->id > 0) ? $admin->id : 0;
            $actorLabel = $admin ? "Admin #{$adminId}" : 'System/Cancellation';
            $description = "P2P Order Refund #{$order->id} to Seller #{$seller->id} by {$actorLabel}";

            $ledger = app(\App\Services\Core\LedgerService::class);
            $ledger->post($eventType, $entries, $currencyCode, [
                'reference' => 'P2P-ORD-' . $order->id,
                'description' => $description,
                'created_by' => $admin ? $adminId : $seller->id,
                'meta' => [
                    'order_id' => $order->id,
                    'action' => $admin ? 'admin_dispute_refund' : 'order_cancellation_refund',
                    'seller_id' => $seller->id,
                    'admin_id' => $adminId ?: null,
                    'note' => $note,
                    'amount' => $refundAmount,
                    'currency' => $currencyCode,
                ],
            ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('p2p.ledger_refund_failed', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
