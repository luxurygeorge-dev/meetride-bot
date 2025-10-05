<?php
/**
 * Система кеширования значений полей сделок
 * 
 * Хранит последние значения полей для сравнения изменений
 * Использует JSON файлы для простоты
 */

class DealCache {
    private static $cacheFile = '/var/www/html/meetRiedeBot/logs/deal_cache.json';
    
    /**
     * Получить кешированные значения полей сделки
     */
    public static function getDealFields($dealId) {
        $cache = self::loadCache();
        return $cache[$dealId] ?? null;
    }
    
    /**
     * Сохранить значения полей сделки в кеш
     */
    public static function saveDealFields($dealId, $fields) {
        $cache = self::loadCache();
        $cache[$dealId] = $fields;
        self::saveCache($cache);
    }
    
    /**
     * Удалить кеш сделки
     */
    public static function removeDeal($dealId) {
        $cache = self::loadCache();
        unset($cache[$dealId]);
        self::saveCache($cache);
    }
    
    /**
     * Сравнить текущие значения с кешированными
     */
    public static function compareFields($dealId, $currentFields) {
        $cachedFields = self::getDealFields($dealId);
        
        if (!$cachedFields) {
            // Первый раз - сохраняем текущие значения
            self::saveDealFields($dealId, $currentFields);
            return [];
        }
        
        $changes = [];
        
        // Поля для отслеживания
        $trackedFields = [
            'UF_CRM_1751269147414' => 'Точка А',
            'UF_CRM_1751269175432' => 'Точка Б',
            'UF_CRM_1751269222959' => 'Время поездки',
            'UF_CRM_1751269256380' => 'Пассажиры',
            'UF_CRM_1754228146' => 'Промежуточные точки'
        ];
        
        foreach ($trackedFields as $fieldId => $fieldName) {
            $oldValue = $cachedFields[$fieldId] ?? null;
            $newValue = $currentFields[$fieldId] ?? null;
            
            // Нормализуем значения (пустые строки = null)
            $oldValue = ($oldValue === '' || $oldValue === null) ? null : $oldValue;
            $newValue = ($newValue === '' || $newValue === null) ? null : $newValue;
            
            // Проверяем изменение
            if ($oldValue !== $newValue) {
                $changes[$fieldName] = [
                    'old' => $oldValue,
                    'new' => $newValue,
                    'field_id' => $fieldId
                ];
            }
        }
        
        // Обновляем кеш
        self::saveDealFields($dealId, $currentFields);
        
        return $changes;
    }
    
    /**
     * Загрузить кеш из файла
     */
    private static function loadCache() {
        if (!file_exists(self::$cacheFile)) {
            return [];
        }
        
        $content = file_get_contents(self::$cacheFile);
        $data = json_decode($content, true);
        
        return $data ?: [];
    }
    
    /**
     * Сохранить кеш в файл
     */
    private static function saveCache($cache) {
        // Очищаем старые записи (старше 7 дней)
        $weekAgo = time() - (7 * 24 * 60 * 60);
        foreach ($cache as $dealId => $fields) {
            if (isset($fields['_timestamp']) && $fields['_timestamp'] < $weekAgo) {
                unset($cache[$dealId]);
            }
        }
        
        // Добавляем timestamp
        foreach ($cache as $dealId => $fields) {
            $cache[$dealId]['_timestamp'] = time();
        }
        
        file_put_contents(self::$cacheFile, json_encode($cache, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
    
    /**
     * Получить статистику кеша
     */
    public static function getStats() {
        $cache = self::loadCache();
        return [
            'total_deals' => count($cache),
            'cache_file' => self::$cacheFile,
            'file_size' => file_exists(self::$cacheFile) ? filesize(self::$cacheFile) : 0
        ];
    }
}
?>


