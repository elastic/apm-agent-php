<?php

use Elastic\Elasticsearch\ClientBuilder;
use Elastic\Elasticsearch\Exception\ClientResponseException;

require __DIR__ . '/vendor/autoload.php';

function runsAsCli(): bool
{
    return PHP_SAPI === 'cli';
}

function myEcho(string $msg): void
{
    $thisScript = basename(__FILE__);
    $lineTxt = date('Y-m-d H:i:s') . ' [' . $thisScript . '] ' . $msg;

    syslog(LOG_DEBUG, $lineTxt);

    if (!defined('STDERR')) {
        define('STDERR', fopen('php://stderr', 'w'));
    }
    if (defined('STDERR')) {
        fwrite(STDERR, $lineTxt . PHP_EOL);
    }

    if (!runsAsCli()) {
        echo $lineTxt . '<br>' . "\n";
        flush();
    }
}

function valToString($val): string
{
    if (is_scalar($val)) {
        return strval($val);
    }

    if (is_array($val)) {
        return '<' . get_debug_type($val) . '>: ' . json_encode($val);
    }

    if (is_object($val)) {
        $objAsString = method_exists($val, '__toString') ? strval($val) : json_encode(get_object_vars($val));
        return '<' . get_debug_type($val) . '>: ' . $objAsString;
    }

    if (is_resource($val)) {
        return '<' . get_debug_type($val) . ', ' . get_resource_type($val) . '>: ' . get_resource_id($val);
    }

    return '<' . get_debug_type($val) . '>: ' . $val;
}

// function callElasticsearch(string $dbgApi, callable $call)
// {
//     myEcho('Calling ' . $dbgApi . '...');
//     try {
//         return $call();
//     } catch (Exception $ex) {
//         myEcho('Call to ' . $dbgApi . ' has failed. ' . get_debug_type($ex) . ': ' . $ex);
//         throw $ex;
//     }
// }

function main(): void
{
    myEcho('Starting sequence of calls to Elasticsearch API...');

    // $client = callElasticsearch(
    //     'ClientBuilder::create->...->build',
    //     function () {
    //         return ClientBuilder::create()
    //                             ->setHosts(['elasticsearch:9200'])
    //                             ->build();
    //     }
    // );
    $client = ClientBuilder::create()
                           ->setHosts(['elasticsearch:9200'])
                           ->build();
    $response = $client->info();
    myEcho('info() response: ' . valToString($response));

    //
    // Index|create
    //
    $response = $client->index(
        [
            'index' => 'my_index',
            'body'  => ['testField' => 'abc'],
        ]
    );
    myEcho('index() response: ' . valToString($response));
    $docId = $response['_id'];

    //
    // Update
    //
    $response = $client->update(
        [
            'index' => 'my_index',
            'id'    => $docId,
            'body'  => [
                'doc' => [
                    'name' => 'abc1214',
                ],
            ],
        ]
    );
    myEcho('update() response: ' . valToString($response));

    //
    // Update by id with index() method
    //
    $response = $client->index(
        [
            'index' => 'my_index',
            'id'    => $docId,
            'body'  => ['testField' => 'abc121'],
        ]
    );
    myEcho('index() to update response: ' . valToString($response));

    //
    // Search
    //
    $response = $client->search(
        [
            'index' => 'my_index',
            'body'  => [
                'query' => [
                    'match' => [
                        'testField' => 'abc',
                    ],
                ],
            ],
        ]
    );
    myEcho('search() response: ' . valToString($response));

    printf("Total docs: %d\n", $response['hits']['total']['value']);
    printf("Max score : %.4f\n", $response['hits']['max_score']);
    printf("Took      : %d ms\n", $response['took']);
    print_r($response['hits']['hits']); // documents

    //
    // Get
    //
    $response = $client->get(
        [
            'index' => 'my_index',
            'id'    => $docId,
        ]
    );
    myEcho('get() response: ' . valToString($response));

    //
    // Update
    //
    $response = $client->indices()->putMapping(
        [
            'index' => 'my_index',
            'body'  => [
                'properties' => [
                    'title' => [
                        'type' => 'text',
                    ],
                ],
            ],
        ]
    );
    myEcho('indices()->putMapping() response: ' . valToString($response));

    //
    // Open
    //
    $response = $client->indices()->open(
        [
            'index' => 'my_index',
            'id'    => 'jclknoQBtwi2L3yudXzA',
        ]
    );
    myEcho('indices()->open() response: ' . valToString($response));

    //
    // Delete
    //
    $response = $client->delete(
        [
            'index' => 'my_index',
            'id'    => $docId,
        ]
    );
    myEcho('delete() response: ' . valToString($response));

    //
    // Get
    //
    try {
        $client->get(
            [
                'index' => 'my_index',
                'id'    => $docId,
            ]
        );
    } catch (ClientResponseException $ex) {
        myEcho('get() thrown as expected: ' . valToString($ex));
    }

    myEcho('Successfully finished sequence of calls to Elasticsearch API.');
}

main();