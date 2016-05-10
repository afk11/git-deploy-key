<?php

namespace DeployKey\Console\Command;

use DeployKey\Curves;
use DeployKey\Serializer\EncryptedPrivateKeySerializer;
use DeployKey\Serializer\SshPublicKeySerializer;
use DeployKey\SshStorage;
use Mdanter\Ecc\Curves\CurveFactory;
use Mdanter\Ecc\Curves\NamedCurveFp;
use Mdanter\Ecc\EccFactory;
use Mdanter\Ecc\Primitives\GeneratorPoint;
use Mdanter\Ecc\Serializer\Point\UncompressedPointSerializer;
use Mdanter\Ecc\Serializer\PrivateKey\DerPrivateKeySerializer;
use Mdanter\Ecc\Serializer\PrivateKey\PemPrivateKeySerializer;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

class CreateCommand extends Command
{
    /**
     * @return void
     */
    protected function configure()
    {
        $this->setName('create')
            ->addArgument('url', InputArgument::REQUIRED, 'Git repository url')
            ->addArgument('name', InputArgument::OPTIONAL, 'Assign an SSH nickname', null)
            ->addOption('no-password', null, InputOption::VALUE_NONE, "Don't encrypt the key")
            ->addOption('ec-curve', null, InputOption::VALUE_OPTIONAL, 'Elliptic curve', 'nistp256')
            ->addOption('ssh-dir', null, InputOption::VALUE_OPTIONAL, 'SSH directory', getenv('HOME') . "/.ssh")
            ->addOption('ssh-port',null,  InputOption::VALUE_OPTIONAL, 'SSH port', 22)
        ;
    }

    /**
     * @param int $port
     * @return bool
     */
    public function checkSshPort($port)
    {
        if (is_int($port) && ($port > 0 || $port < 65535)) {
            return true;
        }

        return false;
    }

