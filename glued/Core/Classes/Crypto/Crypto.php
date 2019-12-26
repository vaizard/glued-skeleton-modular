<?php
namespace Glued\Core\Classes\Crypto;
use Respect\Validation\Validator as v;

class Crypto
{
    public function genkey() {
        $key = random_bytes(SODIUM_CRYPTO_SECRETBOX_KEYBYTES); // 256 bit
    }

    public function encrypt($msg, $key) {
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES); // 24 bytes
        $ciphertext = sodium_crypto_secretbox($msg, $nonce, $key);
        $encoded = base64_encode($nonce . $ciphertext);
        return $encoded;
    }

    public function decrypt($encoded, $key) {
        $decoded = base64_decode($encoded);
        $nonce = mb_substr($decoded, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES, '8bit');
        $ciphertext = mb_substr($decoded, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES, null, '8bit');
        $plaintext = sodium_crypto_secretbox_open($ciphertext, $nonce, $key);
        return $plaintext;
    }
}