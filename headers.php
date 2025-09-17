<?php
header('Content-Type: application/json');
echo json_encode(getallheaders(), JSON_PRETTY_PRINT);
