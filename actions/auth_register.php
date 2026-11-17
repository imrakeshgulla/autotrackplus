<?php
require_once __DIR__ . '/../config/config.php';
http_response_code(403);
exit('Registration is by administrator invitation. Contact your administrator.');
