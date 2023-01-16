<?php

use yii\helpers\Html;
use yii\widgets\ActiveForm;

/* @var $this */
/* @var $model */

$this->title = 'Update Article: ' . ' ' . $model->id;
?>
<div class="drums-update">

    <h1><?= Html::encode($this->title) ?></h1>

    <?php $form = ActiveForm::begin(); ?>

    <?= $form->field($model, 'title') ?>

    <?= $form->field($model, 'content') ?>
    <br>
    <div class="form-group">
        <?= Html::submitButton('Update', ['class' => 'btn btn-primary']) ?>
    </div>

    <?php ActiveForm::end(); ?>
</div>