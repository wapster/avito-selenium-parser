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

file_put_contents("log.txt", "");

$driver = get_driver();

$account = get_random_account($accounts_list);
$login = $account['login'];
$password = $account['password'];

// Авторизация
authorization($driver, $login, $password);

// Получаем ссылки на объявления в нашем регионе
$links = get_links_from_index_page($driver);

// Заходим в каждое объявление
// и получаем ссылку на профиль пользователя
$i = 0;
$profiles_in_db = get_profiles_in_db();
debug("Профили, к-ые уже есть в БД");
debug(count($profiles_in_db));
debug($profiles_in_db);

// Собираем ссылки на профили
$profile_urls = [];
foreach ($links as $link) {
    $profile_url = get_profile_url($link, $driver);
    $profile_urls[] = $profile_url;
}

// очищаем от пустых строк
$profile_urls = array_values( array_diff( $profile_urls, [''] ) );
debug($profile_urls);

// удаляем дубли
$profile_urls = array_values(array_unique($profile_urls));

// находим те, которых еще нет в БД
$profile_for_add_in_db = array_values(array_diff($profile_urls, $profiles_in_db));
debug($profile_for_add_in_db);
debug(count($profile_for_add_in_db));

// Добавляем профили в БД
foreach ($profile_for_add_in_db as $profile_url) {
    $profile_id = get_profile_id($profile_url);
    profile_insert_to_db($profile_id, $profile_url);
}
close_connect_db();
