<?php

namespace Afk11\DeployKey\Console;

use Afk11\DeployKey\Console\Command\CreateCommand;
use Afk11\DeployKey\Console\Command\ListCurvesCommand;

class Application extends \Symfony\Component\Console\Application
{

    /**
     * @return array|\Symfony\Component\Console\Command\Command[]
     */
    protected function getDefaultCommands()
    {
        $commands = parent::getDefaultCommands();
        $commands[] = new CreateCommand();
        $commands[] = new ListCurvesCommand();
        return $commands;
    }
}
