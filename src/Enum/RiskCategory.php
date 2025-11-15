<?php
declare(strict_types=1);

namespace Kodegen\Ipqs\Enum;

/**
 * Risk categorization for tri-risk evaluation
 *
 * Based on average fraud score:
 * - LOW: avgScore <= 50
 * - MEDIUM: avgScore 51-75
 * - HIGH: avgScore >= 76
 *
 * See SPEC_IPQUALITY_TRIRISK_RULES.md for full evaluation logic
 */
enum RiskCategory: string
{
    case LOW = 'LOW';
    case MEDIUM = 'MEDIUM';
    case HIGH = 'HIGH';
}
