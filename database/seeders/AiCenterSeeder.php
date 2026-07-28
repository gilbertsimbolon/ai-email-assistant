<?php

namespace Database\Seeders;

use App\Enums\AiCenter\PriorityLevel;
use App\Enums\AiCenter\PublishStatus;
use App\Models\AiCenter\AiCenterSetting;
use App\Models\AiCenter\Intent;
use App\Models\AiCenter\TemplateVariable;
use Illuminate\Database\Seeder;

/**
 * Seeds the example Intent taxonomy from claude.txt (published, so they
 * match immediately), the fixed 10 template variables, and the singleton
 * AiCenterSetting row. "General Inquiry" is guaranteed to exist — it is
 * IntentDetectionEngine's ultimate fallback when nothing else matches.
 */
class AiCenterSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedIntents();
        $this->seedTemplateVariables();
        $this->seedAiCenterSettings();
    }

    protected function seedIntents(): void
    {
        $intents = [
            'Refund' => ['priority' => PriorityLevel::High, 'keywords' => ['refund', 'money back', 'cancel payment', 'return', 'cash back']],
            'Billing' => ['priority' => PriorityLevel::Medium, 'keywords' => ['invoice', 'payment', 'charged', 'subscription']],
            'Technical Support' => ['priority' => PriorityLevel::High, 'keywords' => ['bug', 'error', 'cannot login', 'crash', 'failed']],
            'Complaint' => ['priority' => PriorityLevel::High, 'keywords' => []],
            'Sales' => ['priority' => PriorityLevel::Medium, 'keywords' => []],
            'Subscription' => ['priority' => PriorityLevel::Medium, 'keywords' => []],
            'Account' => ['priority' => PriorityLevel::Medium, 'keywords' => []],
            'Verification' => ['priority' => PriorityLevel::Medium, 'keywords' => []],
            'Spam' => ['priority' => PriorityLevel::Low, 'keywords' => []],
            'General Inquiry' => ['priority' => PriorityLevel::Low, 'keywords' => []],
            'Shipping' => ['priority' => PriorityLevel::Medium, 'keywords' => []],
            'Invoice' => ['priority' => PriorityLevel::Medium, 'keywords' => []],
            'Feature Request' => ['priority' => PriorityLevel::Low, 'keywords' => []],
            'Cancellation' => ['priority' => PriorityLevel::High, 'keywords' => []],
            'Bug Report' => ['priority' => PriorityLevel::High, 'keywords' => []],
        ];

        foreach ($intents as $name => $data) {
            $intent = Intent::query()->firstOrCreate(
                ['name' => $name],
                ['priority' => $data['priority'], 'status' => PublishStatus::Published]
            );

            foreach ($data['keywords'] as $keyword) {
                $intent->keywords()->firstOrCreate(['keyword' => $keyword]);
            }
        }
    }

    protected function seedTemplateVariables(): void
    {
        $variables = [
            'customer_name' => 'Customer Name',
            'company' => 'Company',
            'email' => 'Email',
            'invoice_number' => 'Invoice Number',
            'subscription_date' => 'Subscription Date',
            'refund_amount' => 'Refund Amount',
            'today' => "Today's Date",
            'agent_name' => 'Agent Name',
            'product' => 'Product',
            'ticket_number' => 'Ticket Number',
        ];

        foreach ($variables as $key => $label) {
            TemplateVariable::query()->firstOrCreate(['key' => $key], ['label' => $label]);
        }
    }

    protected function seedAiCenterSettings(): void
    {
        if (AiCenterSetting::current()) {
            return;
        }

        AiCenterSetting::create([
            'confidence_review_threshold' => 0.70,
            'default_fallback_tone' => 'professional',
            'default_escalation_target' => 'human_agent',
        ]);
    }
}
