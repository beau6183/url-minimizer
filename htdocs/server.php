<?php
header('Content-Type: text/plain');
echo "SERVER: \n";
print_r($_SERVER);

echo "\n\nENV:\n";
print_r($_ENV);

echo "\n\nSESSION:\n";
print_r($_SESSION);
