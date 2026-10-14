<?php

// Product facts (docs/product-facts.md): which model turns a product page's
// text into facts, and what the candidate models cost.

return [
    // Used by product-facts:extract. A "gemini-" prefix goes to the Gemini
    // API, anything else to OpenAI. Empty = extraction off (the command only
    // links pages to products), until the owner picks a model on cost.
    'model' => env('PRODUCT_FACTS_MODEL'),

    // USD per 1M tokens [input, output], list prices checked 2026-10-14
    // (OpenAI model pages; Gemini 3.5 from price trackers, not Google's own
    // page). Only for the cost estimate in product-facts:benchmark/extract.
    'prices' => [
        'gpt-5-nano' => [0.05, 0.40],
        'gpt-5-mini' => [0.25, 2.00],
        'gpt-5' => [1.25, 10.00],
        'gemini-2.5-flash-lite' => [0.10, 0.40],
        'gemini-2.5-flash' => [0.30, 2.50],
        'gemini-3-flash-preview' => [0.50, 3.00],
        'gemini-3.5-flash-lite' => [0.30, 2.50],
        'gemini-3.5-flash' => [1.50, 9.00],
    ],
];
