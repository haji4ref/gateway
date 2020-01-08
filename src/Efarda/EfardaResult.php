<?php

namespace Larabookir\Gateway\Efarda;

class EfardaResult {

    public static $errors
        = [
            0 => 'تراکنش با موفقیت انجام شد.',
        ];

    public static function errorMessage($errorId)
    {
        return isset(self::$errors[$errorId]) ? self::$errors[$errorId] : $errorId;
    }
}
