<?php

namespace Voyager\System\Bootstrap;

use Dotenv\Dotenv;
use Dotenv\Exception\InvalidFileException;
use Voyager\Contracts\System\Application;
use Voyager\NutsAndBolts\DataObjects\Env;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\ConsoleOutput;

class LoadEnvironmentVariables
{
    /**
     * Bootstrap the given application.
     *
     * @param  \Voyager\Contracts\System\Application  $app
     * @return void
     */
    public function bootstrap(Application $app): void
    {
        if ($app->configurationIsCached()) {
            return;
        }

        $this->checkForSpecificEnvironmentFile($app);

        try {
            $this->createDotenv($app)->safeLoad();
        } catch (InvalidFileException $e) {
            $this->writeErrorAndDie($e);
        }
    }

    /**
     * Detect if a custom environment file matching the APP_ENV exists.
     *
     * @param  \Voyager\Contracts\System\Application  $app
     * @return void
     */
    protected function checkForSpecificEnvironmentFile(\Voyager\Contracts\System\Application $app): void
    {
        if ($app->runningInConsole() &&
            ($input = new ArgvInput)->hasParameterOption('--env') &&
            $this->setEnvironmentFilePath($app, $app->environmentFile().'.'.$input->getParameterOption('--env'))) {
            return;
        }

        $environment = Env::get('APP_ENV');

        if (! $environment) {
            return;
        }

        $this->setEnvironmentFilePath(
            $app, $app->environmentFile().'.'.$environment
        );
    }

    /**
     * Load a custom environment file.
     *
     * @param  \Voyager\Contracts\System\Application  $app
     * @param  string  $file
     * @return bool
     */
    protected function setEnvironmentFilePath(\Voyager\Contracts\System\Application $app, string $file): bool
    {
        if (is_file($app->environmentPath().'/'.$file)) {
            $app->loadEnvironmentFrom($file);

            return true;
        }

        return false;
    }

    /**
     * Create a Dotenv instance.
     *
     * @param  \Voyager\Contracts\System\Application  $app
     * @return \Dotenv\Dotenv
     */
    protected function createDotenv(\Voyager\Contracts\System\Application $app): Dotenv
    {
        return Dotenv::create(
            Env::getRepository(),
            $app->environmentPath(),
            $app->environmentFile()
        );
    }

    /**
     * Write the error information to the screen and exit.
     *
     * @param  \Dotenv\Exception\InvalidFileException  $e
     * @return never
     */
    protected function writeErrorAndDie(InvalidFileException $e): never
    {
        $output = (new ConsoleOutput)->getErrorOutput();

        $output->writeln('The environment file is invalid!');
        $output->writeln($e->getMessage());

        http_response_code(500);

        exit(1);
    }
}
