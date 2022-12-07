<h1>Articles</h1>
<p><?= $this->Html->link("Add Article", ['action' => 'add']) ?></p>
<table>
    <tr>
        <th>Title</th>
        <th>Action</th>
    </tr>
    <?php foreach ($articles as $article): ?>
        <tr>
            <td>
                <?= $this->Html->link($article->title, ['action' => 'view', $article->slug]) ?>
            </td>
            <td>
                <?= $this->Html->link('Edit', ['action' => 'edit', $article->slug]) ?>
                <?= $this->Html->link('Delete', ['action' => 'delete', $article->slug]) ?>
            </td>
        </tr>
    <?php endforeach; ?>

</table>
