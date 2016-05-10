<?php

namespace DeployKey;


use Mdanter\Ecc\Curves\CurveFactory;

class Curves
{
    /**
     * @return array
     */
    public static function listAll()
    {
        return ['nistp256', 'nistp384', 'nistp521'];
    }

    /**
     * @param string $curveName
     * @return array
     */
    public static function load($curveName)
    {
        switch ($curveName) {
            case 'nistp256':
                return [
                    CurveFactory::getCurveByName('nist-p256'),
                    CurveFactory::getGeneratorByName('nist-p256')
                ];
            case 'nistp384':
                return [
                    CurveFactory::getCurveByName('nist-p384'),
                    CurveFactory::getGeneratorByName('nist-p384')
                ];
            case 'nistp521':
                return [
                    CurveFactory::getCurveByName('nist-p521'),
                    CurveFactory::getGeneratorByName('nist-p521')
                ];
            default:
                throw new \InvalidArgumentException('Unknown or unsupported curve');
        }
    }
}