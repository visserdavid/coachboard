<?php
declare(strict_types=1);

Auth::logout();

redirect(APP_URL . '/public/index.php?page=auth&action=login&message=logged_out');
