<?php

namespace app\models;

use yii\base\Model;

class EntryForm extends Model
{
    public $title;
    public $content;

    public function rules()
    {
        return [
            [['title', 'content'], 'required'],
        ];
    }
}