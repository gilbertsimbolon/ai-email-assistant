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
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Backs the 5 manual Inbox toolbar actions (Summarize/Translate/Detect
 * Intent/Extract Info/Sentiment — claude.txt Task 3). Every call is
 * user-initiated (never automatic, same rule AiGenerationService follows
 * for draft generation) and cached for an hour per conversation+thread
 * content so re-opening a modal doesn't re-spend OpenAI tokens. The
 * exception is Extract Info (claude.txt: "Remove AI from Extract Info") —
 * it never touches aiClient at all, it reads straight from GHL.
 */
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

    /**
     * @return array<string, mixed>
     */
    public function summarize(Conversation $conversation, bool $forceRefresh = false): array
    {
        return $this->remember('summarize', $conversation, $forceRefresh, function (string $thread) use ($conversation) {
            $result = $this->aiClient->json($this->promptFactory->summarize($thread));
            $this->logCall($conversation, $thread, $result);

            return $result;
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function translate(Conversation $conversation, string $language, bool $forceRefresh = false): array
    {
        $languageLabel = self::LANGUAGES[$language] ?? $language;

        return $this->remember("translate:{$language}", $conversation, $forceRefresh, function (string $thread) use ($conversation, $languageLabel) {
            $result = $this->aiClient->json($this->promptFactory->translate($thread, $languageLabel));
            $this->logCall($conversation, $thread, $result);

            return $result;
        });
    }

    /**
     * Reuses IntentDetectionEngine/SopMatchingEngine/KnowledgeResolver — the
     * same matching stages AiCenterPipeline runs for Generate/Regenerate —
     * so "Matched SOP"/"Matched Knowledge"/"Matched Template" reflect
     * exactly what a real draft generation would pick up.
     *
     * @return array<string, mixed>
     */
    public function detectIntent(Conversation $conversation, bool $forceRefresh = false): array
    {
        return $this->remember('detect-intent', $conversation, $forceRefresh, function (string $thread) use ($conversation) {
            $knownIntentNames = $this->intentDetectionEngine->shortlist($thread)->pluck('name')->all();
            $classification = $this->aiClient->json($this->promptFactory->detectIntent($thread, $knownIntentNames));

            $intent = $this->intentDetectionEngine->resolve($thread, $classification);
            $sopMatch = $this->sopMatchingEngine->match($conversation, $intent, $thread);
            $sop = $sopMatch->sop;
            $knowledgeBases = $this->knowledgeResolver->resolve($sop);

            $result = [
                'intent' => $classification['intent'] ?? $intent?->name,
                'confidence_score' => $classification['confidence_score'] ?? null,
                'reasoning' => $classification['reasoning'] ?? '',
                'matched_sop' => $sop?->name,
                'matched_knowledge' => $knowledgeBases->pluck('title')->values()->all(),
                'matched_template' => $sop?->replyTemplate?->name,
            ];

            $this->logCall($conversation, $thread, $result, intentId: $intent?->id, sopId: $sop?->id);

            return $result;
        });
    }

    /**
     * NOT an AI call (claude.txt Task 1: "Remove AI from Extract Info").
     * Every field here comes straight from GHL's own contact/conversation
     * data — the same source InboxController's Contact Details panel
     * reads from — never inferred by a model. Cached briefly per
     * conversation purely to avoid re-hitting GHL on every modal open;
     * force_refresh bypasses that cache the same way the AI tools' own
     * force-refresh does.
     *
     * @return array<string, mixed>
     */
    public function extractInformation(Conversation $conversation, bool $forceRefresh = false): array
    {
        $key = 'inbox-extract-info:'.$conversation->id;

        if (! $forceRefresh && Cache::has($key)) {
            return Cache::get($key);
        }

        $contact = $this->fetchContact($conversation->contact_id);
        $purchaseInfo = $this->extractPurchaseInfo($contact?->customFields ?? []);
        $purchaseInfo = $this->fillPurchaseInfoFromOrders($conversation->contact_id, $purchaseInfo);
        $purchaseInfo = $this->fillPurchaseInfoFromTransactions($conversation->contact_id, $purchaseInfo);

        // claude.txt Step 3/10: never logs the actual values (names, prices,
        // etc.), only which source — if any — ended up answering each of
        // the 4 purchase fields. This is the evidence trail for "kenapa
        // field selalu -": check this log line against GHL API request
        // succeeded/failed lines for the same conversation_id.
        Log::debug('Extract Info purchase field resolution', [
            'conversation_id' => $conversation->id,
            'contact_id' => $conversation->contact_id,
            'product_found' => $purchaseInfo['product'] !== null,
            'purchase_date_found' => $purchaseInfo['purchase_date'] !== null,
            'purchase_price_found' => $purchaseInfo['purchase_price'] !== null,
            'receipt_number_found' => $purchaseInfo['receipt_number'] !== null,
        ]);

        $result = [
            'customer_name' => $contact?->fullName() ?: $conversation->contact_name,
            'email' => $contact?->email ?: $conversation->contact_email,
            'phone' => $contact?->phone ?: $conversation->contact_phone,
            'contact_id' => $conversation->contact_id,
            'conversation_id' => $conversation->ghl_conversation_id,
            'channel' => $conversation->channel,
            'company_name' => $contact?->companyName,
            'product' => $purchaseInfo['product'],
            'purchase_date' => $purchaseInfo['purchase_date'],
            'purchase_price' => $purchaseInfo['purchase_price'],
            'receipt_number' => $purchaseInfo['receipt_number'],
            'tags' => $contact?->tags ?? [],
            'custom_fields' => $purchaseInfo['remaining_custom_fields'],
        ];

        Cache::put($key, $result, now()->addMinutes(self::EXTRACT_INFO_CACHE_TTL_MINUTES));

        return $result;
    }

    /**
     * Picks the 4 purchase-related fields (claude.txt: Product/Purchase
     * Date/Purchase Price/Receipt Number) out of GHL's free-form contact
     * custom fields. GHL custom field *keys* are whatever an agency named
     * them in their own location (e.g. "contact.product_purchased" vs just
     * "product"), so this matches against a normalized key (lowercased,
     * stripped of separators) against a handful of known aliases instead of
     * one exact string. Never invents a value — a field GHL doesn't have
     * stays null and the UI renders "-". Matched fields are removed from
     * the generic custom-fields list so they aren't shown twice.
     *
     * @param  array<int, array{id: ?string, key: ?string, value: mixed}>  $customFields
     * @return array{product: ?string, purchase_date: ?string, purchase_price: ?string, receipt_number: ?string, remaining_custom_fields: array}
     */
    protected function extractPurchaseInfo(array $customFields): array
    {
        $aliasesByTarget = [
            'product' => ['product', 'productname', 'productpurchased', 'itempurchased', 'item', 'productbought'],
            'purchase_date' => ['purchasedate', 'dateofpurchase', 'orderdate', 'datepurchased', 'transactiondate'],
            'purchase_price' => ['purchaseprice', 'price', 'amount', 'orderamount', 'ordertotal', 'transactionamount', 'total', 'paymentamount'],
            'receipt_number' => ['receiptnumber', 'receiptno', 'receipt', 'invoicenumber', 'invoiceno', 'ordernumber', 'orderid', 'transactionid'],
        ];

        $values = ['product' => null, 'purchase_date' => null, 'purchase_price' => null, 'receipt_number' => null];
        $matchedFieldIds = [];

        foreach ($customFields as $field) {
            $normalizedKey = preg_replace('/[^a-z0-9]/', '', strtolower((string) ($field['key'] ?? '')));

            if ($normalizedKey === '') {
                continue;
            }

            foreach ($aliasesByTarget as $target => $aliases) {
                if ($values[$target] !== null || ! in_array($normalizedKey, $aliases, true)) {
                    continue;
                }

                $value = $field['value'];
                $values[$target] = is_array($value) ? implode(', ', $value) : (string) $value;
                $matchedFieldIds[] = $field['id'] ?? $field['key'];
                break;
            }
        }

        $remaining = collect($customFields)
            ->reject(fn (array $field) => in_array($field['id'] ?? $field['key'], $matchedFieldIds, true))
            ->values()
            ->all();

        return [
            'product' => $values['product'],
            'purchase_date' => $this->formatPurchaseDate($values['purchase_date']),
            'purchase_price' => $values['purchase_price'],
            'receipt_number' => $this->formatReceiptNumber($values['receipt_number']),
            'remaining_custom_fields' => $remaining,
        ];
    }

    /**
     * Falls back to GHL's Payments Orders API (claude.txt Step 6: Contact ID
     * -> Order -> Product/Purchase Info) for whichever purchase fields the
     * contact's own custom fields didn't answer. Orders is the GHL resource
     * that actually models "a completed purchase" — amount/currency,
     * createdAt, and an items[] array carrying the product name — unlike
     * the Contact resource, which only has whatever custom fields a
     * location owner manually configured. Skips the API call entirely once
     * every field is already filled, and never overwrites a value the
     * custom fields already provided.
     *
     * Assumes the most recent order (first page, default sort) is the
     * relevant one — GHL's Orders list doesn't let you target "the order
     * this conversation is about", so with multiple historical purchases
     * this picks the latest rather than guessing which one the customer is
     * asking about.
     *
     * @param  array{product: ?string, purchase_date: ?string, purchase_price: ?string, receipt_number: ?string, remaining_custom_fields: array}  $purchaseInfo
     * @return array{product: ?string, purchase_date: ?string, purchase_price: ?string, receipt_number: ?string, remaining_custom_fields: array}
     */
    protected function fillPurchaseInfoFromOrders(?string $contactId, array $purchaseInfo): array
    {
        if (blank($contactId) || $this->purchaseInfoComplete($purchaseInfo)) {
            return $purchaseInfo;
        }

        try {
            $response = $this->ghlApi->getOrders($contactId);
        } catch (Throwable $e) {
            Log::warning('Failed to fetch GHL orders for Extract Info', [
                'contact_id' => $contactId,
                'error' => $e->getMessage(),
            ]);

            return $purchaseInfo;
        }

        $order = $this->firstRecord($response, ['orders', 'data']);

        if ($order === null) {
            return $purchaseInfo;
        }

        $item = $this->firstRecord(['items' => $order['items'] ?? []], ['items']) ?? [];

        if ($purchaseInfo['product'] === null) {
            $purchaseInfo['product'] = $item['product']['name'] ?? $item['name'] ?? null;
        }

        if ($purchaseInfo['purchase_date'] === null) {
            $purchaseInfo['purchase_date'] = $this->formatPurchaseDate($order['createdAt'] ?? null);
        }

        if ($purchaseInfo['purchase_price'] === null) {
            $purchaseInfo['purchase_price'] = $this->formatPrice($order['amount'] ?? null, $order['currency'] ?? null);
        }

        if ($purchaseInfo['receipt_number'] === null) {
            $purchaseInfo['receipt_number'] = $this->formatReceiptNumber($this->findReceiptNumber($order));
        }

        return $purchaseInfo;
    }

    /**
     * Secondary probe against GHL's Payments Transactions API (claude.txt
     * Step 2: "jangan mengasumsikan salah satu endpoint") — only reached for
     * whatever Orders still left null. Unlike Orders, GHL's public
     * Transaction schema isn't documented to carry a receipt/invoice
     * number, so this only claims a value when the response itself has a
     * field explicitly named like one (see findReceiptNumber) — it never
     * repurposes a payment/charge ID as a "receipt number", since that
     * would misrepresent an internal identifier as a customer-facing one.
     *
     * @param  array{product: ?string, purchase_date: ?string, purchase_price: ?string, receipt_number: ?string, remaining_custom_fields: array}  $purchaseInfo
     * @return array{product: ?string, purchase_date: ?string, purchase_price: ?string, receipt_number: ?string, remaining_custom_fields: array}
     */
    protected function fillPurchaseInfoFromTransactions(?string $contactId, array $purchaseInfo): array
    {
        if (blank($contactId) || $this->purchaseInfoComplete($purchaseInfo)) {
            return $purchaseInfo;
        }

        try {
            $response = $this->ghlApi->getTransactions($contactId);
        } catch (Throwable $e) {
            Log::warning('Failed to fetch GHL transactions for Extract Info', [
                'contact_id' => $contactId,
                'error' => $e->getMessage(),
            ]);

            return $purchaseInfo;
        }

        $transaction = $this->firstRecord($response, ['transactions', 'data']);

        if ($transaction === null) {
            return $purchaseInfo;
        }

        if ($purchaseInfo['product'] === null) {
            $purchaseInfo['product'] = $transaction['productName'] ?? $transaction['description'] ?? null;
        }

        if ($purchaseInfo['purchase_date'] === null) {
            $purchaseInfo['purchase_date'] = $this->formatPurchaseDate($transaction['createdAt'] ?? null);
        }

        if ($purchaseInfo['purchase_price'] === null) {
            $purchaseInfo['purchase_price'] = $this->formatPrice($transaction['amount'] ?? null, $transaction['currency'] ?? null);
        }

        if ($purchaseInfo['receipt_number'] === null) {
            $purchaseInfo['receipt_number'] = $this->formatReceiptNumber($this->findReceiptNumber($transaction));
        }

        return $purchaseInfo;
    }

    protected function purchaseInfoComplete(array $purchaseInfo): bool
    {
        return $purchaseInfo['product'] !== null
            && $purchaseInfo['purchase_date'] !== null
            && $purchaseInfo['purchase_price'] !== null
            && $purchaseInfo['receipt_number'] !== null;
    }

    /**
     * GHL list endpoints aren't consistent about the wrapper key around
     * their array of records (`orders`, `data`, or occasionally the bare
     * list), so this tries each known wrapper before giving up — never
     * assumes a shape it hasn't actually seen in the response.
     *
     * @param  array<int, string>  $listKeys
     */
    protected function firstRecord(array $response, array $listKeys): ?array
    {
        foreach ($listKeys as $listKey) {
            $list = $response[$listKey] ?? null;

            if (is_array($list) && $list !== [] && is_array($list[array_key_first($list)])) {
                return $list[array_key_first($list)];
            }
        }

        if (array_is_list($response) && isset($response[0]) && is_array($response[0])) {
            return $response[0];
        }

        return null;
    }

    /**
     * Only matches a field GHL itself named like a receipt/invoice number —
     * never a generic identifier (order _id, chargeId) that happens to
     * contain digits, since presenting an internal payment ID as "Receipt
     * Number" would misrepresent real data rather than reflect it.
     */
    protected function findReceiptNumber(array $source): ?string
    {
        foreach (['receiptNumber', 'receipt_number', 'receiptNo', 'invoiceNumber', 'invoice_number', 'invoiceNo', 'orderNumber', 'order_number'] as $key) {
            if (filled($source[$key] ?? null)) {
                return (string) $source[$key];
            }
        }

        return null;
    }

    /**
     * Formats a GHL amount (a plain number, minor or major unit depending
     * on the endpoint) alongside its currency code exactly as returned —
     * no currency symbol guessing, since GHL locations can be configured
     * in any currency.
     */
    protected function formatPrice(mixed $amount, ?string $currency): ?string
    {
        if (blank($amount) && $amount !== 0) {
            return null;
        }

        return $currency ? trim($currency).' '.$amount : (string) $amount;
    }

    /**
     * Renders whatever date format GHL stored the custom field in as
     * "d M Y" (same day/month style as the rest of Inbox, e.g.
     * conversation-item.blade.php's "M j"). Falls back to the raw string
     * if GHL's value isn't a parseable date, rather than dropping it.
     */
    protected function formatPurchaseDate(?string $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        try {
            return Carbon::parse($value)->format('d M Y');
        } catch (Throwable) {
            return $value;
        }
    }

    /**
     * Normalizes the receipt number to the "REC<digits>" format claude.txt
     * requires (e.g. REC2141), pulling the digits out of whatever raw value
     * GHL has for that field — never a made-up number, just a consistent
     * shape around GHL's own value.
     */
    protected function formatReceiptNumber(?string $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        $digits = preg_replace('/\D/', '', $value);

        if ($digits === '') {
            return $value;
        }

        return 'REC'.$digits;
    }

    /**
     * Same on-demand, defensive-read GHL contact fetch InboxController uses
     * for the Contact Details panel — a failed/missing fetch returns null
     * rather than fabricating data, so Extract Info can fall back to
     * whatever the local anchor already knows (contact_name/email/phone
     * seeded from GHL when the anchor was created).
     */
    protected function fetchContact(?string $contactId): ?ParsedGhlContactData
    {
        if (blank($contactId)) {
            return null;
        }

        try {
            $response = $this->ghlApi->getContact($contactId);

            return $this->ghlParser->contactFromApi($response['contact'] ?? $response);
        } catch (Throwable $e) {
            Log::warning('Failed to fetch GHL contact for Extract Info', [
                'contact_id' => $contactId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function sentiment(Conversation $conversation, bool $forceRefresh = false): array
    {
        return $this->remember('sentiment', $conversation, $forceRefresh, function (string $thread) use ($conversation) {
            $result = $this->aiClient->json($this->promptFactory->sentiment($thread));
            $this->logCall($conversation, $thread, $result);

            return $result;
        });
    }

    /**
     * @return array<string, mixed>
     */
    protected function remember(string $tool, Conversation $conversation, bool $forceRefresh, \Closure $compute): array
    {
        $thread = $this->threadFormatter->format($this->messagesFor($conversation));

        $key = 'inbox-tool:'.$tool.':'.$conversation->id.':'.md5($thread);

        if (! $forceRefresh && Cache::has($key)) {
            return Cache::get($key);
        }

        $result = $compute($thread);

        Cache::put($key, $result, now()->addMinutes(self::CACHE_TTL_MINUTES));

        return $result;
    }

    /**
     * GHL-sourced conversations are never mirrored into the messages table
     * (claude.txt) — their thread is fetched live on every call. Gmail-
     * sourced ones keep reading the real, persisted relation.
     *
     * @return \Illuminate\Support\Collection<int, \App\Models\Message>
     */
    protected function messagesFor(Conversation $conversation)
    {
        if (filled($conversation->ghl_conversation_id)) {
            return $this->ghlThreadLoader->messages($conversation->ghl_conversation_id);
        }

        return $conversation->messages()->orderBy('sent_at')->get();
    }

    /**
     * @param  array<string, mixed>  $result
     */
    protected function logCall(Conversation $conversation, string $thread, array $result, ?int $intentId = null, ?int $sopId = null): void
    {
        AiLog::create([
            'source' => AiCenterLogSource::InboxTool,
            'conversation_id' => $conversation->exists ? $conversation->id : null,
            'intent_id' => $intentId,
            'sop_id' => $sopId,
            'triggered_by' => auth()->id(),
            'prompt' => $thread,
            'response' => json_encode($result),
            'status' => AiCenterLogStatus::Success,
        ]);
    }
}
