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

$driver = get_driver();


$profiles = [
    // 'https://www.avito.ru/user/7ead3dd43a7382aca5b3718b3ec55472/profile',
    // 'https://www.avito.ru/user/6fba0e8502f8418747a077e3aa8d7886/profile',
    // 'https://avito.ru/user/8f7a08ae5bedbeba30625e9724501ab1/profile', /* >170 */
    // 'https://m.avito.ru/user/5a3237a4c9853b7ae928dd3a3f75471f/profile' /* 21 */
    'https://m.avito.ru/user/300929d3db1a82d2113f44c857aa069a/profile',   /* 4 */
    // 'https://m.avito.ru/user/6c4cefbc4faa1d5ac294f7360d86d5d1/profile',
];

foreach ($profiles as $profile_url) {

    $count_active_items = get_count_active_items($profile_url, $driver);
    $list_active_items = get_list_active_items($profile_url, $count_active_items, $driver);

    foreach ($list_active_items as $item) {
        // переходим в объявление

        // парсим инф-ию

        // записываем инф-ию об объявлении в базу

    }


}
