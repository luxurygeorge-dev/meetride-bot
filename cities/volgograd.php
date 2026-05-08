<?php
/**
 * Конфигурация для города: Волгоград
 * Создано: 2026-02-07
 * Базовая конфигурация из текущего продакшена
 */

return [
    'city_code' => 'volgograd',
    'city_name' => 'Волгоград',
    'timezone' => 'Europe/Volgograd',

    // Telegram Configuration
    'telegram' => [
        'creation_bot_token' => '7992462078:AAGJ46crBdOMSAuIfWncFd0AEjrDiT4Tnww',
        'notification_bot_token' => '7529690360:AAHED5aKmuKjjfFQPRI-0RQ8DlxlZARA2O4',
        'drivers_chat_id' => '-1002544521661',
        'managers_chat_id' => null,
    ],

    // Bitrix24 Configuration (ОДИН портал на все города)
    'bitrix24' => [
        'domain' => 'meetride.bitrix24.ru',
        'webhook_url' => 'https://meetride.bitrix24.ru/rest/9/oo1pdplpuoy0q9ur/',
        'user_id' => 9,
        'category_id' => 0, // Волгоград
        'funnel_prefix' => 'C1',
    ],

    // Field Mappings (UF_CRM_* поля из botManager.php)
    'fields' => [
        // Основные поля
        'driver_id' => 'UF_CRM_1751272181',
        'driver_telegram_id' => 'UF_CRM_1751185017761',
        'driver_fullname' => 'UF_CRM_1751185026711',
        'driver_phone' => 'UF_CRM_1751185033863',

        // Адреса
        'address_from' => 'UF_CRM_1751269147414',
        'address_from_service' => 'UF_CRM_1751638512',
        'address_to' => 'UF_CRM_1751269175432',
        'address_to_service' => 'UF_CRM_1751638529',

        // Промежуточные точки
        'intermediate_point_1' => 'UF_CRM_1751271833',
        'intermediate_point_1_service' => 'UF_CRM_1751639155',
        'intermediate_point_2' => 'UF_CRM_1751271911',
        'intermediate_point_2_service' => 'UF_CRM_1751639173',
        'intermediate_point_3' => 'UF_CRM_1751271959',
        'intermediate_point_3_service' => 'UF_CRM_1751639201',

        // Детали поездки
        'trip_date' => 'UF_CRM_1751269331660',
        'trip_time' => 'UF_CRM_1751269344668',
        'passenger_fullname' => 'UF_CRM_1751269423440',
        'passenger_phone' => 'UF_CRM_1751269405',
        'passengers_count' => 'UF_CRM_1751269452',
        'car_class' => 'UF_CRM_1751269467',
        'payment_type' => 'UF_CRM_1751269508',
        'cost' => 'UF_CRM_1751269614',
        'comment' => 'UF_CRM_1751269563',

        // Система напоминаний
        'reminder_sent' => 'UF_CRM_1751610732',
        'reminder_time' => 'UF_CRM_1751610746',
        'reminder_message_id' => 'UF_CRM_1751756028',

        // Финальное закрытие
        'final_accept_time' => 'UF_CRM_1751836732',
        'final_decline_time' => 'UF_CRM_1751836786',
        'final_user_id' => 'UF_CRM_1751837077',
    ],

    // Stage IDs (с префиксом воронки Волгограда)
    'stages' => [
        'new' => 'C1:NEW',
        'preparation' => 'C1:PREPARATION',
        'prepayment_invoice' => 'C1:PREPAYMENT_INVOICE',
        'executing' => 'C1:EXECUTING',
        'final_invoice' => 'C1:FINAL_INVOICE',
        'won' => 'C1:WON',
        'lose' => 'C1:LOSE',
    ],

    // Car Classes Mapping (из telegram_to_bitrix.py)
    'car_classes' => [
        'стандарт' => 119,
        'комфорт' => 93,
        'комфорт+' => 95,
        'комфорт плюс' => 95,
        'бизнес' => 97,
        'минивэн 6' => 99,
        'минивен' => 99,
        'минивэн' => 99,
        'микроавтобус' => 103,
        'вип-минивэн' => 105,
        'вип минивэн' => 105,
        'кроссовер' => 107,
        'внедорожник' => 109,
        'вип-седан' => 111,
        'вип седан' => 111,
        'пикап' => 113,
        'каблук' => 115,
        'грузопассажирский' => 117,
    ],

    // Payment Types
    'payment_types' => [
        'наличные' => 121,
        'карта' => 123,
        'безналичные' => 125,
        'безнал' => 125,
        'безналичный' => 125,
    ],

    // Business Rules
    'features' => [
        'enable_reminders' => true,
        'reminder_interval_minutes' => 60,
        'enable_intermediate_points' => true,
        'max_intermediate_points' => 3,
    ],
];
