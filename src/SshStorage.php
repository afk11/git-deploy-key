<?php

namespace DeployKey;


class SshStorage
{
    /**
     * @var string
     */
    private $sshDir;

    /**
     * @var KeyStorage
     */
    private $keyStorage;

    /**
     * SshStorage constructor.
     * @param string $sshDir
     */
    public function __construct($sshDir)
    {
        $this->sshDir = $sshDir;
    }

    /**
     * @return bool
     */
    public function checkDirectoryExists()
    {
        if (file_exists($this->sshDir) && is_dir($this->sshDir)) {
            return true;
        }

        return false;
    }

    /**
     * @param $file
     * @return string
     */
    private function path($file)
    {
        return $this->sshDir . "/" . $file;
    }

    /**
     * @return bool
     */
    public function checkConfigExists()
    {
        $path = $this->path('config');
        if (file_exists($path) && is_file($path)) {
            $fd = fopen($path, "w");
            if ($fd) {
                fclose($fd);
                return true;
            }
        }

        return false;
    }

    /**
     * @return KeyStorage
     */
    public function keyStorage()
    {
        if (null === $this->keyStorage) {
            $this->keyStorage = new KeyStorage($this->sshDir);
        }

        return $this->keyStorage;
    }

    /**
     * @param $configText
     * @return bool
     */
    public function commit($configText)
    {
        $fd = fopen($this->path('config'), "a");
        if (!$fd) {
            throw new \RuntimeException('Failed to open SSH config');
        }
        
        fwrite($fd, $configText);
        fclose($fd);
        return true;
    }
}