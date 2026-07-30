<?php

namespace Database\Seeders;

use App\Enums\AiCenter\AiAction;
use App\Enums\AiCenter\BooleanOperator;
use App\Enums\AiCenter\ConditionField;
use App\Enums\AiCenter\ConditionOperator;
use App\Enums\AiCenter\EscalationTarget;
use App\Enums\AiCenter\KnowledgeBaseType;
use App\Enums\AiCenter\PriorityLevel;
use App\Enums\AiCenter\PublishStatus;
use App\Enums\AiCenter\ResponseFormat;
use App\Enums\AiCenter\Tone;
use App\Enums\AiCenter\WorkflowNodeType;
use App\Enums\AiProvider;
use App\Models\AiCenter\AiCenterSetting;
use App\Models\AiCenter\AiModel;
use App\Models\AiCenter\Category;
use App\Models\AiCenter\Intent;
use App\Models\AiCenter\KnowledgeBase;
use App\Models\AiCenter\ReplyTemplate;
use App\Models\AiCenter\Sop;
use App\Models\AiCenter\TemplateVariable;
use App\Models\AiCenter\Workflow;
use App\Models\AiCenter\WorkflowNode;
use Illuminate\Database\Seeder;

/**
 * Seeds the example Intent taxonomy from claude.txt (published, so they
 * match immediately), Categories, Knowledge Base articles, Reply Templates,
 * Workflows, SOPs tying them all together, AI Model configurations, the
 * fixed 10 template variables, and the singleton AiCenterSetting row.
 * "General Inquiry" is guaranteed to exist — it is IntentDetectionEngine's
 * ultimate fallback when nothing else matches.
 */
class AiCenterSeeder extends Seeder
{
    public function run(): void
    {
        $categories = $this->seedCategories();
        $intents = $this->seedIntents($categories);
        $this->seedTemplateVariables();
        $knowledgeBases = $this->seedKnowledgeBases();
        $replyTemplates = $this->seedReplyTemplates();
        $workflows = $this->seedWorkflows();
        $this->seedSops($categories, $intents, $knowledgeBases, $replyTemplates, $workflows);
        $this->seedAiModels();
        $this->seedAiCenterSettings();
    }

    /**
     * @return array<string, Category>
     */
    protected function seedCategories(): array
    {
        $categories = [];

        foreach (['Support', 'Billing', 'Technical', 'General'] as $name) {
            $categories[$name] = Category::query()->firstOrCreate(['name' => $name]);
        }

        return $categories;
    }