    /**
     * @param string $name
     * @return bool
     */
    public function checkCurveSupported($name)
    {
        try {
            CurveFactory::getCurveByName($name);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * @param NamedCurveFp $curve
     * @return string
     */
    public function getCanonicalCurveName(NamedCurveFp $curve)
    {
        switch ($curve->getName()) {
            case 'nist-p256':
                return 'nistp256';
            case 'nist-p384':
                return 'nistp384';
            case 'nist-p521':
                return 'nistp521';
            default:
                throw new \RuntimeException('Key not supported by git');
        }
    }

    /**
     * @param string $url
     * @return array
     */
    public function parseGitUrl($url)
    {
        $explode = explode("@", $url);
        if (2 !== count($explode)) {
            throw new \RuntimeException('Git url missing user');
        }

        $user = $explode[0];
        $explode = explode(":", $explode[1]);
        if (2 !== count($explode)) {
            throw new \RuntimeException('Git url broken');
        }

        $host = $explode[0];
        $path = $explode[1];

        return [$user, $host, $path];
    }

    /**
     * @param string $sshHost
     * @param string $hostname
     * @param string $user
     * @param int $port
     * @param string $keyPath
     * @return string
     */
    public function createConfigEntry($sshHost, $hostname, $user, $port, $keyPath)
    {
        return "\nHost $sshHost
Hostname $hostname
User $user
Port $port
IdentityFile $keyPath\n";
    }

    /**
     * @param string $host
     * @param string $path
     * @return string
     */
    public function createSshHost($host, $path)
    {
        return sprintf("%s-%s", explode(".", $host)[0], preg_replace('/[^a-zA-Z0-9]+/', '-', $path));
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @param QuestionHelper $questionHelper
     * @return string
     */
    public function promptForPassword(InputInterface $input, OutputInterface $output, QuestionHelper $questionHelper)
    {
        $question = new Question('Password: ');
        $question->setValidator(function ($answer) {
            if (strlen($answer) === 0) {
                throw new \RuntimeException(
                    'Password should not be empty!'
                );
            }
            return $answer;
        });

        $question->setHidden(true);
        $question->setMaxAttempts(10);
        $firstPassword = $questionHelper->ask($input, $output, $question);

        $question = new Question('Password again:');
        $question->setValidator(function ($answer) use ($firstPassword) {
            if (strlen($answer) === 0) {
                throw new \RuntimeException(
                    'Password should not be empty!'
                );
            }

            if ($answer !== $firstPassword) {
                throw new \RuntimeException(
                    'Passwords dont match!'
                );
            }

            return $answer;
        });

        $question->setHidden(true);
        $question->setMaxAttempts(1);

        $secondPassword = $questionHelper->ask($input, $output, $question);

        return $secondPassword;
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @param QuestionHelper $questionHelper
     * @return bool
     */
    public function promptWhetherToProceed(InputInterface $input, OutputInterface $output, QuestionHelper $questionHelper)
    {
        $question = new Question('Is this ok [y/n]: ');
        $question->setValidator(function ($answer) {
            switch (strtolower($answer)) {
                case 'y':
                    return true;
                case 'n':
                    return false;
                default:
                    throw new \InvalidArgumentException('Bad selection, please enter y or n');
            }
        });

        return $questionHelper->ask($input, $output, $question);
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @param QuestionHelper $helper
     * @param string $curveName
     * @param bool $useEncryption
     * @return array
     */
    public function generateKeyData(InputInterface $input, OutputInterface $output, QuestionHelper $helper, $curveName, $useEncryption)
    {
        if (!is_bool($useEncryption)) {
            throw new \InvalidArgumentException('useEncryption parameter must be a boolean');
        }

        /**
         * @var GeneratorPoint $generator
         */
        list (, $generator) = Curves::load($curveName);
        $key = $generator->createPrivateKey();

        if ($useEncryption) {
            $password = $this->promptForPassword($input, $output, $helper);
            $method = 'AES-128-CBC';
            $iv = random_bytes(16);

            $serializer = new EncryptedPrivateKeySerializer(new DerPrivateKeySerializer());
            $keyData = $serializer->serialize($key, $password, $method, $iv);
        } else {
            $serializer = new PemPrivateKeySerializer(new DerPrivateKeySerializer());
            $keyData = $serializer->serialize($key);
        }

        $publicKey = $key->getPublicKey();
        $publicSerializer = new SshPublicKeySerializer(new UncompressedPointSerializer(EccFactory::getAdapter()));
        $publicData = $publicSerializer->serialize($curveName, $publicKey);

        $localUser = posix_getpwuid(posix_geteuid());
        $localHost = gethostname();

        $publicData = sprintf("ecdsa-sha2-%s %s %s@%s\n", $curveName, $publicData, $localUser['name'], $localHost);

        return [$keyData, $publicData];
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $url = $input->getArgument('url');
        list ($user, $host, $path) = $this->parseGitUrl($url);

        $sshDir = $input->getOption('ssh-dir');
        // Strip trailing slash
        $sshDir = substr($sshDir, -1) === '/' ? substr($sshDir, 0, -1) : $sshDir;

        $sshStorage = new SshStorage($sshDir);
        if (!$sshStorage->checkDirectoryExists()) {
            throw new \RuntimeException("Unable to read SSH directory. Check it exists, and that it allows read/write access");
        }

        if (!$sshStorage->checkConfigExists()) {
            throw new \RuntimeException("SSH config not found. Create one using: `touch " . $sshDir . "/config`");
        }
        
        $sshPort = $input->getOption('ssh-port');
        if (!$this->checkSshPort($sshPort)) {
            throw new \RuntimeException('Invalid SSH port');
        }

        $sshHost = $input->getArgument('name');
        if (is_null($sshHost)) {
            $sshHost = $this->createSshHost($host, $path);
        }
        
        $keyStorage = $sshStorage->keyStorage();
        $keyStorage->setupDirectory();
        if ($keyStorage->checkKeyFileExists($sshHost)) {
            throw new \RuntimeException('There is already a key belonging to: ' . $sshHost);
        }

        $privateKeyPath = sprintf("%s/gitdeploy/%s", $sshDir, $sshHost);
        $publicKeyPath = $privateKeyPath . ".pub";
        $output->writeln("<info>Confirm details: </info>\n");
        $output->writeln('    Private key file: ' . $privateKeyPath);
        $output->writeln('    Public key file: ' . $publicKeyPath . PHP_EOL);
        $output->writeln("The following config entry will be written:");
        $configText = $this->createConfigEntry($sshHost, $host, $user, $sshPort, $privateKeyPath);
        $output->writeln($configText);

        $questionHelper = new QuestionHelper();
        if ($this->promptWhetherToProceed($input, $output, $questionHelper)) {
            $doEncrypt = $input->getOption('no-password') === false;
            $curveName = $input->getOption('ec-curve');
            list ($privateData, $publicData) = $this->generateKeyData($input, $output, $questionHelper, $curveName, $doEncrypt);

            $sshStorage->commit($configText);
            $keyStorage->commit($sshHost, $privateData, $publicData);

            if ($host === 'github.com') {
                $repo = substr($path, -4) === '.git' ? substr($path, 0, -4) : $path;
                $output->writeln("You're using Github! You can paste the key in here: https://github.com/" . $repo . "/settings/keys");
                $output->writeln("{$publicData}");
            }

            $output->writeln("<info>Saved key to disk</info>");

        } else {
            $output->writeln("No changes made");
        }
    }
}
