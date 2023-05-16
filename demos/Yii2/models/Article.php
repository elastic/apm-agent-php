<?php

namespace app\models;

use yii\db\ActiveRecord;

class Article extends ActiveRecord
{
    public function rules()
    {
        return [
            [['title', 'content'], 'required'],
        ];
    }
}