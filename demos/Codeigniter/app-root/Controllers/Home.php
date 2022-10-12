<?php

namespace App\Controllers;

class Home extends BaseController
{
    public function index()
    {
        $db = \Config\Database::connect();
        $builder = $db->table('blog');
        $query = $builder->where('blog_title', 'Lorem');

//        return $this->respond(($query->getResultArray()), 200);
        return $this->response->setJSON($query->get()->getResult());
        //return $this->respond(($query->get()->getResult()), 200);
//		return view('welcome_message');
    }
}
