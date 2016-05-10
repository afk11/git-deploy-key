<?php

namespace DeployKey\Console\Command;

use DeployKey\Curves;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ListCurvesCommand extends Command
{
    /**
     * @return void
     */
    protected function configure()
    {
        $this->setName('curves');
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
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln('<comment>Supported Curves</comment>');
        $output->writeln('');
        $output->writeln("The following curves are supported: ");
        foreach (Curves::listAll() as $curve) {
            $output->writeln(sprintf("  <info>%s</info>", $curve));
        }
    }
}
