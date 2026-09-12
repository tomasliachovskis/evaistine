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

    public static function formatCount(int $count): string
    {
        return str_replace(',', ' ', number_format($count));
    }

    public static function minuteWord(int $count): string
    {
        $lastDigit = $count % 10;
        $lastTwoDigits = $count % 100;

        if ($lastTwoDigits >= 11 && $lastTwoDigits <= 19) {
            return 'minučių';
        }

        if ($lastDigit === 1) {
            return 'minutė';
        }

        if ($lastDigit >= 2 && $lastDigit <= 9) {
            return 'minutės';
        }

        return 'minučių';
    }

    public static function hourWord(int $count): string
    {
        $lastDigit = $count % 10;
        $lastTwoDigits = $count % 100;

        if ($lastTwoDigits >= 11 && $lastTwoDigits <= 19) {
            return 'valandų';
        }

        if ($lastDigit === 1) {
            return 'valanda';
        }

        if ($lastDigit >= 2 && $lastDigit <= 9) {
            return 'valandos';
        }

        return 'valandų';
    }
}
