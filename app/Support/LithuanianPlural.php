<?php

namespace App\Support;

// Ported from discount/src/lib/utils.ts's getDiscountWord() — Lithuanian
// numeral agreement (1 nuolaida, 2-9 nuolaidos, 11-19/0 nuolaidų).
class LithuanianPlural
{
    public static function discountWord(int $count): string
    {
        $lastDigit = $count % 10;
        $lastTwoDigits = $count % 100;

        if ($lastTwoDigits >= 11 && $lastTwoDigits <= 19) {
            return 'nuolaidų';
        }

        if ($lastDigit === 1) {
            return 'nuolaida';
        }

        if ($lastDigit >= 2 && $lastDigit <= 9) {
            return 'nuolaidos';
        }

        return 'nuolaidų';
    }

    // Ported from discount/src/lib/utils.ts's getPasiulymaiWord().
    public static function offerWord(int $count): string
    {
        $lastDigit = $count % 10;
        $lastTwoDigits = $count % 100;

        if ($lastTwoDigits >= 11 && $lastTwoDigits <= 19) {
            return 'pasiūlymų';
        }

        if ($lastDigit === 1) {
            return 'pasiūlymas';
        }

        if ($lastDigit >= 2 && $lastDigit <= 9) {
            return 'pasiūlymai';
        }

        return 'pasiūlymų';
    }

    public static function leafletWord(int $count): string
    {
        $lastDigit = $count % 10;
        $lastTwoDigits = $count % 100;

        if ($lastTwoDigits >= 11 && $lastTwoDigits <= 19) {
            return 'leidinių';
        }

        if ($lastDigit === 1) {
            return 'leidinys';
        }

        if ($lastDigit >= 2 && $lastDigit <= 9) {
            return 'leidiniai';
        }

        return 'leidinių';
    }

    // "1 akcija / 5 akcijos / 10 akcijų".
    public static function promotionWord(int $count): string
    {
        $lastDigit = $count % 10;
        $lastTwoDigits = $count % 100;

        if ($lastTwoDigits >= 11 && $lastTwoDigits <= 19) {
            return 'akcijų';
        }

        if ($lastDigit === 1) {
            return 'akcija';
        }

        if ($lastDigit >= 2 && $lastDigit <= 9) {
            return 'akcijos';
        }

        return 'akcijų';
    }

    public static function storeWord(int $count): string
    {
        $lastDigit = $count % 10;
        $lastTwoDigits = $count % 100;

        if ($lastTwoDigits >= 11 && $lastTwoDigits <= 19) {
            return 'parduotuvių';
        }

        if ($lastDigit === 1) {
            return 'parduotuvė';
        }

        if ($lastDigit >= 2 && $lastDigit <= 9) {
            return 'parduotuvės';
        }

        return 'parduotuvių';
    }

    // Accusative ("surinkome 1 pasiūlymą / 3 pasiūlymus / 40 pasiūlymų").
    public static function offerWordAccusative(int $count): string
    {
        return match (self::offerWord($count)) {
            'pasiūlymas' => 'pasiūlymą',
            'pasiūlymai' => 'pasiūlymus',
            default => 'pasiūlymų',
        };
    }

    // "aktyvus pasiūlymas" / "aktyvūs pasiūlymai" / "aktyvių pasiūlymų" —
    // the adjective agrees with offerWord()'s form.
    public static function activeOfferPhrase(int $count): string
    {
        return match (self::offerWord($count)) {
            'pasiūlymas' => 'aktyvus pasiūlymas',
            'pasiūlymai' => 'aktyvūs pasiūlymai',
            default => 'aktyvių pasiūlymų',
        };
    }

    public static function formatCount(int $count): string
    {
        return str_replace(',', ' ', number_format($count));
    }
}
