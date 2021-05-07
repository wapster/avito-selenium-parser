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
// $links = get_links_from_index_page($driver);


$categories = [
    'https://m.avito.ru/kurgan/lichnye_veschi',
    'https://m.avito.ru/kurgan/bytovaya_elektronika',
    'https://m.avito.ru/kurgan/remont_i_stroitelstvo',
    'https://m.avito.ru/kurgan/odezhda_obuv_aksessuary',
    'https://m.avito.ru/kurgan/detskaya_odezhda_i_obuv',
    'https://m.avito.ru/kurgan/tovary_dlya_detey_i_igrushki',
    'https://m.avito.ru/kurgan/chasy_i_ukrasheniya',
    'https://m.avito.ru/kurgan/krasota_i_zdorove',
    'https://m.avito.ru/kurgan/bytovaya_tehnika',
    'https://m.avito.ru/kurgan/mebel_i_interer',
    'https://m.avito.ru/kurgan/posuda_i_tovary_dlya_kuhni',
    'https://m.avito.ru/kurgan/produkty_pitaniya',
    'https://m.avito.ru/kurgan/remont_i_stroitelstvo',
    'https://m.avito.ru/kurgan/rasteniya',
    'https://m.avito.ru/kurgan/audio_i_video',
    'https://m.avito.ru/kurgan/igry_pristavki_i_programmy',
    'https://m.avito.ru/kurgan/nastolnye_kompyutery',
    'https://m.avito.ru/kurgan/noutbuki',
    'https://m.avito.ru/kurgan/orgtehnika_i_rashodniki',
    'https://m.avito.ru/kurgan/planshety_i_elektronnye_knig',
    'https://m.avito.ru/kurgan/telefony',
    'https://m.avito.ru/kurgan/tovary_dlya_kompyutera',
    'https://m.avito.ru/kurgan/fototehnika',
    'https://m.avito.ru/kurgan/bilety_i_puteshestviya',
    'https://m.avito.ru/kurgan/velosipedy',
    'https://m.avito.ru/kurgan/knigi_i_zhurnaly',
    'https://m.avito.ru/kurgan/kollektsionirovanie',
    'https://m.avito.ru/kurgan/muzykalnye_instrumenty',
    'https://m.avito.ru/kurgan/ohota_i_rybalka',
    'https://m.avito.ru/kurgan/sport_i_otdyh',
    'https://m.avito.ru/kurgan/sobaki',
    'https://m.avito.ru/kurgan/koshki',
    'https://m.avito.ru/kurgan/ptitsy',
    'https://m.avito.ru/kurgan/akvarium',
    'https://m.avito.ru/kurgan/drugie_zhivotnye',
    'https://m.avito.ru/kurgan/tovary_dlya_zhivotnyh',
    'https://m.avito.ru/kurgan/gotoviy_biznes',
    'https://m.avito.ru/kurgan/oborudovanie_dlya_biznesa',
    'https://m.avito.ru/kurgan/predlozheniya_uslug'
];

$category = array_rand(array_flip($categories));
$driver->get($category . '?localPriority=1&s=104&user=1');
$links = get_links_from_index_page($driver);
$counter = count($links);
loginza("Найдено ссылок на странице: " . $counter . " (в т.ч. дубли)");
loginza("Собираем ссылки на профили... " . $category);


// Собираем ссылки на профили
$profile_urls = [];
$i = 1;
foreach ($links as $link) {
    $profile_url = get_profile_url($link, $driver);
    $profile_urls[] = $profile_url;
    loginza($i . " из " . $counter);
    $i++;
}

// очищаем от пустых строк
$profile_urls = array_values( array_diff( $profile_urls, [''] ) );

// удаляем дубли
$profile_urls = array_values(array_unique($profile_urls));

// находим те, которых еще нет в БД
$profiles_in_db = get_profiles_in_db();
$profile_for_add_in_db = array_values(array_diff($profile_urls, $profiles_in_db));

$counter = count($profile_for_add_in_db);
loginza("в БД будет добавлено: " . $counter . " профилей продавцов");

if (count($profile_for_add_in_db) > 0):
    // Добавляем профили в БД
    $i = 1;
    foreach ($profile_for_add_in_db as $profile_url) {
        $profile_id = get_profile_id($profile_url);
        profile_insert_to_db($profile_id, $profile_url);
        loginza("добавлено... " . $i . "/" . $counter);
        $i++;
    }
endif;

close_connect_db();
close_browser($driver);

empty_line();
