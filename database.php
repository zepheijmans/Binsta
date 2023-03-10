<?php

use RedBeanPHP\R as R;

// Setup connection
R::setup('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME, DB_USER, DB_PASS);
