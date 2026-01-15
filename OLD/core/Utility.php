<?php
namespace Core;

class Utility
{
    /**
     * Sanitize output
     */
    public static function e($string)
    {
        return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
    }

    /**
     * Format currency
     */
    public static function formatCurrency($amount, $currency = 'DKK')
    {
        return number_format($amount, 2, ',', '.') . ' ' . $currency;
    }

    /**
     * Format date
     */
    public static function formatDate($date, $format = 'd/m-Y')
    {
        if (!$date)
            return '';
        return date($format, strtotime($date));
    }

    /**
     * Debug helper
     */
    public static function dd($data)
    {
        echo '<pre>';
        var_dump($data);
        echo '</pre>';
        die();
    }

    /**
     * JSON Response Helper
     */
    public static function jsonResponse($data, $status = 200)
    {
        header('Content-Type: application/json');
        http_response_code($status);
        echo json_encode($data);
        exit;
    }
}
