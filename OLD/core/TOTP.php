<?php
namespace Core;

class TOTP
{
    private static $base32Map = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    /**
     * Generate a secret key (Base32 encoded)
     */
    public static function generateSecret($length = 16)
    {
        $secret = '';
        for ($i = 0; $i < $length; $i++) {
            $secret .= self::$base32Map[random_int(0, 31)];
        }
        return $secret;
    }

    /**
     * Calculate the code for a given secret and time slice
     */
    public static function getCode($secret, $timeSlice = null)
    {
        if ($timeSlice === null) {
            $timeSlice = floor(time() / 30);
        }

        $secretKey = self::base32Decode($secret);

        // Pack time into 8-byte string
        $time = chr(0) . chr(0) . chr(0) . chr(0) . pack('N*', $timeSlice);

        // HMAC-SHA1
        $hmac = hash_hmac('sha1', $time, $secretKey, true);

        // Get offset
        $offset = ord(substr($hmac, -1)) & 0x0f;

        // Extract 4 bytes
        $hashPart = substr($hmac, $offset, 4);

        // Unpack
        $value = unpack('N', $hashPart);
        $value = $value[1];

        // Mask MSB
        $value = $value & 0x7FFFFFFF;

        $modulo = pow(10, 6);
        return str_pad($value % $modulo, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Verify a code
     */
    public static function verify($secret, $code, $discrepancy = 1)
    {
        $currentTimeSlice = floor(time() / 30);

        for ($i = -$discrepancy; $i <= $discrepancy; $i++) {
            $calculatedCode = self::getCode($secret, $currentTimeSlice + $i);
            if (hash_equals($calculatedCode, $code)) {
                return true;
            }
        }
        return false;
    }

    private static function base32Decode($secret)
    {
        if (empty($secret))
            return '';

        $base32chars = self::$base32Map;
        $base32charsFlipped = array_flip(str_split($base32chars));

        $paddingCharCount = substr_count($secret, '=');
        $allowedValues = array(6, 4, 3, 1, 0);
        if (!in_array($paddingCharCount, $allowedValues)) {
            return false;
        }

        for ($i = 0; $i < 4; $i++) {
            if (
                $paddingCharCount == $allowedValues[$i] &&
                substr($secret, -($allowedValues[$i])) != str_repeat('=', $allowedValues[$i])
            ) {
                return false;
            }
        }

        $secret = str_replace('=', '', $secret);
        $secret = str_split($secret);
        $binaryString = '';

        foreach ($secret as $char) {
            if (!isset($base32charsFlipped[$char]))
                return false;
            $binaryString .= str_pad(decbin($base32charsFlipped[$char]), 5, '0', STR_PAD_LEFT);
        }

        $eightBits = str_split($binaryString, 8);
        $result = '';
        foreach ($eightBits as $chunk) {
            if (strlen($chunk) < 8)
                break;
            $result .= chr(bindec($chunk));
        }

        return $result;
    }

    /**
     * Get Provisioning URI for QR Code
     */
    public static function getProvisioningUri($secret, $name)
    {
        return 'otpauth://totp/' . rawurlencode($name) . '?secret=' . $secret . '&issuer=ConstructionSystem';
    }
}
