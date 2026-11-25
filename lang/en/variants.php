<?php

declare(strict_types=1);

return [
    'sections' => [
        'extra' => 'Other variants',
        'options' => 'How this variant differs',
        'branch_prices' => 'Branch prices',
    ],
    'extra_help' => 'Add a variant for every size, flavour or pack. The product above is the first variant.',
    'default_options' => 'First variant: how it differs',
    'default_options_help' => 'Describe the first variant too (for example size = 1 kg), so customers can tell the variants apart.',
    'option_name' => 'Attribute (e.g. size)',
    'option_value' => 'Value (e.g. 1 kg)',
    'add_variant' => 'Add variant',
    'remove_variant' => 'Remove variant',
    'add_option' => 'Add attribute',
    'remove_option' => 'Remove attribute',
    'barcodes' => 'Barcodes (one per line, the first is the main one)',
    'extra_barcodes' => 'More barcodes (one per line)',
    'branch_prices_help' => 'Leave a field empty to use the normal selling price in that branch.',
    'variant' => 'Variant',
    'base_price' => 'Normal price',

    'errors' => [
        'duplicate_sku' => 'The same SKU is used twice in this form.',
        'sku_taken' => 'This SKU already belongs to another variant.',
        'duplicate_barcode' => 'The same barcode is used twice in this form',
        'options_required' => 'When a product has several variants, each one needs at least one attribute (for example size = 1 kg).',
        'duplicate_options' => 'Two variants have exactly the same attributes.',
        'invalid_variant' => 'This variant does not belong to the product.',
        'invalid_branch' => 'You do not have access to this branch.',
        'branch_price_above_mrp' => 'A branch price cannot be higher than the MRP.',
    ],
];
