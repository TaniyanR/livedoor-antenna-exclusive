<?php
require_once __DIR__.'/app/bootstrap.php';
local_https_downgrade();
if(!installed()) redirect('/install/');
require __DIR__.'/admin/index.php';
