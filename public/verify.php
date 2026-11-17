<?php
require_once __DIR__ . '/../config/config.php';
// Legacy verification links cannot authorize password setup or activate accounts.
http_response_code(410);
echo 'This verification link has been retired. Contact your administrator for a new password setup invitation.';
