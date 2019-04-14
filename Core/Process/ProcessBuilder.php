<?php

namespace Kaliop\eZMigrationBundle\Core\Process;

use Symfony\Component\Process\Exception\LogicException;
use Symfony\Component\Process\ProcessBuilder as BaseProcessBuilder;

/**
 * All we want is to make the ProcessBuilder return an eZMigrationBundle Process.
 * Since all the members of the original ProcessBuilder are private, this turns out to be more complex that simply
 * overriding the getProcess() method
 */
class ProcessBuilder extends BaseProcessBuilder
{
    /**
     * Creates a Process instance and returns it.
     *
     * @return Process
     *
     * @throws LogicException In case no arguments have been provided
     */
    public function getProcess()
    {
        /** @var array $this_prefix */
        $this_prefix = \Closure::bind(function(ProcessBuilder $builder){return $builder->prefix;}, null, $this);
        /** @var array $this_arguments */
        $this_arguments = \Closure::bind(function(ProcessBuilder $builder){return $builder->arguments;}, null, $this);
        /** @var array $this_options */
        $this_options = \Closure::bind(function(ProcessBuilder $builder){return $builder->options;}, null, $this);
        $this_inheritEnv = \Closure::bind(function(ProcessBuilder $builder){return $builder->inheritEnv;}, null, $this);
        $this_env = \Closure::bind(function(ProcessBuilder $builder){return $builder->env;}, null, $this);
        $this_cwd = \Closure::bind(function(ProcessBuilder $builder){return $builder->cwd;}, null, $this);
        $this_input = \Closure::bind(function(ProcessBuilder $builder){return $builder->input;}, null, $this);
        $this_timeout = \Closure::bind(function(ProcessBuilder $builder){return $builder->timeout;}, null, $this);
        $this_outputDisabled = \Closure::bind(function(ProcessBuilder $builder){return $builder->outputDisabled;}, null, $this);

        if (0 === count($this_prefix) && 0 === count($this_arguments)) {
            throw new LogicException('You must add() command arguments before calling getProcess().');
        }

        $options = $this_options;

        $arguments = array_merge($this_prefix, $this_arguments);
        $script = implode(' ', array_map(array(__NAMESPACE__.'\\ProcessUtils', 'escapeArgument'), $arguments));

        if ($this_inheritEnv) {
            // include $_ENV for BC purposes
            $env = array_replace($_ENV, $_SERVER, $this_env);
        } else {
            $env = $this_env;
        }

        $process = new Process($script, $this_cwd, $env, $this_input, $this_timeout, $options);

        if ($this_outputDisabled) {
            $process->disableOutput();
        }

        return $process;
    }
}
