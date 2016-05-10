<?php

namespace DeployKey\Console;

use DeployKey\Console\Command\CreateCommand;
use Mdanter\Ecc\Console\Commands\DumpAsnCommand;

class Application extends \Symfony\Component\Console\Application
{

    /**
     * @return array|\Symfony\Component\Console\Command\Command[]
     */
    protected function getDefaultCommands()
    {
        $commands = parent::getDefaultCommands();
        $commands[] = new CreateCommand();
        return $commands;
    }
}
