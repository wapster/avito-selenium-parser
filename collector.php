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


function get_item ($item, $driver) {
    try {
        // переходим в объявление
        $driver->get($item);
        sleep(rand(5,9));

        // ПАРСИМ ИНФ-ИЮ
        
        // url объявления
        $url = $item;
        
        // id объявления
        $item_id = explode("_", $url);
        $item_id = array_pop($item_id);
        
        
        // Заголовок объявления
        $title = $driver->findElement(WebDriverBy::tagName("h1"))->getText();

        // Описание объявления
        $description = $driver->findElement(WebDriverBy::xpath("//*[@id='app']/div/div[2]/div/div[3]/div[1]/div/div"))->getText();
        $description = mb_substr($description, 0, 700);
        $description = preg_replace('/[^a-zA-Zа-яА-Я0-9,.*= ]/ui', '', $description);

        // Цена
        $price = $driver->findElement(WebDriverBy::className("_3CnHz"))->getText();

        // Адрес
        $address = $driver->findElement(WebDriverBy::className("_20LXK"))->getText();

        // Имя продавца
        $name = $driver->findElement(WebDriverBy::className("bbujJ"))->getText();

        // id профиля продавца
        $profile_url = $driver->findElement(WebDriverBy::className("_2xsuC"))->getAttribute("href");
        $profile_url = mb_stristr($profile_url, '/user/');
        if ($profile_url !== false) {
            $profile_url = explode('?', $profile_url);
            $profile_url = 'https://m.avito.ru' . $profile_url[0];
            $profile = explode('?', $profile_url);
            // $profile_id = $profile[0];
            $profile_id = get_profile_id($profile_url);
        } else $profile_id = '';

        // дата парсинга объявления
        $date_scrape = date( 'Y-m-d' );
        
        $data['item_id'] = $item_id;
        $data['profile_id'] = $profile_id;
        $data['url'] = $url;
        $data['title'] = $title;
        $data['description'] = $description;
        $data['price'] = $price;
        $data['address'] = $address;
        $data['name'] = $name;
        $data['date_scrape'] = $date_scrape;
        
        // loginza("Страница с объявлением № " . $item_id. " просмотрена");
        return $data;
    } catch (Exception\WebDriverException $e) {
        loginza("ОШИБКА при парсинге страницы с объявлением");
        loginza($item);
        loginza($e);
        return $data = [];
    }

}

$device = 'iPhone 6';
$driver = get_driver($device);

$driver->get('https://m.avito.ru/kurgan?localPriority=1&s=104&user=1');
$url_list_from_index_page = get_links_from_index_page($driver);
loginza("Найдено: " . count($url_list_from_index_page) . " объявлений на главной странице");

$url_list_in_db = get_url_list_all_items();
// Обрабатываем страницы с объявлениями
if ($url_list_in_db !== 'error') {
    $url_for_parsing = array_values(array_diff($url_list_from_index_page, $url_list_in_db));
    loginza("из них новых: " . count($url_for_parsing) . " объявлений");
    
    // если есть что добавить -> добавляем
    $counter = count($url_for_parsing);
    if ( $counter > 0) {
        loginza("Начинаем просмотр и парсинг новых объявлений...");
        $i = 1;
        foreach ($url_for_parsing as $item) {
            // Парсим информацию со страницы объявления
            $data_item = get_item($item, $driver);
            $profiles_ids[] = $data_item['profile_id'];

            if (count($data_item) > 0) {
                // записываем инф-ию об объявлении в базу
                item_insert_in_db($data_item);
                loginza("объявление + ... " . $i . "/" . $counter);
                $i++;
            }
        }
    }
}


// Обрабатываем профили продавцов

// отсекаем профили, длина id которых 64 символа
// такие профили - подменные, в объявлениях по продаже авто и недвижимости
// очищаем от пустых строк
$profile_ids = array_values( array_diff( $profiles_ids, [''] ) );

$profile_urls = [];
foreach($profiles_ids as $profile_id) {
    $lenght = strlen($profile_id);
    if ($lenght < 40) {
        $profile_urls[] = 'https://m.avito.ru/user/' . $profile_id . "/profile";
    }

}

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
        loginza("профиль + ... " . $i . "/" . $counter);
        $i++;
    }
endif;

close_connect_db();
close_browser($driver);

empty_line();
