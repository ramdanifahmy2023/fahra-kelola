<?php

// Timezone Configuration
date_default_timezone_set('Asia/Jakarta');

// Session Setup
if (!session_id()) session_start();

// Bootstrapping
require_once '../app/init.php';

// App Initialization
$app = new App();
