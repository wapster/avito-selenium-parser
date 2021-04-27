<?php

set_time_limit(0);
date_default_timezone_set('Asia/Yekaterinburg');

// регион поиска объявлений
define('CITY', 'kurgan');

// кол-во профилей продавцов для парсинга с них обьявлений
// при 1 не работает, надо не менее 2
define('COUNT_RANDOM_PROFILES', 2);

// время, в часах, к-ое должно пройти,
// чтобы с аккаунта можно было снова кликать
define('ACCOUNT_TIME_CLICK', 7);

// время, в днях, к-ое должно пройти,
// чтобы можно было парсить телефон в профиле продавца
define('PROFILE_TIME_CLICK', 180);

