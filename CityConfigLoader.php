<?php
namespace Store;

/**
 * CityConfigLoader - Загрузчик конфигураций городов по CATEGORY_ID
 *
 * Используется для динамической загрузки конфигов городов в webhook
 * на основе CATEGORY_ID сделки из Bitrix24.
 */
class CityConfigLoader {
    private static $configs = [];

    /**
     * Карта соответствия CATEGORY_ID -> city_code
     */
    private static $categoryMap = [
        0 => 'volgograd',
        1 => 'rostov',
    ];

    /**
     * Загрузить конфигурацию города по коду города
     *
     * @param string $cityCode Код города (например: 'saratov', 'volgograd')
     * @return array Массив конфигурации города
     * @throws \Exception Если файл конфигурации не найден
     */
    public static function load($cityCode) {
        if (!isset(self::$configs[$cityCode])) {
            // Phase 2C: prefer webroot copy, fallback to legacy /root/meetride_v2/
            $path = __DIR__ . "/cities/{$cityCode}.php";
            if (!file_exists($path)) {
                $legacyPath = "/root/meetride_v2/config/cities/{$cityCode}.php";
                if (file_exists($legacyPath)) {
                    $path = $legacyPath;
                } else {
                    throw new \Exception("City config not found: tried {$path} and {$legacyPath}");
                }
            }

            self::$configs[$cityCode] = require $path;
        }

        return self::$configs[$cityCode];
    }

    /**
     * Получить конфигурацию города по CATEGORY_ID из Bitrix24
     *
     * @param int $categoryId ID категории из Bitrix24 (CATEGORY_ID)
     * @return array Массив конфигурации города
     * @throws \Exception Если город не найден для данной категории
     */
    public static function getByCategoryId($categoryId) {
        if (!isset(self::$categoryMap[$categoryId])) {
            throw new \Exception("Unknown category ID: {$categoryId}");
        }

        $cityCode = self::$categoryMap[$categoryId];
        return self::load($cityCode);
    }

    /**
     * Получить код города по CATEGORY_ID
     *
     * @param int $categoryId ID категории из Bitrix24
     * @return string Код города (например: 'saratov')
     */
    public static function getCityCode($categoryId) {
        return self::$categoryMap[$categoryId] ?? 'volgograd';
    }

    /**
     * Получить список всех доступных городов
     *
     * @return array Массив ['category_id' => 'city_code']
     */
    public static function getAllCities() {
        return self::$categoryMap;
    }
}
