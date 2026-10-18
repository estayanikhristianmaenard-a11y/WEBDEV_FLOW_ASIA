<?php

namespace Tests;

use Illuminate\Foundation\Application;

class SharedModuleTestCase extends TestCase
{
    public function createApplication(): Application
    {
        $values = ['M4_MODE' => 'shared', 'M4_ROUTE_PREFIX' => 'modules/m4', 'M4_ROUTE_NAME_PREFIX' => 'm4.'];
        $previous = [];
        foreach ($values as $key => $value) {
            $previous[$key] = [getenv($key), $_ENV[$key] ?? null, $_SERVER[$key] ?? null];
            putenv($key.'='.$value);
            $_ENV[$key] = $_SERVER[$key] = $value;
        }
        try {
            return parent::createApplication();
        } finally {
            foreach ($previous as $key => [$process, $environment, $server]) {
                $process === false ? putenv($key) : putenv($key.'='.$process);
                if ($environment === null) {
                    unset($_ENV[$key]);
                } else {
                    $_ENV[$key] = $environment;
                }
                if ($server === null) {
                    unset($_SERVER[$key]);
                } else {
                    $_SERVER[$key] = $server;
                }
            }
        }
    }
}
