<?php

declare(strict_types=1);

namespace Elastic\Apm\Impl\Config;

use Elastic\Apm\Impl\Log\LogCategory;
use Elastic\Apm\Impl\Log\Logger;
use Elastic\Apm\Impl\Log\LoggerFactory;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class Parser
{
    /** @var Logger */
    private $logger;

    /**
     * Parser constructor.
     *
     * @param LoggerFactory $loggerFactory
     */
    public function __construct(LoggerFactory $loggerFactory)
    {
        $this->logger = $loggerFactory->loggerForClass(LogCategory::CONFIGURATION, __NAMESPACE__, __CLASS__, __FILE__);
    }

    /**
     * @param string                       $rawValue
     * @param OptionParser<mixed> $optionParser
     *
     * @return mixed
     *
     * @template       T
     * @phpstan-param  OptionParser<T> $optionParser
     * @phpstan-return T
     */
    public static function parseOptionRawValue(string $rawValue, OptionParser $optionParser)
    {
        return $optionParser->parse(trim($rawValue));
    }

    /**
     * @param array<string, OptionMetadata<mixed>> $optNameToMeta
     * @param RawSnapshotInterface                          $rawSnapshot
     *
     * @return array<string, mixed> Option name to parsed value
     */
    public function parse(array $optNameToMeta, RawSnapshotInterface $rawSnapshot): array
    {
        $optNameToParsedValue = [];
        /** @var OptionMetadata<mixed> $optMeta */
        foreach ($optNameToMeta as $optName => $optMeta) {
            $rawValue = $rawSnapshot->valueFor($optName);
            if (is_null($rawValue)) {
                $parsedValue = $optMeta->defaultValue();

                ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
                && $loggerProxy->log(
                    "Input raw config snapshot doesn't have a value for the option - using default value",
                    ['Option name' => $optName, 'Option default value' => $optMeta->defaultValue()]
                );
            } else {
                try {
                    $parsedValue = self::parseOptionRawValue($rawValue, $optMeta->parser());

                    ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
                    && $loggerProxy->log(
                        'Input raw config snapshot has a value - using parsed value',
                        ['Option name' => $optName, 'Raw value' => $rawValue, 'Parsed value' => $parsedValue]
                    );
                } catch (ParseException $ex) {
                    $parsedValue = $optMeta->defaultValue();

                    ($loggerProxy = $this->logger->ifErrorLevelEnabled(__LINE__, __FUNCTION__))
                    && $loggerProxy->log(
                        "Input raw config snapshot has a value but it's invalid - using default value",
                        [
                            'Option name'          => $optName,
                            'Option default value' => $optMeta->defaultValue(),
                            'Exception'            => $ex,
                        ]
                    );
                }
            }
            $optNameToParsedValue[$optName] = $parsedValue;
        }

        return $optNameToParsedValue;
    }
}
