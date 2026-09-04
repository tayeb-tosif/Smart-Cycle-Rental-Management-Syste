<?php
/**
 * Pricing Configuration
 * Smart Cycle Rental Management System
 * 
 * Centralized pricing rules to avoid hard-coded values across multiple files.
 */

// Base unlock / rental fee in BDT (Tk)
define('PRICING_BASE_FEE', 20.00);

// Additional fee per unit time slot in BDT (Tk)
define('PRICING_ADDITIONAL_FEE_PER_SLOT', 5.00);

// Minutes per time slot for additional fee
define('PRICING_MINUTES_PER_SLOT', 30);

// Currency symbol
define('CURRENCY_SYMBOL', 'Tk');

/**
 * Calculates rental bill based on duration in minutes.
 * Formula:
 * Duration <= 30 mins: Base Fee (20 Tk) + 1 Slot (5 Tk) = 25 Tk
 * Duration <= 60 mins: Base Fee (20 Tk) + 2 Slots (10 Tk) = 30 Tk
 * Duration <= 90 mins: Base Fee (20 Tk) + 3 Slots (15 Tk) = 35 Tk
 * 
 * @param int $durationMinutes Total duration in minutes (minimum 1 minute)
 * @return array ['base_fee' => float, 'additional_fee' => float, 'total_before_discount' => float, 'slots' => int]
 */
function calculateRentalFare(int $durationMinutes): array {
    $durationMinutes = max(1, $durationMinutes);
    
    // Calculate slots (every 30 minutes or fraction thereof)
    $slots = (int) ceil($durationMinutes / PRICING_MINUTES_PER_SLOT);
    $additionalFee = $slots * PRICING_ADDITIONAL_FEE_PER_SLOT;
    $baseFee = PRICING_BASE_FEE;
    $total = $baseFee + $additionalFee;

    return [
        'base_fee'              => $baseFee,
        'additional_fee'        => $additionalFee,
        'slots'                 => $slots,
        'duration_minutes'      => $durationMinutes,
        'total_before_discount' => $total
    ];
}
