<?php
class Crypto {
  private string $key;

  public function __construct(string $keyBase64) {
    $key = base64_decode($keyBase64, true);
    if ($key === false || strlen($key) !== 32) {
      throw new RuntimeException('Invalid AES key: need base64 of 32 bytes.');
    }
    $this->key = $key;
  }

  public function genIv(): string {
    return random_bytes(16); // 16 ไบต์สำหรับ AES-256-CBC
  }

  public function encryptWithIv(string $plaintext, string $iv): string {
    $cipher = openssl_encrypt($plaintext, 'aes-256-cbc', $this->key, OPENSSL_RAW_DATA, $iv);
    if ($cipher === false) throw new RuntimeException('Encryption failed.');
    return $cipher;
  }

  public function decryptWithIv(string $ciphertext, string $iv): string {
    $plain = openssl_decrypt($ciphertext, 'aes-256-cbc', $this->key, OPENSSL_RAW_DATA, $iv);
    if ($plain === false) throw new RuntimeException('Decryption failed.');
    return $plain;
  }
}
