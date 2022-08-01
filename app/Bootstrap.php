<?php

declare(strict_types=1);

namespace App;

use Nette\Bootstrap\Configurator;


class Bootstrap
{
    public static function boot(): Configurator
    {
        $configurator = new Configurator;
        $netteDebug = getenv('NETTE_DEBUG');

        if ($_SERVER["SERVER_NAME"] === 'reproman.loc' || $_SERVER['SERVER_NAME'] === 'dev.reproman.cz' || $_SERVER['SERVER_NAME'] === 'localhost') {
            $devStatus = true;
        } else {
            $devStatus = false;
        }

        $configurator->setDebugMode($devStatus);
        $appDir = dirname(__DIR__);

        //$configurator->setDebugMode('secret@23.75.345.200'); // enable for your remote IP
        $configurator->setTimeZone('Europe/Prague');
        $configurator->enableTracy($appDir . '/log');
        $configurator->setTempDirectory($appDir . '/temp');

        $configurator->createRobotLoader()
            ->addDirectory(__DIR__)
            ->register();

        $configurator->addConfig($appDir . '/config/common.neon');
        $configurator->addConfig($appDir . '/config/secret.neon');
        if ($_SERVER['SERVER_NAME'] === 'localhost' || $_SERVER['SERVER_NAME'] === 'reproman.loc') {
            $configurator->addConfig($appDir . '/config/local.neon');
        } elseif ($_SERVER['SERVER_NAME'] === 'dev.reproman.cz') {
            $configurator->addConfig($appDir . '/config/staging.neon');
        }

        return $configurator;
    }
}
