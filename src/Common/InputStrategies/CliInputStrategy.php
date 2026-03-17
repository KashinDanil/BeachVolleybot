<?php

declare(strict_types=1);

namespace BeachVolleybot\Common\InputStrategies;

class CliInputStrategy extends AbstractInputStrategy
{
    private const string PARAMS_PATTERN = '/--([^=]+)=(.*)/';

    public function __construct()
    {
        $params = $this->getParams();
        $this->secretToken = $params['secretToken'] ?? '';
        $this->payload = $params['payload'] ?? '';
    }

    private function getParams(): array
    {
        global $argv;

        array_shift($argv);
        $params = [];
        foreach ($argv as $arg) {
            if (preg_match(self::PARAMS_PATTERN, $arg, $matches)) {
                $params[$matches[1]] = $matches[2];
            }
        }

        return $params;
    }

    public function getRequestMethod(): ?string
    {
        return 'POST'; //Emulate POST request so CLI runs pass the same validators and flow as HTTP requests.
    }
}
