<?php

namespace App\Enums\AiCenter;

enum ConditionField: string
{
    // Resolved directly from Analysis/Conversation/Intent.
    case Intent = 'intent';
    case Priority = 'priority';
    case Sentiment = 'sentiment';
    case CustomerStatus = 'customer_status';
    case RefundRequested = 'refund_requested';
    case EscalationRequired = 'escalation_required';
    case Channel = 'channel';
    case ConfidenceScore = 'confidence_score';

    // Resolved from Conversation::metadata (no real billing/CRM integration
    // yet — see conversations.metadata migration comment).
    case Plan = 'plan';
    case Country = 'country';
    case IsVip = 'is_vip';
    case SubscriptionStatus = 'subscription_status';
    case OrderCompleted = 'order_completed';
    case InvoiceExists = 'invoice_exists';
    case DaysSincePurchase = 'days_since_purchase';

    public function label(): string
    {
        return match ($this) {
            self::Intent => 'Intent',
            self::Priority => 'Priority',
            self::Sentiment => 'Sentiment',
            self::CustomerStatus => 'Customer Status',
            self::RefundRequested => 'Refund Requested',
            self::EscalationRequired => 'Escalation Required',
            self::Channel => 'Channel',
            self::ConfidenceScore => 'Confidence Score',
            self::Plan => 'Plan',
            self::Country => 'Country',
            self::IsVip => 'Customer VIP',
            self::SubscriptionStatus => 'Subscription Status',
            self::OrderCompleted => 'Order Completed',
            self::InvoiceExists => 'Invoice Exists',
            self::DaysSincePurchase => 'Days Since Purchase',
        };
    }

    /**
     * Whether this field is a boolean flag ("is_true"/"is_false"/"exists"
     * are the only operators that make sense).
     */
    public function isBoolean(): bool
    {
        return in_array($this, [
            self::RefundRequested,
            self::EscalationRequired,
            self::IsVip,
            self::OrderCompleted,
            self::InvoiceExists,
        ], true);
    }

    public function isMetadataBacked(): bool
    {
        return in_array($this, [
            self::Plan,
            self::Country,
            self::IsVip,
            self::SubscriptionStatus,
            self::OrderCompleted,
            self::InvoiceExists,
            self::DaysSincePurchase,
        ], true);
    }
}
