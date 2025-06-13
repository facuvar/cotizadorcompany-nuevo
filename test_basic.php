<?php
echo "BASIC TEST RAILWAY<br>";
echo "PHP: " . PHP_VERSION . "<br>";
echo "Date: " . date('Y-m-d H:i:s') . "<br>";
echo "Host: " . $_SERVER['HTTP_HOST'] . "<br>";
echo "Port ENV: " . ($_ENV['PORT'] ?? 'NO') . "<br>";
echo "END TEST<br>";
?> 