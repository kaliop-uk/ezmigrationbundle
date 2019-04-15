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
        $parentClass = get_parent_class($this);
        $this_prefix = \Closure::bind(function(ProcessBuilder $builder){return $builder->prefix;}, null, $parentClass);
        $this_prefix = $this_prefix($this);
        $this_arguments = \Closure::bind(function(ProcessBuilder $builder){return $builder->arguments;}, null, $parentClass);
        $this_arguments = $this_arguments($this);
        $this_options = \Closure::bind(function(ProcessBuilder $builder){return $builder->options;}, null, $parentClass);
        $this_options = $this_options($this);
        $this_inheritEnv = \Closure::bind(function(ProcessBuilder $builder){return $builder->inheritEnv;}, null, $parentClass);
        $this_inheritEnv = $this_inheritEnv($this);
        $this_env = \Closure::bind(function(ProcessBuilder $builder){return $builder->env;}, null, $parentClass);
        $this_env = $this_env($this);
        $this_cwd = \Closure::bind(function(ProcessBuilder $builder){return $builder->cwd;}, null, $parentClass);
        $this_cwd = $this_cwd($this);
        $this_input = \Closure::bind(function(ProcessBuilder $builder){return $builder->input;}, null, $parentClass);
        $this_input = $this_input($this);
        $this_timeout = \Closure::bind(function(ProcessBuilder $builder){return $builder->timeout;}, null, $parentClass);
        $this_timeout = $this_timeout($this);
        $this_outputDisabled = \Closure::bind(function(ProcessBuilder $builder){return $builder->outputDisabled;}, null, $parentClass);
        $this_outputDisabled = $this_outputDisabled($this);

        if (0 === count($this_prefix) && 0 === count($this_arguments)) {
            throw new LogicException('You must add() command arguments before calling getProcess().');
        }

        $options = $this_options;

        $arguments = array_merge($this_prefix, $this_arguments);
        $script = implode(' ', array_map(array('Symfony\\Component\\Process\\ProcessUtils', 'escapeArgument'), $arguments));

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
