<?php
$env = file_get_contents('.env');
$env = str_replace('QUEUE_CONNECTION=database', 'QUEUE_CONNECTION=sync', $env);
file_put_contents('.env', $env);
echo "Queue connection reverted to sync.\n";
