<?php
declare(strict_types=1);

$statusCode = filter_var($_GET['status'] ?? 200, FILTER_VALIDATE_INT);

http_response_code($statusCode);
header("Content-Type: text/plain");
echo "Status code: $statusCode\n";