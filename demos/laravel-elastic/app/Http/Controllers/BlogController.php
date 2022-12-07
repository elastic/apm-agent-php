<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Models\User;
use Exception;
use Illuminate\Http\Request;
//use Illuminate\Support\Facades\Auth;
//use Illuminate\Support\Facades\Validator;
//use Laravel\Socialite\Facades\Socialite;
//use Elasticsearch;
use Elastic\Elasticsearch\ClientBuilder;
use Elastic\Elasticsearch\Exception\ClientResponseException;
use Elastic\Elasticsearch\Exception\ServerResponseException;
use Illuminate\Support\Facades\Auth;

class Test {
    public function f()
    {
        return 'f';
    }
}

class BlogController extends Controller
{
    public function home (Request $request) {
        //  $client = ClientBuilder::create()
        //      ->setHosts(['elasticsearch:9200'])
        //      ->build();
        //  $response = $client->info();

        //ini_set('memory_limit', '12048M');

       // \Elastic\Apm\Impl\AutoInstrument\PhpPartFacade::$singletonInstance->interceptionManager->loadPlugins();

        //(new Test())->f();

         //INDEX|CREATE
//         $params = [
//             'index' => 'my_index',
//             'body'  => [ 'testField' => 'abc']
//         ];
//
//         try {
//             $response = $client->index($params);
//         } catch (ClientResponseException $e) {
//             dd('ClientResponseException');
//             // manage the 4xx error
//         } catch (ServerResponseException $e) {
//             dd('ServerResponseException');
//             // manage the 5xx error
//         } catch (Exception $e) {
//             dd('Exception');
//             // eg. network error like NoNodeAvailableException
//         }

        // //UPDATE
        // $params = [
        //     'index' => 'my_index',
        //     'id' => $response['_id'],
        //     'body'  => [
        //             'doc' => [
        //                 'name' => 'abc1214'
        //             ]
        //         ]
        // ];
        // $response = $client->update($params);

        // //UPDATE by id with index() method
        // $params = [
        //     'index' => 'my_index',
        //     'id' => $response['_id'],
        //     'body'  => [ 'testField' => 'abc121']
        // ];
        // $response = $client->index($params);

        // print_r($response->asArray());

        // // SEARCH
        // $params = [
        //     'index' => 'my_index',
        //     'body'  => [
        //         'query' => [
        //             'match' => [
        //                 'testField' => 'abc'
        //             ]
        //         ]
        //     ]
        // ];
        // $response = $client->search($params);

        // printf("Total docs: %d\n", $response['hits']['total']['value']);
        // printf("Max score : %.4f\n", $response['hits']['max_score']);
        // printf("Took      : %d ms\n", $response['took']);

        // print_r($response['hits']['hits']); // documents

        // //GET
        // // $params = [
        // //     'index' => 'my_index',
        // //     'id' => 'jclknoQBtwi2L3yudXzA'
        // // ];
        // // $client->get($params);

        // //UPDATE
        // $params = [
        //     'index' => 'my_index',
        //     'body' => [
        //         'properties' => [
        //             'title' => [
        //                 'type' => 'text',
        //             ],
        //         ],
        //     ],
        // ];
        // $response = $client->indices()->putMapping($params);

        // //OPEN
        // $params = [
        //     'index' => 'my_index',
        //     'id' => 'jclknoQBtwi2L3yudXzA'
        // ];
        // $response = $client->indices()->open($params);

        // //DELETE
        // try {
        //     $response = $client->delete([
        //         'index' => 'my_index',
        //         'id' => 'W32AmYQBbyOLYHZLvPQI'
        //     ]);
        // } catch (ClientResponseException $e) {
        //     if ($e->getCode() === 404) {
        //         // the document does not exist
        //     }
        // }

        $posts = Post::all();

        return view('home', ['posts' => $posts]);
    }

    public function savePost (Request $request) {
        $post = new Post([
            'title' => $request->get('title'),
            'content' => $request->get('content'),
            'user_id' => 1
        ]);
        $post->save();

        return redirect('/');
    }
}
