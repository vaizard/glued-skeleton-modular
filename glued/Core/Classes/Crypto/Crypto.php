<?php
namespace Glued\Core\Classes\Crypto;
use Respect\Validation\Validator as v;

class Crypto
{
    public function genkey_base64() {
        $key = sodium_bin2base64(random_bytes(SODIUM_CRYPTO_SECRETBOX_KEYBYTES), SODIUM_BASE64_VARIANT_ORIGINAL); // 256 bit
        return $key;
    }

    public function encrypt($msg, $key) {
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES); // 24 bytes
        $ciphertext = sodium_crypto_secretbox($msg, $nonce, sodium_base642bin($key, SODIUM_BASE64_VARIANT_ORIGINAL));
        $encoded = sodium_bin2base64($nonce . $ciphertext, SODIUM_BASE64_VARIANT_ORIGINAL);
        sodium_memzero($msg);
        sodium_memzero($key);
        return $encoded;
    }

    public function decrypt($encoded, $key) {
        $decoded = sodium_base642bin($encoded, SODIUM_BASE64_VARIANT_ORIGINAL);
        $nonce = mb_substr($decoded, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES, '8bit');
        $ciphertext = mb_substr($decoded, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES, null, '8bit');
        $plaintext = sodium_crypto_secretbox_open($ciphertext, $nonce, sodium_base642bin($key, SODIUM_BASE64_VARIANT_ORIGINAL));
        sodium_memzero($ciphertext);
        sodium_memzero($key);
        return $plaintext;
    }
}