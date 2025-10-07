# 📋 Как добавить SERVICE поля в Bitrix24

**Проблема:** Промежуточные точки и пассажиры НЕ отслеживаются

**Причина:** В Bitrix24 отсутствуют SERVICE поля для хранения предыдущих значений

---

## ✅ ЧТО НУЖНО СДЕЛАТЬ

### Добавить 2 новых поля в Bitrix24:

#### 1. SERVICE поле для промежуточных точек

**Настройки:**
- **Объект:** Сделка (Deal)
- **Тип поля:** Строка (String)
- **Название:** `Промежуточные точки (служ.)`
- **Символьный код:** `UF_CRM_XXXXX` (автоматически)
- **Доступ:** Скрытое от пользователей (только для API)

#### 2. SERVICE поле для пассажиров

**Настройки:**
- **Объект:** Сделка (Deal)
- **Тип поля:** Строка (String) или Список (List)
- **Название:** `Пассажиры (служ.)`
- **Символьный код:** `UF_CRM_XXXXX` (автоматически)
- **Доступ:** Скрытое от пользователей (только для API)

---

## 🔧 ГДЕ СОЗДАТЬ ПОЛЯ

### Вариант 1: Через веб-интерфейс

1. Откройте Bitrix24: https://meetride.bitrix24.ru/
2. Перейдите: **Настройки → CRM → Настройка → Сделки**
3. Нажмите **"Добавить поле"**
4. Заполните:
   - Название: `Промежуточные точки (служ.)`
   - Тип: Строка
   - Множественное: Нет
5. Сохраните
6. **ВАЖНО:** Скопируйте ID поля (например `UF_CRM_1234567890`)
7. Повторите для поля "Пассажиры (служ.)"

### Вариант 2: Через REST API

```bash
# Промежуточные точки SERVICE
curl -X POST "https://meetride.bitrix24.ru/rest/9/oo1pdplpuoy0q9ur/crm.deal.userfield.add.json" \
  -d "fields[FIELD_NAME]=UF_CRM_INTERMEDIATE_SERVICE" \
  -d "fields[USER_TYPE_ID]=string" \
  -d "fields[EDIT_FORM_LABEL]=Промежуточные точки (служ.)" \
  -d "fields[LIST_COLUMN_LABEL]=Промежуточные точки (служ.)"

# Пассажиры SERVICE
curl -X POST "https://meetride.bitrix24.ru/rest/9/oo1pdplpuoy0q9ur/crm.deal.userfield.add.json" \
  -d "fields[FIELD_NAME]=UF_CRM_PASSENGERS_SERVICE" \
  -d "fields[USER_TYPE_ID]=string" \
  -d "fields[EDIT_FORM_LABEL]=Пассажиры (служ.)" \
  -d "fields[LIST_COLUMN_LABEL]=Пассажиры (служ.)"
```

---

## 📝 ПОСЛЕ СОЗДАНИЯ ПОЛЕЙ

### 1. Узнайте ID созданных полей

```bash
cd /root/meetride
php -r "
require_once('/home/telegramBot/crest/crest.php');
\$fields = CRest::call('crm.deal.fields')['result'];
foreach (\$fields as \$key => \$field) {
    if (strpos(\$field['formLabel'], 'служ') !== false) {
        echo \$key . ' = ' . \$field['formLabel'] . \"\n\";
    }
}
"
```

### 2. Обновите константы в botManager.php

Откройте `/root/meetride/botManager.php` и добавьте:

```php
// После строки 33 добавить:
public const INTERMEDIATE_POINTS_FIELD_SERVICE = 'UF_CRM_XXXXXXXXX'; // ID из п.1
public const PASSENGERS_FIELD = 'UF_CRM_1751271798896'; // Уже есть
public const PASSENGERS_FIELD_SERVICE = 'UF_CRM_YYYYYYYYY'; // ID из п.1
```

### 3. Обновите функцию dealChangeHandle()

В файле `/root/meetride/botManager.php` найдите строки 1149-1153:

```php
// 4. Промежуточные точки
// TODO: Добавить отдельное SERVICE поле для промежуточных точек в Bitrix24

// 5. Пассажиры
// TODO: Добавить отдельное SERVICE поле для пассажиров в Bitrix24
```

