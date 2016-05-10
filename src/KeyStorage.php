<?php

namespace DeployKey;

class KeyStorage
{
    /**
     * @var string
     */
    private $keyDirectory;

    /**
     * KeyStorage constructor.
     * @param string $sshDirectory
     */
    public function __construct($sshDirectory)
    {
        $this->keyDirectory = $sshDirectory . "/gitdeploy";
    }

    /**
     * 
     */
    public function setupDirectory()
    {
        if (!$this->checkDirectoryExists()) {
            if (!$this->createDirectory()) {
                throw new \RuntimeException('Failed to setup key storage directory');
            }
        }
    }

    /**
     * @return bool
     */
    public function checkDirectoryExists()
    {
        if (file_exists($this->keyDirectory) && is_dir($this->keyDirectory)) {
            return true;
        }

        return false;
    }

    /**
     * @return bool
     */
    public function createDirectory()
    {
        return mkdir($this->keyDirectory) === true;
    }

    /**
     * @param string $name
     * @return string
     */
    private function path($name)
    {
        return $this->keyDirectory . "/" . $name;
    }

    /**
     * @param string $fileName
     * @return bool
     */
    public function checkKeyFileExists($fileName)
    {
        return file_exists($this->path($fileName)) === true;
    }

    /**
     * @param string $fileName
     * @param string $serializedPrivate
     * @param string $serializedPublic
     */
    public function commit($fileName, $serializedPrivate, $serializedPublic)
    {
        $privPath = $this->path($fileName);
        $pubPath = $privPath . ".pub";
        $privateFd = fopen($privPath, "a");
        $publicFd = fopen($pubPath, "a");

        if ($privateFd === false || $publicFd === false) {
            throw new \RuntimeException('Unable to create key file');
        }

        fwrite($privateFd, $serializedPrivate);
        fwrite($publicFd, $serializedPublic);

        chmod($privPath, 0600);
        chmod($pubPath, 0644);

        fclose($privateFd);
        fclose($publicFd);
    }
}