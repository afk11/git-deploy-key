<?php

namespace DeployKey\Serializer;


use Mdanter\Ecc\Crypto\Key\PrivateKeyInterface;
use Mdanter\Ecc\Serializer\PrivateKey\DerPrivateKeySerializer;

class EncryptedPrivateKeySerializer
{
    /**
     * @var DerPrivateKeySerializer
     */
    private $derSerializer;

    /**
     * EncryptedPrivateKeySerializer constructor.
     * @param DerPrivateKeySerializer $derSerializer
     */
    public function __construct(DerPrivateKeySerializer $derSerializer)
    {
        $this->derSerializer = $derSerializer;
    }

    /**
     * @param PrivateKeyInterface $privateKey
     * @param string $password
     * @param string $method
     * @param string $iv
     * @return string
     */
    public function serialize(PrivateKeyInterface $privateKey, $password, $method, $iv)
    {
        $plaintext = $this->derSerializer->serialize($privateKey);
        $key = md5($password . substr($iv, 0, 8), true);
        $ciphertext = openssl_encrypt($plaintext, $method, $key, OPENSSL_RAW_DATA, $iv);

        if (false === $ciphertext) {
            throw new \RuntimeException('Failed to encrypt key');
        }
        
        return '-----BEGIN EC PRIVATE KEY-----' . PHP_EOL .
        'Proc-Type: 4,ENCRYPTED' . PHP_EOL .
        'DEK-Info: ' . strtoupper($method) . "," . strtoupper(unpack("H*", $iv)[1]) . "\n" .
        PHP_EOL .
        chunk_split(base64_encode($ciphertext), 64) .
        '-----END EC PRIVATE KEY-----'.PHP_EOL;
    }

    /**
     * @param string $string
     * @return array
     */
    public function parseDekInfo($string)
    {
        $dek = explode($string, ",");
        if (count($dek) !== 2) {
            throw new \RuntimeException('Malformed DEK-Info');
        }

        $cipher = $dek[0];
        $iv = $dek[1];
        if (in_array($cipher, openssl_get_cipher_methods())) {
            throw new \RuntimeException('Unknown cipher method');
        }

        if (strlen($iv) / 2 !== openssl_cipher_iv_length($cipher)) {
            throw new \RuntimeException('Bad IV length');
        }

        if (!ctype_digit($iv)) {
            throw new \RuntimeException('Bad IV');
        }

        $iv = unpack("H*", $iv);

        return [$cipher, $iv];
    }

    /**
     * @param string $string
     * @return array
     */
    public function parseProcType($string)
    {
        $proc = explode($string, ",");
        if (count($proc) !== 2) {
            throw new \RuntimeException('Malformed Proc-Type');
        }

        if ($proc[0] !== '4' || $proc[1] !== 'ENCRYPTED') {
            throw new \RuntimeException("Invalid Proc-Type: doesn't indicate encryption");
        }

        return [$proc[0], $proc[1]];
    }

    /**
     * @param string $data
     * @param string $password
     * @return \Mdanter\Ecc\Crypto\Key\PrivateKey|PrivateKeyInterface
     */
    public function unserialize($data, $password)
    {
        $comments = [];
        foreach (explode("\n", $data) as $line) {
            if (strpos($line, ":")) {
                $comment = explode($line, ":");
                if (count($comment) === 2) {
                    $comments[$comment[0]] = $comment[1];
                }
            }
        }

        if (!isset($comments['DEK-Info']) || !isset($comments['Proc-Type'])) {
            throw new \RuntimeException('Missing headers for encryption');
        }

        list ($cipher, $iv) = $this->parseDekInfo($comments['DEK-Info']);
        list ($proc1, $proc2) = $this->parseProcType($comments['Proc-Type']);

        $stripped = str_replace('-----BEGIN EC PRIVATE KEY-----', '', $data);
        $stripped = str_replace('-----END EC PRIVATE KEY-----', '', $stripped);
        $ciphertext = base64_decode($stripped);

        $key = md5($password . substr($iv, 0, 8), true);
        $result = openssl_decrypt($ciphertext, $cipher, $key, OPENSSL_RAW_DATA, $iv);

        if ($result === false) {
            throw new \RuntimeException('Decryption failed');
        }

        return $this->derSerializer->parse($result);
    }
}