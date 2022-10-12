<?php
$db = new PDO('sqlite:' . realpath(__DIR__) . '/laminastutorial.db');
$fh = fopen(__DIR__ . '/schema.sql', 'r');
while ($line = fread($fh, 4096)) {
    $db->exec($line);
}
fclose($fh);