Замените на:

```php
// 4. Промежуточные точки
$serviceIntermediate = $deal[botManager::INTERMEDIATE_POINTS_FIELD_SERVICE] ?? '';
$currentIntermediate = $deal[botManager::INTERMEDIATE_POINTS_FIELD] ?? '';

if ($serviceIntermediate && $serviceIntermediate != $currentIntermediate) {
    $changes[] = [
        'field' => 'intermediatePoints',
        'emoji' => '🗺️',
        'label' => 'Промежуточные точки',
        'old' => $serviceIntermediate ?: 'Не указано',
        'new' => $currentIntermediate ?: 'Не указано'
    ];
    $updateServiceFields[botManager::INTERMEDIATE_POINTS_FIELD_SERVICE] = $currentIntermediate;
}

// 5. Пассажиры
$servicePassengers = $deal[botManager::PASSENGERS_FIELD_SERVICE] ?? '';
$currentPassengers = $deal[botManager::PASSENGERS_FIELD] ?? '';

// Обработка массивов
if (is_array($currentPassengers)) {
    $currentPassengers = implode(", ", $currentPassengers);
}
if (is_array($servicePassengers)) {
    $servicePassengers = implode(", ", $servicePassengers);
}

if ($servicePassengers && $servicePassengers != $currentPassengers) {
    $changes[] = [
        'field' => 'passengers',
        'emoji' => '👥',
        'label' => 'Пассажиры',
        'old' => $servicePassengers ?: 'Не указано',
        'new' => $currentPassengers ?: 'Не указано'
    ];
    $updateServiceFields[botManager::PASSENGERS_FIELD_SERVICE] = $currentPassengers;
}
```

### 4. Обновите select в dealChangeHandle()

В строке 1041-1048 добавьте новые поля:

```php
$deal = \CRest::call('crm.deal.get', [
    'id' => $dealId,
    'select' => [
        '*',
        'UF_CRM_1751271798896', // Пассажиры
        botManager::INTERMEDIATE_POINTS_FIELD, // Промежуточные точки
        botManager::ADDRESS_FROM_FIELD_SERVICE, // SERVICE: Откуда
        botManager::ADDRESS_TO_FIELD_SERVICE, // SERVICE: Куда
        botManager::TRAVEL_DATE_TIME_FIELD_SERVICE, // SERVICE: Время
        botManager::INTERMEDIATE_POINTS_FIELD_SERVICE, // SERVICE: Промежуточные точки - ДОБАВИТЬ
        botManager::PASSENGERS_FIELD_SERVICE, // SERVICE: Пассажиры - ДОБАВИТЬ
    ]
])['result'];
```

### 5. Скопируйте в продакшн

```bash
cd /root/meetride
php -l botManager.php  # Проверка синтаксиса
cp botManager.php /var/www/html/meetRiedeBot/botManager.php
```

---

## 🧪 ТЕСТИРОВАНИЕ

После добавления полей:

1. Измените промежуточные точки в заявке
2. Проверьте логи:
```bash
tail -f /var/www/html/meetRiedeBot/logs/webhook_debug.log
```

3. Должно прийти уведомление:
```
🗺️ Промежуточные точки: <s>Точка 1</s> ➔ Точка 2
```

---

## 📊 ИТОГОВАЯ ТАБЛИЦА

| Поле | Основное | SERVICE | Статус |
|------|----------|---------|--------|
| Откуда | UF_CRM_1751269147414 | UF_CRM_1751638512 | ✅ Работает |
| Куда | UF_CRM_1751269175432 | UF_CRM_1751638529 | ✅ Работает |
| Время | UF_CRM_1751269222959 | UF_CRM_1751638617 | ✅ Работает |
| Промежуточные точки | UF_CRM_1754228146 | **Создать** | ⚠️ TODO |
| Пассажиры | UF_CRM_1751271798896 | **Создать** | ⚠️ TODO |

---

## ❓ НУЖНА ПОМОЩЬ?

Если нужна помощь с добавлением полей - дайте знать!

Я могу:
1. Создать поля через REST API
2. Обновить код после получения ID полей
3. Протестировать работу

---

**Без SERVICE полей промежуточные точки и пассажиры отслеживаться НЕ БУДУТ!**


