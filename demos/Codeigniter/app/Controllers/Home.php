<?php namespace App\Controllers;

use CodeIgniter\API\ResponseTrait;

class Home extends BaseController
{
    use ResponseTrait;

    public function index()
    {
        $db = \Config\Database::connect();
        $builder = $db->table('blog');
        $query = $builder->where('blog_title', 'Lorem');

        return $this->respond('Codeigniter', 200);
//        return $this->respond(($query->getResultArray()), 200);
//        return $this->respond(($query->get()->getResult()), 200);
//		return view('welcome_message');
    }
}
