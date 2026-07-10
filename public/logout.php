<?php
// ログアウト（Phase 2）
require_once __DIR__ . '/../src/auth.php';

start_session();
logout();
redirect('login.php');
