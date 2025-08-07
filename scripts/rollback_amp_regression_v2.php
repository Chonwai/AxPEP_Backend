<?php

/**
 * AMP Regression V2 API 回退腳本
 *
 * 此腳本用於快速回退到V1 API
 */

require_once __DIR__.'/enable_amp_regression_v2.php';

echo "🔄 執行AMP Regression V2 API回退...\n";

$deployer = new AmpRegressionV2Deployer;
$success = $deployer->rollback();

exit($success ? 0 : 1);
