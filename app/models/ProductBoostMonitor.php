<?php
require_once __DIR__.'/BoostStore.php';

// Compatibility for existing product-list/history read endpoints.
class ProductBoostMonitor extends BoostStore {}
