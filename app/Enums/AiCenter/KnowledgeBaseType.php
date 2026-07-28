<?php

namespace App\Enums\AiCenter;

enum KnowledgeBaseType: string
{
    case Faq = 'faq';
    case RefundPolicy = 'refund_policy';
    case CompanyPolicy = 'company_policy';
    case Terms = 'terms';
    case ProductDocumentation = 'product_documentation';
    case Troubleshooting = 'troubleshooting';
    case InternalGuide = 'internal_guide';

    public function label(): string
    {
        return match ($this) {
            self::Faq => 'FAQ',
            self::RefundPolicy => 'Refund Policy',
            self::CompanyPolicy => 'Company Policy',
            self::Terms => 'Terms',
            self::ProductDocumentation => 'Product Documentation',
            self::Troubleshooting => 'Troubleshooting',
            self::InternalGuide => 'Internal Guide',
        };
    }
}
