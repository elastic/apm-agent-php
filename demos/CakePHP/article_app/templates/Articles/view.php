<h1><?= h($article->title) ?></h1>
<p><?= h($article->body) ?></p>
<p><?= $this->Html->link('Edit', ['action' => 'edit', $article->slug]) ?></p>

