<?php

namespace DeployKey\Serializer;


use Mdanter\Ecc\Crypto\Key\PublicKeyInterface;
use Mdanter\Ecc\Curves\CurveFactory;
use Mdanter\Ecc\Curves\NamedCurveFp;
use Mdanter\Ecc\EccFactory;
use Mdanter\Ecc\Serializer\Point\UncompressedPointSerializer;

class SshPublicKeySerializer
{
    /**
     * @var UncompressedPointSerializer
     */
    private $pointSerializer;

    /**
     * SshPublicKeySerializer constructor.
     * @param UncompressedPointSerializer $pointSerializer
     */
    public function __construct(UncompressedPointSerializer $pointSerializer)
    {
        $this->pointSerializer = $pointSerializer;
    }

    /**
     * @param string $curveName
     * @param PublicKeyInterface $publicKey
     * @return string
     */
    public function serialize($curveName, PublicKeyInterface $publicKey)
    {
        $ecdsa = 'ecdsa-sha2-' . $curveName;
        $key = hex2bin($this->pointSerializer->serialize($publicKey->getPoint()));

        $serialized  = pack("N", strlen($ecdsa)) . $ecdsa;
        $serialized .= pack("N", strlen($curveName)) . $curveName;
        $serialized .= pack("N", strlen($key)) . $key;

        return base64_encode($serialized);
    }

    /**
     * @param $base64
     * @return array
     */
    public function unserialize($base64)
    {
        $binary = base64_decode($base64);
        $values = [];
        $pos = 0;
        $end = strlen($binary);
        for ($i = 0; $i < 3; $i++) {
            if ($end - $pos < 4) {
                throw new \RuntimeException('Invalid data');
            }
            $length = unpack("N", substr($binary, $pos, 4))[1];
            $pos += 4;

            if ($end - $pos < $length) {
                throw new \RuntimeException('Invalid data');
            }

            $value = substr($binary, $pos, $length);
            $pos += $length;
            $values[$i] = $value;
        }

        $curveName = $values[1];
        $curve = CurveFactory::getCurveByName($curveName);
        $publicKey = $this->pointSerializer->unserialize($curve, $values[2]);

        return [$curve, $publicKey];
    }
}