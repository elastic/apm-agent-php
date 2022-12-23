<?php

use yii\helpers\Html;
use yii\widgets\DetailView;
use yii\widgets\LinkPager;
?>
    <h1>Blog</h1>
    <ul>
        <?php foreach ($articles as $article): ?>
            <div>
                <?= Html::a('Update', ['update', 'id' => $article->id], ['class' => 'btn btn-primary']) ?>
                <?= Html::a('Delete', ['delete', 'id' => $article->id], [
                    'class' => 'btn btn-danger',
                    'data' => [
                        'confirm' => 'Are you sure you want to delete this item?',
                        'method' => 'post',
                    ],
                ]) ?>
            </div>

            <?= DetailView::widget([
                'model' => $article,
                'attributes' => [
                    'id',
                    'title',
                    'content',
                ],
            ]) ?>
        <?php endforeach; ?>
    </ul>

<?= LinkPager::widget(['pagination' => $pagination]) ?>