    /**
     * @param  array<string, Category>  $categories
     * @return array<string, Intent>
     */
    protected function seedIntents(array $categories): array
    {
        $intents = [
            'Refund' => [
                'priority' => PriorityLevel::High,
                'category' => 'Billing',
                'keywords' => ['refund', 'money back', 'cancel payment', 'return', 'cash back'],
                'examples' => ['I want refund', 'Please refund me', 'I need my money back'],
            ],
            'Billing' => [
                'priority' => PriorityLevel::Medium,
                'category' => 'Billing',
                'keywords' => ['invoice', 'payment', 'charged', 'subscription'],
                'examples' => [],
            ],
            'Technical Support' => [
                'priority' => PriorityLevel::High,
                'category' => 'Technical',
                'keywords' => ['bug', 'error', 'cannot login', 'crash', 'failed'],
                'examples' => [],
            ],
            'Login Issue' => [
                'priority' => PriorityLevel::High,
                'category' => 'Technical',
                'keywords' => ['login', 'cannot login', 'password'],
                'examples' => ["I can't login", 'Forgot password'],
            ],
            'Complaint' => ['priority' => PriorityLevel::High, 'category' => null, 'keywords' => [], 'examples' => []],
            'Sales' => ['priority' => PriorityLevel::Medium, 'category' => null, 'keywords' => [], 'examples' => []],
            'Subscription' => ['priority' => PriorityLevel::Medium, 'category' => 'Billing', 'keywords' => [], 'examples' => []],
            'Account' => ['priority' => PriorityLevel::Medium, 'category' => null, 'keywords' => [], 'examples' => []],
            'Verification' => ['priority' => PriorityLevel::Medium, 'category' => null, 'keywords' => [], 'examples' => []],
            'Spam' => ['priority' => PriorityLevel::Low, 'category' => null, 'keywords' => [], 'examples' => []],
            'General Inquiry' => ['priority' => PriorityLevel::Low, 'category' => 'General', 'keywords' => [], 'examples' => []],
            'Shipping' => ['priority' => PriorityLevel::Medium, 'category' => null, 'keywords' => [], 'examples' => []],
            'Invoice' => ['priority' => PriorityLevel::Medium, 'category' => null, 'keywords' => [], 'examples' => []],
            'Feature Request' => ['priority' => PriorityLevel::Low, 'category' => null, 'keywords' => [], 'examples' => []],
            'Cancellation' => ['priority' => PriorityLevel::High, 'category' => 'General', 'keywords' => [], 'examples' => []],
            'Bug Report' => ['priority' => PriorityLevel::High, 'category' => null, 'keywords' => [], 'examples' => []],
        ];

        $created = [];

        foreach ($intents as $name => $data) {
            $intent = Intent::query()->firstOrCreate(
                ['name' => $name],
                [
                    'priority' => $data['priority'],
                    'status' => PublishStatus::Published,
                    'category_id' => $data['category'] ? $categories[$data['category']]->id : null,
                ]
            );

            if (! $intent->category_id && $data['category']) {
                $intent->update(['category_id' => $categories[$data['category']]->id]);
            }

            foreach ($data['keywords'] as $keyword) {
                $intent->keywords()->firstOrCreate(['keyword' => $keyword]);
            }

            foreach ($data['examples'] as $example) {
                $intent->examples()->firstOrCreate(['example_text' => $example]);
            }

            $created[$name] = $intent;
        }

        return $created;
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

    /**
     * @return array<string, KnowledgeBase>
     */
    protected function seedKnowledgeBases(): array
    {
        $items = [
            'Refund Policy' => [
                'type' => KnowledgeBaseType::RefundPolicy,
                'content' => 'Customers may request a full refund within 14 days of purchase. Refunds are processed back to the original payment method within 5-7 business days. Subscriptions cancelled mid-cycle are refunded on a pro-rata basis.',
            ],
            'Subscription Policy' => [
                'type' => KnowledgeBaseType::CompanyPolicy,
                'content' => 'Subscriptions renew automatically at the end of each billing cycle unless cancelled at least 24 hours before renewal. Customers can upgrade, downgrade, or cancel from their account settings at any time.',
            ],
            'Billing FAQ' => [
                'type' => KnowledgeBaseType::Faq,
                'content' => 'Common billing questions: charges appear on statements as "AI EMAIL ASSISTANT". Failed payments are retried automatically for 3 days. Invoices are emailed after every successful charge and available in Billing History.',
            ],
            'Login Troubleshooting' => [
                'type' => KnowledgeBaseType::Troubleshooting,
                'content' => 'If a customer cannot log in: confirm the email is correct, ask them to clear cookies/cache or try an incognito window, and check whether their account was locked after repeated failed attempts (auto-unlocks after 30 minutes).',
            ],
            'Password Reset' => [
                'type' => KnowledgeBaseType::Troubleshooting,
                'content' => 'To reset a password, use the "Forgot Password" link on the login page. The reset email expires after 60 minutes. If it does not arrive, ask the customer to check spam or request a new link.',
            ],
            'Product Features' => [
                'type' => KnowledgeBaseType::ProductDocumentation,
                'content' => 'Core features include Gmail-connected inbox, AI-drafted replies with human review, SOP/Knowledge Base-driven responses, and usage reporting. Feature availability depends on the customer\'s subscription plan.',
            ],
        ];

        $created = [];
        $order = 0;

        foreach ($items as $title => $data) {
            $created[$title] = KnowledgeBase::query()->firstOrCreate(
                ['title' => $title],
                [
                    'type' => $data['type'],
                    'content' => $data['content'],
                    'status' => PublishStatus::Published,
                    'sort_order' => $order++,
                ]
            );
        }

        return $created;
    }

    /**
     * @return array<string, ReplyTemplate>
     */
    protected function seedReplyTemplates(): array
    {
        $greeting = "Hi {{customer_name}},\n\nThank you for contacting {{company}}.";
        $signature = "\n\nBest regards,\n{{agent_name}}\n{{company}}";

        $templates = [
            'Refund Approved' => [
                'category' => 'Billing',
                'subject' => 'Your refund has been approved',
                'body' => $greeting."\n\nYour refund of {{refund_amount}} for invoice {{invoice_number}} has been approved and will be credited to your original payment method within 5-7 business days.".$signature,
            ],
            'Refund Rejected' => [
                'category' => 'Billing',
                'subject' => 'Update on your refund request',
                'body' => $greeting."\n\nAfter reviewing invoice {{invoice_number}}, we're unable to process a refund as the purchase falls outside our refund policy window. Ticket reference: {{ticket_number}}.".$signature,
            ],
            'Subscription Renewal' => [
                'category' => 'Billing',
                'subject' => 'Your subscription has been renewed',
                'body' => $greeting."\n\nYour subscription to {{product}} was renewed on {{subscription_date}}. You can review or update your plan any time from your account settings.".$signature,
            ],
            'Technical Support' => [
                'category' => 'Technical',
                'subject' => 'We\'re looking into your issue',
                'body' => $greeting."\n\nWe've received your report about {{product}} (ticket {{ticket_number}}) and our technical team is investigating. We'll follow up as soon as we have an update.".$signature,
            ],
            'Billing Issue' => [
                'category' => 'Billing',
                'subject' => 'Regarding your billing question',
                'body' => $greeting."\n\nWe've reviewed the charge on invoice {{invoice_number}} for your {{email}} account. Please let us know if you have any further questions.".$signature,
            ],
            'General Inquiry' => [
                'category' => 'General',
                'subject' => 'Re: your message to {{company}}',
                'body' => $greeting."\n\nThanks for reaching out. Here's what we found regarding your question, and we're happy to help further if needed.".$signature,
            ],
        ];

        $created = [];

        foreach ($templates as $name => $data) {
            $created[$name] = ReplyTemplate::query()->firstOrCreate(
                ['name' => $name],
                [
                    'category' => $data['category'],
                    'subject' => $data['subject'],
                    'body' => $data['body'],
                    'status' => PublishStatus::Published,
                ]
            );
        }

        return $created;
    }

    /**
     * @return array<string, Workflow>
     */
    protected function seedWorkflows(): array
    {
        $workflows = [
            'Refund Flow' => [
                'description' => 'Identify Intent -> Load SOP -> Load Knowledge -> Generate Draft -> Human Review -> Send Email.',
                'nodes' => [
                    ['type' => WorkflowNodeType::Start, 'label' => 'Start'],
                    ['type' => WorkflowNodeType::IntentDetection, 'label' => 'Identify Intent'],
                    ['type' => WorkflowNodeType::Action, 'label' => 'Load SOP'],
                    ['type' => WorkflowNodeType::Action, 'label' => 'Load Knowledge'],
                    ['type' => WorkflowNodeType::Action, 'label' => 'Generate Draft'],
                    ['type' => WorkflowNodeType::Action, 'label' => 'Human Review'],
                    ['type' => WorkflowNodeType::Action, 'label' => 'Send Email'],
                    ['type' => WorkflowNodeType::End, 'label' => 'End'],
                ],
            ],
            'Standard Support Flow' => [
                'description' => 'Identify Intent -> Load SOP -> Generate Draft -> Human Review -> Send Email.',
                'nodes' => [
                    ['type' => WorkflowNodeType::Start, 'label' => 'Start'],
                    ['type' => WorkflowNodeType::IntentDetection, 'label' => 'Identify Intent'],
                    ['type' => WorkflowNodeType::Action, 'label' => 'Load SOP'],
                    ['type' => WorkflowNodeType::Action, 'label' => 'Generate Draft'],
                    ['type' => WorkflowNodeType::Action, 'label' => 'Human Review'],
                    ['type' => WorkflowNodeType::Action, 'label' => 'Send Email'],
                    ['type' => WorkflowNodeType::End, 'label' => 'End'],
                ],
            ],
        ];

        $created = [];

        foreach ($workflows as $name => $data) {
            $workflow = Workflow::query()->firstOrCreate(
                ['name' => $name],
                ['description' => $data['description'], 'status' => PublishStatus::Published]
            );

            if ($workflow->nodes()->count() === 0) {
                $previousNode = null;

                foreach ($data['nodes'] as $order => $nodeData) {
                    $node = WorkflowNode::query()->create([
                        'workflow_id' => $workflow->id,
                        'type' => $nodeData['type'],
                        'label' => $nodeData['label'],
                        'order' => $order,
                    ]);

                    if ($previousNode) {
                        $workflow->edges()->create([
                            'workflow_id' => $workflow->id,
                            'from_node_id' => $previousNode->id,
                            'to_node_id' => $node->id,
                        ]);
                    }

                    $previousNode = $node;
                }
            }

            $created[$name] = $workflow;
        }

        return $created;
    }

    /**
     * @param  array<string, Category>  $categories
     * @param  array<string, Intent>  $intents
     * @param  array<string, KnowledgeBase>  $knowledgeBases
     * @param  array<string, ReplyTemplate>  $replyTemplates
     * @param  array<string, Workflow>  $workflows
     */
    protected function seedSops(array $categories, array $intents, array $knowledgeBases, array $replyTemplates, array $workflows): void
    {
        $sops = [
            'Refund Request' => [
                'category' => 'Billing',
                'intent' => 'Refund',
                'priority' => PriorityLevel::High,
                'description' => "Purpose: handle customer requests to be refunded for a purchase or subscription charge.\nImportant notes: verify the refund is within the policy window before approving; never promise a refund timeline outside 5-7 business days.",
                'workflow' => 'Refund Flow',
                'reply_template' => 'Refund Approved',
                'triggers' => ['refund', 'money back', 'cancel payment'],
                'knowledge_bases' => ['Refund Policy'],
                'rule' => [
                    'name' => 'Standard Refund',
                    'tone' => Tone::Empathetic,
                    'escalation_target' => EscalationTarget::BillingTeam,
                    'conditions' => [
                        ['field' => ConditionField::RefundRequested, 'operator' => ConditionOperator::IsTrue],
                    ],
                    'actions' => [AiAction::ReplyUsingTemplate, AiAction::GenerateReply],
                ],
            ],
            'Login Problem' => [
                'category' => 'Technical',
                'intent' => 'Login Issue',
                'priority' => PriorityLevel::High,
                'description' => "Purpose: help customers who cannot access their account.\nImportant notes: never share another customer's account details; escalate if the account appears compromised.",
                'workflow' => 'Standard Support Flow',
                'reply_template' => null,
                'triggers' => ['login', 'password reset', 'cannot login'],
                'knowledge_bases' => ['Login Troubleshooting', 'Password Reset'],
                'rule' => [
                    'name' => 'Login Assistance',
                    'tone' => Tone::Friendly,
                    'escalation_target' => EscalationTarget::TechnicalTeam,
                    'conditions' => [],
                    'actions' => [AiAction::ReplyUsingKnowledgeBase, AiAction::GenerateReply],
                ],
            ],
            'Payment Failed' => [
                'category' => 'Billing',
                'intent' => 'Billing',
                'priority' => PriorityLevel::High,
                'description' => "Purpose: resolve failed or declined payment attempts.\nImportant notes: card details must never be requested or repeated back to the customer over email.",
                'workflow' => null,
                'reply_template' => 'Billing Issue',
                'triggers' => ['payment failed', 'card declined', 'charged twice'],
                'knowledge_bases' => ['Billing FAQ'],
                'rule' => [
                    'name' => 'Payment Failure',
                    'tone' => Tone::Professional,
                    'escalation_target' => EscalationTarget::BillingTeam,
                    'conditions' => [],
                    'actions' => [AiAction::ReplyUsingKnowledgeBase, AiAction::GenerateReply],
                ],
            ],
            'Account Cancellation' => [
                'category' => 'General',
                'intent' => 'Cancellation',
                'priority' => PriorityLevel::Medium,
                'description' => "Purpose: process account or subscription cancellation requests.\nImportant notes: offer to explain what happens to existing data before confirming; escalate high-value accounts to a supervisor.",
                'workflow' => null,
                'reply_template' => null,
                'triggers' => ['cancel my account', 'close account'],
                'knowledge_bases' => ['Subscription Policy'],
                'rule' => [
                    'name' => 'Cancellation Handling',
                    'tone' => Tone::Empathetic,
                    'escalation_target' => EscalationTarget::Supervisor,
                    'conditions' => [],
                    'actions' => [AiAction::Escalate, AiAction::GenerateReply],
                ],
            ],
            'Subscription Renewal' => [
                'category' => 'Billing',
                'intent' => 'Subscription',
                'priority' => PriorityLevel::Medium,
                'description' => "Purpose: answer questions about upcoming or completed subscription renewals.\nImportant notes: confirm the renewal date and plan from the customer's account before replying.",
                'workflow' => null,
                'reply_template' => 'Subscription Renewal',
                'triggers' => ['renew subscription', 'subscription expired'],
                'knowledge_bases' => ['Subscription Policy'],
                'rule' => [
                    'name' => 'Renewal Confirmation',
                    'tone' => Tone::Professional,
                    'escalation_target' => null,
                    'conditions' => [],
                    'actions' => [AiAction::ReplyUsingTemplate, AiAction::GenerateReply],
                ],
            ],
        ];

        foreach ($sops as $name => $data) {
            $sop = Sop::query()->firstOrCreate(
                ['name' => $name],
                [
                    'category_id' => $categories[$data['category']]->id,
                    'intent_id' => $intents[$data['intent']]->id,
                    'priority' => $data['priority'],
                    'status' => PublishStatus::Published,
                    'description' => $data['description'],
                    'workflow_id' => $data['workflow'] ? $workflows[$data['workflow']]->id : null,
                    'reply_template_id' => $data['reply_template'] ? $replyTemplates[$data['reply_template']]->id : null,
                ]
            );

            foreach ($data['triggers'] as $phrase) {
                $sop->triggers()->firstOrCreate(['phrase' => $phrase]);
            }

            foreach ($data['knowledge_bases'] as $title) {
                $sop->knowledgeBases()->syncWithoutDetaching([$knowledgeBases[$title]->id]);
            }

            if ($sop->rules()->count() === 0) {
                $rule = $sop->rules()->create([
                    'name' => $data['rule']['name'],
                    'order' => 0,
                    'tone' => $data['rule']['tone'],
                    'escalation_target' => $data['rule']['escalation_target'],
                ]);

                foreach ($data['rule']['conditions'] as $order => $condition) {
                    $rule->conditions()->create([
                        'field' => $condition['field'],
                        'operator' => $condition['operator'],
                        'boolean_operator' => BooleanOperator::And,
                        'order' => $order,
                    ]);
                }

                foreach ($data['rule']['actions'] as $order => $actionType) {
                    $rule->actions()->create([
                        'action_type' => $actionType,
                        'order' => $order,
                    ]);
                }
            }
        }
    }

    protected function seedAiModels(): void
    {
        $models = [
            'GPT-5' => ['provider' => AiProvider::OpenAi, 'model' => 'gpt-5', 'is_default' => false],
            'GPT-5 Mini' => ['provider' => AiProvider::OpenAi, 'model' => 'gpt-5-mini', 'is_default' => true],
            'Claude Sonnet' => ['provider' => AiProvider::Anthropic, 'model' => 'claude-sonnet-4-5', 'is_default' => false],
            'Gemini 2.5 Pro' => ['provider' => AiProvider::Gemini, 'model' => 'gemini-2.5-pro', 'is_default' => false],
            // AiProvider has no native DeepSeek case; routed through OpenRouter, which proxies DeepSeek models.
            'DeepSeek' => ['provider' => AiProvider::OpenRouter, 'model' => 'deepseek/deepseek-chat', 'is_default' => false],
        ];

        foreach ($models as $name => $data) {
            AiModel::query()->firstOrCreate(
                ['name' => $name],
                [
                    'provider' => $data['provider'],
                    'base_url' => $data['provider']->defaultBaseUrl(),
                    'model' => $data['model'],
                    'temperature' => 0.7,
                    'max_tokens' => 2048,
                    'response_format' => ResponseFormat::Text,
                    'timeout' => 60,
                    'is_default' => $data['is_default'],
                    'enabled' => false,
                    'status' => PublishStatus::Published,
                ]
            );
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
