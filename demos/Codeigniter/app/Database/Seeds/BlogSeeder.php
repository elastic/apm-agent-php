<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class BlogSeeder extends Seeder
{
    public function run()
    {
        $data = [
            'blog_title' => 'Lorem',
            'blog_description'    => 'Lorem ipsum',
        ];

        $this->db->table('blog')->insert($data);
    }
}