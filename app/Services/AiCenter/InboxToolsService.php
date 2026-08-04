<?php

namespace App\Services\AiCenter;

use App\DataTransferObjects\ParsedGhlContactData;
use App\Enums\AiCenter\AiCenterLogSource;
use App\Enums\AiCenter\AiCenterLogStatus;
use App\Models\AiCenter\AiLog;
use App\Models\Conversation;
use App\Services\AI\Contracts\AiClientInterface;
use App\Services\AiCenter\Engines\IntentDetectionEngine;
use App\Services\AiCenter\Engines\SopMatchingEngine;
use App\Services\AiCenter\Support\ConversationThreadFormatter;
use App\Services\AiCenter\Support\InboxToolPromptFactory;
use App\Services\Ghl\GhlParserService;
use App\Services\Ghl\GhlThreadLoader;
use App\Services\Ghl\GoHighLevelApiService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class InboxToolsService
{
    protected const CACHE_TTL_MINUTES = 60;

    protected const EXTRACT_INFO_CACHE_TTL_MINUTES = 5;

    protected const LANGUAGES = [
        'en' => 'English',
        'id' => 'Indonesian',
        'ja' => 'Japanese',
        'zh' => 'Chinese',
        'es' => 'Spanish',
        'fr' => 'French',
        'de' => 'German',
    ];

    public function __construct(
        protected AiClientInterface $aiClient,
        protected ConversationThreadFormatter $threadFormatter,
        protected InboxToolPromptFactory $promptFactory,
        protected IntentDetectionEngine $intentDetectionEngine,
        protected SopMatchingEngine $sopMatchingEngine,
        protected KnowledgeResolver $knowledgeResolver,
        protected GhlThreadLoader $ghlThreadLoader,
        protected GoHighLevelApiService $ghlApi,
        protected GhlParserService $ghlParser,
    ) {
    }

    /*
    |--------------------------------------------------------------------------
    | SUMMARIZE
    |--------------------------------------------------------------------------
    */

    public function summarize(
        Conversation $conversation,
        bool $forceRefresh = false
    ): array {
        return $this->remember(
            'summarize',
            $conversation,
            $forceRefresh,
            function (string $thread) use ($conversation): array {
                $result = $this->aiClient->json(
                    $this->promptFactory->summarize($thread)
                );

                $this->logCall(
                    $conversation,
                    $thread,
                    $result
                );

                return $result;
            }
        );
    }

    /*
    |--------------------------------------------------------------------------
    | TRANSLATE
    |--------------------------------------------------------------------------
    */

    public function translate(
        Conversation $conversation,
        string $language,
        bool $forceRefresh = false
    ): array {
        $language = strtolower(trim($language));

        $languageLabel = self::LANGUAGES[$language]
            ?? $language;

        return $this->remember(
            "translate:{$language}",
            $conversation,
            $forceRefresh,
            function (string $thread) use (
                $conversation,
                $languageLabel
            ): array {
                $result = $this->aiClient->json(
                    $this->promptFactory->translate(
                        $thread,
                        $languageLabel
                    )
                );

                $this->logCall(
                    $conversation,
                    $thread,
                    $result
                );

                return $result;
            }
        );
    }

    /*
    |--------------------------------------------------------------------------
    | DETECT INTENT
    |--------------------------------------------------------------------------
    */

    public function detectIntent(
        Conversation $conversation,
        bool $forceRefresh = false
    ): array {
        return $this->remember(
            'detect-intent',
            $conversation,
            $forceRefresh,
            function (string $thread) use ($conversation): array {
                $knownIntentNames = $this
                    ->intentDetectionEngine
                    ->shortlist($thread)
                    ->pluck('name')
                    ->filter()
                    ->values()
                    ->all();

                $classification = $this->aiClient->json(
                    $this->promptFactory->detectIntent(
                        $thread,
                        $knownIntentNames
                    )
                );

                $intent = $this->intentDetectionEngine->resolve(
                    $thread,
                    $classification
                );

                $sopMatch = $this->sopMatchingEngine->match(
                    $conversation,
                    $intent,
                    $thread
                );

                $sop = $sopMatch->sop;

                $knowledgeBases = $this->knowledgeResolver->resolve(
                    $sop
                );

                $result = [
                    'intent' => $classification['intent']
                        ?? $intent?->name,

                    'confidence_score' =>
                        $classification['confidence_score']
                        ?? null,

                    'reasoning' =>
                        $classification['reasoning']
                        ?? '',

                    'matched_sop' =>
                        $sop?->name,

                    'matched_knowledge' =>
                        $knowledgeBases
                            ->pluck('title')
                            ->filter()
                            ->values()
                            ->all(),

                    'matched_template' =>
                        $sop?->replyTemplate?->name,
                ];

                $this->logCall(
                    $conversation,
                    $thread,
                    $result,
                    intentId: $intent?->id,
                    sopId: $sop?->id
                );

                return $result;
            }
        );
    }

    /*
    |--------------------------------------------------------------------------
    | EXTRACT INFORMATION
    |--------------------------------------------------------------------------
    |
    | IMPORTANT:
    | This method DOES NOT use AI.
    |
    | Data comes directly from:
    | 1. GHL Contact
    | 2. GHL Orders
    | 3. GHL Transactions
    |
    */

    public function extractInformation(
        Conversation $conversation,
        bool $forceRefresh = false
    ): array {
        $cacheKey = 'inbox-extract-info:' . $conversation->id;

        if (
            ! $forceRefresh
            && Cache::has($cacheKey)
        ) {
            return Cache::get($cacheKey);
        }

        $contact = $this->fetchContact(
            $conversation->contact_id
        );

        $purchaseInfo = $this->extractPurchaseInfo(
            $contact?->customFields ?? []
        );

        /*
        |--------------------------------------------------------------------------
        | Get ALL PAYMENT DATA
        |--------------------------------------------------------------------------
        */

        $payments = $this->getAllPaymentDetails(
            $conversation->contact_id
        );

        /*
        |--------------------------------------------------------------------------
        | Backward-compatible single purchase summary
        |--------------------------------------------------------------------------
        |
        | IMPORTANT:
        | The original code called fillPurchaseInfoFromPayments(),
        | but that method did not exist.
        |
        | The actual method implemented in this service is:
        | fillLegacyPurchaseFields()
        |
        */

        $purchaseInfo = $this->fillLegacyPurchaseFields(
            $purchaseInfo,
            $payments
        );

        $result = [
            'customer_name' =>
                $contact?->fullName()
                ?: $conversation->contact_name,

            'email' =>
                $contact?->email
                ?: $conversation->contact_email,

            'phone' =>
                $contact?->phone
                ?: $conversation->contact_phone,

            'contact_id' =>
                $conversation->contact_id,

            'conversation_id' =>
                $conversation->ghl_conversation_id,

            'channel' =>
                $conversation->channel,

            'company_name' =>
                $contact?->companyName,

            /*
            |--------------------------------------------------------------------------
            | Backward-compatible single purchase summary
            |--------------------------------------------------------------------------
            */

            'product' =>
                $purchaseInfo['product'],

            'purchase_date' =>
                $purchaseInfo['purchase_date'],

            'purchase_price' =>
                $purchaseInfo['purchase_price'],

            'receipt_number' =>
                $purchaseInfo['receipt_number'],

            /*
            |--------------------------------------------------------------------------
            | ALL PAYMENTS
            |--------------------------------------------------------------------------
            */

            'payments' =>
                $payments,

            'payment_count' =>
                count($payments),

            'tags' =>
                $contact?->tags ?? [],

            'custom_fields' =>
                $purchaseInfo['remaining_custom_fields'],
        ];

        Log::debug(
            'Extract Info payment resolution',
            [
                'conversation_id' =>
                    $conversation->id,

                'contact_id' =>
                    $conversation->contact_id,

                'total_payments' =>
                    count($payments),

                'product_found' =>
                    filled($purchaseInfo['product']),

                'purchase_date_found' =>
                    filled($purchaseInfo['purchase_date']),

                'purchase_price_found' =>
                    filled($purchaseInfo['purchase_price']),

                'receipt_number_found' =>
                    filled($purchaseInfo['receipt_number']),
            ]
        );

        Cache::put(
            $cacheKey,
            $result,
            now()->addMinutes(
                self::EXTRACT_INFO_CACHE_TTL_MINUTES
            )
        );

        return $result;
    }

    /*
    |--------------------------------------------------------------------------
    | GET ALL PAYMENT DETAILS
    |--------------------------------------------------------------------------
    */

    protected function getAllPaymentDetails(
        ?string $contactId
    ): array {
        if (blank($contactId)) {
            return [];
        }

        $payments = [];

        /*
        |--------------------------------------------------------------------------
        | ORDERS
        |--------------------------------------------------------------------------
        */

        try {
            $response = $this->ghlApi->getOrders(
                $contactId
            );

            $orders = $this->allRecords(
                $response,
                [
                    'orders',
                    'data',
                ]
            );

            foreach ($orders as $order) {
                $normalized = $this->normalizeOrder(
                    $order,
                    $contactId
                );

                if (
                    $this->hasMeaningfulPaymentData(
                        $normalized
                    )
                ) {
                    $payments[] = $normalized;
                }
            }
        } catch (Throwable $e) {
            Log::warning(
                'Failed to fetch GHL orders',
                [
                    'contact_id' =>
                        $contactId,

                    'error' =>
                        $e->getMessage(),
                ]
            );
        }

        /*
        |--------------------------------------------------------------------------
        | TRANSACTIONS
        |--------------------------------------------------------------------------
        */

        try {
            $response = $this->ghlApi->getTransactions(
                $contactId
            );

            $transactions = $this->allRecords(
                $response,
                [
                    'transactions',
                    'data',
                ]
            );

            foreach ($transactions as $transaction) {
                $normalized = $this->normalizeTransaction(
                    $transaction,
                    $contactId
                );

                if (
                    $this->hasMeaningfulPaymentData(
                        $normalized
                    )
                ) {
                    $payments[] = $normalized;
                }
            }
        } catch (Throwable $e) {
            Log::warning(
                'Failed to fetch GHL transactions',
                [
                    'contact_id' =>
                        $contactId,

                    'error' =>
                        $e->getMessage(),
                ]
            );
        }

        /*
        |--------------------------------------------------------------------------
        | REMOVE DUPLICATES
        |--------------------------------------------------------------------------
        */

        $payments = $this->removeDuplicatePayments(
            $payments
        );

        /*
        |--------------------------------------------------------------------------
        | NEWEST FIRST
        |--------------------------------------------------------------------------
        */

        usort(
            $payments,
            function (array $a, array $b): int {
                return strcmp(
                    (string) ($b['created_at'] ?? ''),
                    (string) ($a['created_at'] ?? '')
                );
            }
        );

        return array_values($payments);
    }

    /*
    |--------------------------------------------------------------------------
    | NORMALIZE ORDER
    |--------------------------------------------------------------------------
    */

    protected function normalizeOrder(
        array $order,
        string $contactId
    ): array {
        $items = $order['items'] ?? [];

        if (! is_array($items)) {
            $items = [];
        }

        $normalizedItems = [];

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $name =
                $item['name']
                ?? data_get($item, 'product.name')
                ?? $item['displayText']
                ?? null;

            $quantity =
                $item['qty']
                ?? data_get($item, 'quantity.max')
                ?? $item['quantity']
                ?? 1;

            $priceAmount =
                data_get($item, 'price.amount')
                ?? $item['price']
                ?? null;

            $priceCurrency =
                data_get($item, 'price.currency')
                ?? $item['currency']
                ?? null;

            $normalizedItems[] = [
                'name' =>
                    $name,

                'quantity' =>
                    $quantity,

                'price' =>
                    $priceAmount,

                'formatted_price' =>
                    $this->formatPrice(
                        $priceAmount,
                        $priceCurrency
                    ),

                'currency' =>
                    $priceCurrency,

                'product_id' =>
                    data_get(
                        $item,
                        'product._id'
                    )
                    ?? data_get(
                        $item,
                        'product.id'
                    ),

                'price_id' =>
                    data_get(
                        $item,
                        'price._id'
                    )
                    ?? data_get(
                        $item,
                        'price.id'
                    ),
            ];
        }

        $products = collect($normalizedItems)
            ->pluck('name')
            ->filter(
                fn ($value) => filled($value)
            )
            ->unique()
            ->values()
            ->all();

        $amount =
            $order['amount']
            ?? data_get(
                $order,
                'amountSummary.total'
            )
            ?? null;

        $currency =
            $order['currency']
            ?? null;

        return [
            'type' =>
                'order',

            'order_id' =>
                $order['_id']
                ?? $order['id']
                ?? null,

            'contact_id' =>
                $order['contactId']
                ?? $contactId,

            'contact_name' =>
                $order['contactName']
                ?? null,

            'contact_email' =>
                $order['contactEmail']
                ?? null,

            /*
            |--------------------------------------------------------------------------
            | PAYMENT
            |--------------------------------------------------------------------------
            */

            'amount' =>
                $amount,

            'formatted_amount' =>
                $this->formatPrice(
                    $amount,
                    $currency
                ),

            'currency' =>
                $currency,

            'subtotal' =>
                data_get(
                    $order,
                    'amountSummary.subtotal'
                )
                ?? $order['subtotal']
                ?? null,

            'discount' =>
                data_get(
                    $order,
                    'amountSummary.discount'
                )
                ?? $order['discount']
                ?? null,

            'tax' =>
                data_get(
                    $order,
                    'amountSummary.tax'
                )
                ?? $order['tax']
                ?? null,

            'shipping' =>
                data_get(
                    $order,
                    'amountSummary.shipping'
                )
                ?? $order['shipping']
                ?? null,

            /*
            |--------------------------------------------------------------------------
            | STATUS
            |--------------------------------------------------------------------------
            */

            'status' =>
                $order['status']
                ?? null,

            'payment_status' =>
                $order['paymentStatus']
                ?? null,

            'fulfillment_status' =>
                $order['fulfillmentStatus']
                ?? null,

            'live_mode' =>
                $order['liveMode']
                ?? null,

            /*
            |--------------------------------------------------------------------------
            | PRODUCTS
            |--------------------------------------------------------------------------
            */

            'product' =>
                ! empty($products)
                    ? implode(', ', $products)
                    : null,

            'products' =>
                $products,

            'items' =>
                $normalizedItems,

            /*
            |--------------------------------------------------------------------------
            | SOURCE
            |--------------------------------------------------------------------------
            */

            'source' => [
                'type' =>
                    data_get(
                        $order,
                        'source.type'
                    )
                    ?? $order['sourceType']
                    ?? null,

                'sub_type' =>
                    data_get(
                        $order,
                        'source.subType'
                    )
                    ?? $order['sourceSubType']
                    ?? null,

                'id' =>
                    data_get(
                        $order,
                        'source.id'
                    )
                    ?? $order['sourceId']
                    ?? null,

                'name' =>
                    data_get(
                        $order,
                        'source.name'
                    )
                    ?? $order['sourceName']
                    ?? null,

                'meta' =>
                    data_get(
                        $order,
                        'source.meta'
                    )
                    ?? $order['sourceMeta']
                    ?? [],
            ],

            /*
            |--------------------------------------------------------------------------
            | DATE
            |--------------------------------------------------------------------------
            */

            'purchase_date' =>
                $this->formatPurchaseDate(
                    $order['createdAt']
                    ?? $order['created_at']
                    ?? null
                ),

            'created_at' =>
                $order['createdAt']
                ?? $order['created_at']
                ?? null,

            'updated_at' =>
                $order['updatedAt']
                ?? $order['updated_at']
                ?? null,

            /*
            |--------------------------------------------------------------------------
            | RECEIPT
            |--------------------------------------------------------------------------
            */

            'receipt_number' =>
                $this->formatReceiptNumber(
                    $this->findReceiptNumber(
                        $order
                    )
                ),

            /*
            |--------------------------------------------------------------------------
            | RAW DATA
            |--------------------------------------------------------------------------
            */

            'raw_order' =>
                $order,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | NORMALIZE TRANSACTION
    |--------------------------------------------------------------------------
    */

    protected function normalizeTransaction(
        array $transaction,
        string $contactId
    ): array {
        $chargeSnapshot =
            is_array(
                $transaction['chargeSnapshot'] ?? null
            )
                ? $transaction['chargeSnapshot']
                : null;

        $amount =
            $transaction['amount']
            ?? null;

        $currency =
            $transaction['currency']
            ?? null;

        $product =
            $transaction['productName']
            ?? $transaction['description']
            ?? data_get(
                $transaction,
                'chargeSnapshot.description'
            )
            ?? data_get(
                $transaction,
                'chargeSnapshot.charges.data.0.description'
            )
            ?? null;

        return [
            'type' =>
                'transaction',

            'transaction_id' =>
                $transaction['_id']
                ?? $transaction['id']
                ?? null,

            'entity_type' =>
                $transaction['entityType']
                ?? null,

            'entity_id' =>
                $transaction['entityId']
                ?? null,

            'contact_id' =>
                $transaction['contactId']
                ?? $contactId,

            'contact_name' =>
                $transaction['contactName']
                ?? null,

            'contact_email' =>
                $transaction['contactEmail']
                ?? null,

            /*
            |--------------------------------------------------------------------------
            | PAYMENT
            |--------------------------------------------------------------------------
            */

            'amount' =>
                $amount,

            'formatted_amount' =>
                $this->formatPrice(
                    $amount,
                    $currency
                ),

            'currency' =>
                $currency,

            'amount_refunded' =>
                $transaction['amountRefunded']
                ?? null,

            /*
            |--------------------------------------------------------------------------
            | STATUS
            |--------------------------------------------------------------------------
            */

            'status' =>
                $transaction['status']
                ?? null,

            'payment_status' =>
                $transaction['paymentStatus']
                ?? null,

            /*
            |--------------------------------------------------------------------------
            | PAYMENT METHOD
            |--------------------------------------------------------------------------
            */

            'payment_provider_type' =>
                $transaction['paymentProviderType']
                ?? null,

            'payment_method' =>
                $transaction['paymentMethod']
                ?? null,

            /*
            |--------------------------------------------------------------------------
            | PRODUCT
            |--------------------------------------------------------------------------
            */

            'product' =>
                $product,

            'products' =>
                filled($product)
                    ? [$product]
                    : [],

            'items' =>
                [],

            /*
            |--------------------------------------------------------------------------
            | SOURCE
            |--------------------------------------------------------------------------
            */

            'source' => [
                'type' =>
                    $transaction['entitySourceType']
                    ?? null,

                'sub_type' =>
                    $transaction['entitySourceSubType']
                    ?? null,

                'name' =>
                    $transaction['entitySourceName']
                    ?? null,

                'id' =>
                    $transaction['entitySourceId']
                    ?? null,

                'meta' =>
                    $transaction['entitySourceMeta']
                    ?? [],
            ],

            /*
            |--------------------------------------------------------------------------
            | DATE
            |--------------------------------------------------------------------------
            */

            'purchase_date' =>
                $this->formatPurchaseDate(
                    $transaction['createdAt']
                    ?? $transaction['created_at']
                    ?? null
                ),

            'created_at' =>
                $transaction['createdAt']
                ?? $transaction['created_at']
                ?? null,

            'updated_at' =>
                $transaction['updatedAt']
                ?? $transaction['updated_at']
                ?? null,

            /*
            |--------------------------------------------------------------------------
            | RECEIPT
            |--------------------------------------------------------------------------
            */

            'receipt_number' =>
                $this->formatReceiptNumber(
                    $this->findReceiptNumber(
                        $transaction
                    )
                    ?? $this->findReceiptNumber(
                        $chargeSnapshot ?? []
                    )
                ),

            /*
            |--------------------------------------------------------------------------
            | GHL PAYMENT DATA
            |--------------------------------------------------------------------------
            */

            'charge_snapshot' =>
                $chargeSnapshot,

            'payment_providers' =>
                $transaction['paymentProviders']
                ?? [],

            /*
            |--------------------------------------------------------------------------
            | RAW DATA
            |--------------------------------------------------------------------------
            */

            'raw_transaction' =>
                $transaction,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | REMOVE DUPLICATE PAYMENTS
    |--------------------------------------------------------------------------
    */

    protected function removeDuplicatePayments(
        array $payments
    ): array {
        $seen = [];

        return array_values(
            array_filter(
                $payments,
                function (array $payment) use (&$seen): bool {
                    $id =
                        $payment['order_id']
                        ?? $payment['transaction_id']
                        ?? null;

                    /*
                    | If there is no ID, don't accidentally remove
                    | unrelated records.
                    */

                    if (blank($id)) {
                        return true;
                    }

                    $key =
                        ($payment['type'] ?? 'payment')
                        . ':'
                        . $id;

                    if (isset($seen[$key])) {
                        return false;
                    }

                    $seen[$key] = true;

                    return true;
                }
            )
        );
    }

    /*
    |--------------------------------------------------------------------------
    | PAYMENT DATA CHECK
    |--------------------------------------------------------------------------
    */

    protected function hasMeaningfulPaymentData(
        array $payment
    ): bool {
        return filled(
            $payment['order_id']
            ?? $payment['transaction_id']
            ?? $payment['entity_id']
            ?? $payment['amount']
            ?? $payment['product']
            ?? $payment['created_at']
        );
    }

    /*
    |--------------------------------------------------------------------------
    | EXTRACT PURCHASE INFO FROM CUSTOM FIELDS
    |--------------------------------------------------------------------------
    */

    protected function extractPurchaseInfo(
        array $customFields
    ): array {
        $aliasesByTarget = [
            'product' => [
                'product',
                'productname',
                'productpurchased',
                'itempurchased',
                'item',
                'productbought',
            ],

            'purchase_date' => [
                'purchasedate',
                'dateofpurchase',
                'orderdate',
                'datepurchased',
                'transactiondate',
            ],

            'purchase_price' => [
                'purchaseprice',
                'price',
                'amount',
                'orderamount',
                'ordertotal',
                'transactionamount',
                'total',
                'paymentamount',
            ],

            'receipt_number' => [
                'receiptnumber',
                'receiptno',
                'receipt',
                'invoicenumber',
                'invoiceno',
                'ordernumber',
                'orderid',
                'transactionid',
            ],
        ];

        $values = [
            'product' => null,
            'purchase_date' => null,
            'purchase_price' => null,
            'receipt_number' => null,
        ];

        $matchedFieldIds = [];

        foreach ($customFields as $field) {
            if (! is_array($field)) {
                continue;
            }

            $normalizedKey = preg_replace(
                '/[^a-z0-9]/',
                '',
                strtolower(
                    (string) (
                        $field['key']
                        ?? $field['name']
                        ?? ''
                    )
                )
            );

            if ($normalizedKey === '') {
                continue;
            }

            foreach (
                $aliasesByTarget as $target => $aliases
            ) {
                if (
                    $values[$target] !== null
                    || ! in_array(
                        $normalizedKey,
                        $aliases,
                        true
                    )
                ) {
                    continue;
                }

                $value =
                    $field['value']
                    ?? null;

                if (is_array($value)) {
                    $value = implode(
                        ', ',
                        array_map(
                            static fn ($item) =>
                                is_scalar($item)
                                    ? (string) $item
                                    : json_encode($item),
                            $value
                        )
                    );
                }

                $values[$target] =
                    filled($value)
                        ? (string) $value
                        : null;

                $matchedFieldIds[] =
                    $field['id']
                    ?? $field['key']
                    ?? $field['name']
                    ?? null;

                break;
            }
        }

        $remaining = collect($customFields)
            ->reject(
                function ($field) use ($matchedFieldIds): bool {
                    if (! is_array($field)) {
                        return true;
                    }

                    $identifier =
                        $field['id']
                        ?? $field['key']
                        ?? $field['name']
                        ?? null;

                    return in_array(
                        $identifier,
                        $matchedFieldIds,
                        true
                    );
                }
            )
            ->values()
            ->all();

        return [
            'product' =>
                $values['product'],

            'purchase_date' =>
                $this->formatPurchaseDate(
                    $values['purchase_date']
                ),

            'purchase_price' =>
                $values['purchase_price'],

            'receipt_number' =>
                $this->formatReceiptNumber(
                    $values['receipt_number']
                ),

            'remaining_custom_fields' =>
                $remaining,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | FILL LEGACY PURCHASE FIELDS
    |--------------------------------------------------------------------------
    */

    protected function fillLegacyPurchaseFields(
        array $purchaseInfo,
        array $payments
    ): array {
        if ($payments === []) {
            return $purchaseInfo;
        }

        /*
        |--------------------------------------------------------------------------
        | Prefer successful / paid payment
        |--------------------------------------------------------------------------
        */

        $payment = collect($payments)
            ->sortByDesc(
                fn (array $payment) =>
                    (string) (
                        $payment['created_at']
                        ?? ''
                    )
            )
            ->first(
                function (array $payment): bool {
                    $status = strtolower(
                        trim(
                            (string) (
                                $payment['payment_status']
                                ?? $payment['status']
                                ?? ''
                            )
                        )
                    );

                    return in_array(
                        $status,
                        [
                            'paid',
                            'completed',
                            'succeeded',
                            'success',
                        ],
                        true
                    );
                }
            );

        /*
        |--------------------------------------------------------------------------
        | Otherwise newest payment
        |--------------------------------------------------------------------------
        */

        $payment ??= $payments[0];

        /*
        |--------------------------------------------------------------------------
        | Product
        |--------------------------------------------------------------------------
        */

        if (
            blank($purchaseInfo['product'])
            && filled($payment['product'] ?? null)
        ) {
            $purchaseInfo['product'] =
                $payment['product'];
        }

        /*
        |--------------------------------------------------------------------------
        | Date
        |--------------------------------------------------------------------------
        */

        if (
            blank($purchaseInfo['purchase_date'])
        ) {
            $purchaseInfo['purchase_date'] =
                $payment['purchase_date']
                ?? null;
        }

        /*
        |--------------------------------------------------------------------------
        | Price
        |--------------------------------------------------------------------------
        */

        if (
            blank($purchaseInfo['purchase_price'])
        ) {
            $purchaseInfo['purchase_price'] =
                $payment['formatted_amount']
                ?? null;
        }

        /*
        |--------------------------------------------------------------------------
        | Receipt
        |--------------------------------------------------------------------------
        */

        if (
            blank($purchaseInfo['receipt_number'])
        ) {
            $purchaseInfo['receipt_number'] =
                $payment['receipt_number']
                ?? null;
        }

        return $purchaseInfo;
    }

    /*
    |--------------------------------------------------------------------------
    | ALL RECORDS
    |--------------------------------------------------------------------------
    */

    protected function allRecords(
        array $response,
        array $listKeys
    ): array {
        foreach ($listKeys as $listKey) {
            $list = $response[$listKey] ?? null;

            if (! is_array($list)) {
                continue;
            }

            if (array_is_list($list)) {
                return array_values(
                    array_filter(
                        $list,
                        fn ($item) =>
                            is_array($item)
                    )
                );
            }

            $records = [];

            foreach ($list as $item) {
                if (is_array($item)) {
                    $records[] = $item;
                }
            }

            if ($records !== []) {
                return $records;
            }
        }

        if (array_is_list($response)) {
            return array_values(
                array_filter(
                    $response,
                    fn ($item) =>
                        is_array($item)
                )
            );
        }

        return [];
    }

    /*
    |--------------------------------------------------------------------------
    | RECEIPT NUMBER
    |--------------------------------------------------------------------------
    */

    protected function findReceiptNumber(
        array $source
    ): ?string {
        $keys = [
            'receiptNumber',
            'receipt_number',
            'receiptNo',
            'receipt_no',
            'invoiceNumber',
            'invoice_number',
            'invoiceNo',
            'invoice_no',
            'orderNumber',
            'order_number',
        ];

        foreach ($keys as $key) {
            $value = $source[$key] ?? null;

            if (filled($value)) {
                return (string) $value;
            }
        }

        return null;
    }

    /*
    |--------------------------------------------------------------------------
    | FORMAT PRICE
    |--------------------------------------------------------------------------
    */

    protected function formatPrice(
        mixed $amount,
        mixed $currency = null
    ): ?string {
        if (
            $amount === null
            || $amount === ''
        ) {
            return null;
        }

        if (is_array($amount)) {
            $amount =
                $amount['amount']
                ?? $amount['value']
                ?? null;
        }

        if (
            $amount === null
            || $amount === ''
        ) {
            return null;
        }

        $amountString = is_scalar($amount)
            ? (string) $amount
            : json_encode($amount);

        $currencyString = is_scalar($currency)
            ? trim((string) $currency)
            : '';

        return $currencyString !== ''
            ? $currencyString . ' ' . $amountString
            : $amountString;
    }

    /*
    |--------------------------------------------------------------------------
    | FORMAT DATE
    |--------------------------------------------------------------------------
    */

    protected function formatPurchaseDate(
        mixed $value
    ): ?string {
        if (
            $value === null
            || $value === ''
        ) {
            return null;
        }

        if (is_array($value)) {
            $value =
                $value['date']
                ?? $value['value']
                ?? null;
        }

        if (
            $value === null
            || $value === ''
        ) {
            return null;
        }

        try {
            return Carbon::parse(
                (string) $value
            )->format('d M Y');
        } catch (Throwable) {
            return (string) $value;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | FORMAT RECEIPT
    |--------------------------------------------------------------------------
    */

    protected function formatReceiptNumber(
        mixed $value
    ): ?string {
        if (
            $value === null
            || $value === ''
        ) {
            return null;
        }

        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        /*
        | Don't double-format an existing REC number.
        */

        if (
            preg_match(
                '/^REC/i',
                $value
            )
        ) {
            return strtoupper($value);
        }

        $digits = preg_replace(
            '/\D/',
            '',
            $value
        );

        if ($digits === '') {
            return $value;
        }

        return 'REC' . $digits;
    }

    /*
    |--------------------------------------------------------------------------
    | FETCH CONTACT
    |--------------------------------------------------------------------------
    */

    protected function fetchContact(
        ?string $contactId
    ): ?ParsedGhlContactData {
        if (blank($contactId)) {
            return null;
        }

        try {
            $response = $this->ghlApi->getContact(
                $contactId
            );

            $contactData =
                $response['contact']
                ?? $response;

            if (! is_array($contactData)) {
                return null;
            }

            return $this->ghlParser->contactFromApi(
                $contactData
            );
        } catch (Throwable $e) {
            Log::warning(
                'Failed to fetch GHL contact',
                [
                    'contact_id' =>
                        $contactId,

                    'error' =>
                        $e->getMessage(),
                ]
            );

            return null;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | SENTIMENT
    |--------------------------------------------------------------------------
    */

    public function sentiment(
        Conversation $conversation,
        bool $forceRefresh = false
    ): array {
        return $this->remember(
            'sentiment',
            $conversation,
            $forceRefresh,
            function (string $thread) use ($conversation): array {
                $result = $this->aiClient->json(
                    $this->promptFactory->sentiment(
                        $thread
                    )
                );

                $this->logCall(
                    $conversation,
                    $thread,
                    $result
                );

                return $result;
            }
        );
    }

    /*
    |--------------------------------------------------------------------------
    | CACHE AI TOOLS
    |--------------------------------------------------------------------------
    */

    protected function remember(
        string $tool,
        Conversation $conversation,
        bool $forceRefresh,
        \Closure $compute
    ): array {
        $thread = $this->threadFormatter->format(
            $this->messagesFor($conversation)
        );

        $key =
            'inbox-tool:'
            . $tool
            . ':'
            . $conversation->id
            . ':'
            . md5($thread);

        if (
            ! $forceRefresh
            && Cache::has($key)
        ) {
            return Cache::get($key);
        }

        $result = $compute($thread);

        Cache::put(
            $key,
            $result,
            now()->addMinutes(
                self::CACHE_TTL_MINUTES
            )
        );

        return $result;
    }

    /*
    |--------------------------------------------------------------------------
    | MESSAGE SOURCE
    |--------------------------------------------------------------------------
    */

    protected function messagesFor(
        Conversation $conversation
    ) {
        if (
            filled(
                $conversation->ghl_conversation_id
            )
        ) {
            return $this->ghlThreadLoader->messages(
                $conversation->ghl_conversation_id
            );
        }

        return $conversation
            ->messages()
            ->orderBy('sent_at')
            ->get();
    }

    /*
    |--------------------------------------------------------------------------
    | AI LOG
    |--------------------------------------------------------------------------
    */

    protected function logCall(
        Conversation $conversation,
        string $thread,
        array $result,
        ?int $intentId = null,
        ?int $sopId = null
    ): void {
        try {
            AiLog::create([
                'source' =>
                    AiCenterLogSource::InboxTool,

                'conversation_id' =>
                    $conversation->exists
                        ? $conversation->id
                        : null,

                'intent_id' =>
                    $intentId,

                'sop_id' =>
                    $sopId,

                'triggered_by' =>
                    auth()->id(),

                'prompt' =>
                    $thread,

                'response' =>
                    json_encode(
                        $result,
                        JSON_UNESCAPED_UNICODE
                        | JSON_UNESCAPED_SLASHES
                    ),

                'status' =>
                    AiCenterLogStatus::Success,
            ]);
        } catch (Throwable $e) {
            /*
            | AI result should not fail only because
            | logging failed.
            */

            Log::warning(
                'Failed to create AI log',
                [
                    'conversation_id' =>
                        $conversation->id,

                    'error' =>
                        $e->getMessage(),
                ]
            );
        }
    }
}