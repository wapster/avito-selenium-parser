<?php
namespace Facebook\WebDriver;

use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\Chrome\ChromeOptions;
use Facebook\WebDriver\Exception;
use \PDO;

require_once('vendor/autoload.php');
require_once('functions.php');
require_once('settings.php');


empty_line();
loginza("PROFILES");
loginza("Старт парсинга ПРОФИЛЕЙ продавцов через их объявления");


// Авторизация
$account = get_random_account();
$login = $account['login'];
$password = $account['password'];
$device = $account['device'];
$driver = get_driver($device);
authorization($driver, $login, $password);

// Заходим в каждое объявление
// и получаем ссылку на профиль пользователя

// Получаем ссылки на объявления в нашем регионе
$links = get_links_from_index_page($driver);

// Собираем ссылки на профили
$profile_urls = [];
foreach ($links as $link) {
    $profile_url = get_profile_url($link, $driver);
    $profile_urls[] = $profile_url;
}

// очищаем от пустых строк
$profile_urls = array_values( array_diff( $profile_urls, [''] ) );

// удаляем дубли
$profile_urls = array_values(array_unique($profile_urls));

// находим те, которых еще нет в БД
$profiles_in_db = get_profiles_in_db();
$profile_for_add_in_db = array_values(array_diff($profile_urls, $profiles_in_db));

if (count($profile_for_add_in_db) > 0):
    // Добавляем профили в БД
    foreach ($profile_for_add_in_db as $profile_url) {
        $profile_id = get_profile_id($profile_url);
        profile_insert_to_db($profile_id, $profile_url);
    }
endif;

close_connect_db();
close_browser($driver);
