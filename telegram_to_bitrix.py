# -*- coding: utf-8 -*-
import dateparser
import os
import sys
import io
from pathlib import Path
sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding='utf-8')

import telebot
import requests
import re
from dotenv import load_dotenv

load_dotenv(Path(__file__).resolve().parent / ".env")

TELEGRAM_TOKEN = os.environ["TELEGRAM_TOKEN_MAIN"]
BITRIX_WEBHOOK = os.environ["BITRIX_WEBHOOK"]

bot = telebot.TeleBot(TELEGRAM_TOKEN)

# Маппинг классов автомобилей с правильными ID из Bitrix24
# Согласно полю UF_CRM_1751271728682 из deal (1).csv
CAR_CLASS_MAPPING = {
    'стандарт': 119,
    'комфорт плюс': 95,
    'комфорт': 93,          # Комфорт! Jolion, X-Cite, оптима и выше
    'комфорт!': 93,
    'комфорт +': 95,        # Комфорт +! Класс D, Camry, optima, k5 и подобные от 2018гв
    'комфорт+': 95,
    'микроавтобус': 97,     # Микроавтобус! Mercedes sprinter, pegout boxer и подобные
    'минивэн': 99,          # Минивэн! Hyundai Starex и подобные до 8ми мест
    'минивен': 99,
    'минивэн vip': 101,     # Минивэн VIP! Mercedes V-class, hyundai staria и подобные
    'минивен vip': 101,
    'автобус': 103,         # Автобус! Ютонг и подобные до 55 мест
    'бизнес': 105,          # Бизнес! BMW 5, MERCEDES E-CLASS И ПОДОБНЫЕ ОТ 2018 ГВ
    'представительский': 107, # Представительский! Mercedes s-class, Mercedes Maybach, BMW 7 и подобные
    'кроссовер': 109,       # Кроссовер! Jolion, Geely Atlas Pro, и подобные
    'кроссовер!': 109,
    'джип': 111,            # Джип! Land Cruser, BMW x 5 и подобные
    'джип!': 111,
    'внедорожник': 113,     # Внедорожник! Уаз патриот и подобные
    'внедорожник!': 113,
    'услуга трезвый водитель': 115, # Услуга ТРЕЗВЫЙ ВОДИТЕЛЬ
    'трезвый водитель': 115,
    'доставка': 117         # Доставка
}

# Примечание: теперь бот отправляет ID элементов списка напрямую в Bitrix24


def parse_message(message):
    data = {}

    # Номер заявки — только с начала строки, чтобы не захватывать случайные # внутри текста
    match = re.search(r'(?:^|\n)\s*(?:#️⃣|＃|#)\s*([A-ZА-Яa-zа-я0-9][A-ZА-Яa-zа-я0-9\-]+)', message)
    if not match:
        # Fallback для агрегаторных форматов без #: ищем XX-NNNN+ в начале строки
        match = re.search(r'(?:^|\n)\s*([A-ZА-Я]{2}-\d{4,})', message)

    if match:
        order_number = match.group(1).strip()
        data['TITLE'] = 'Заявка: {}'.format(order_number)
    else:
        data['TITLE'] = 'Заявка без номера'

    # Класс авто
    match = re.search(r'🚗\s*(.+)', message)
    car_class_text = match.group(1).strip() if match else ''
    
    # Обрабатываем класс автомобиля - просто ищем ID в маппинге
    if car_class_text:
        # Приводим к нижнему регистру для поиска
        car_class_lower = car_class_text.lower().strip()
        
        # Ищем ID в маппинге, если не найден - сохраняем None
        data['CAR_CLASS'] = CAR_CLASS_MAPPING.get(car_class_lower, None)
    else:
        data['CAR_CLASS'] = None

    # Дата и время
    match = re.search(r'📆\s*(.+)', message)
    raw_date = match.group(1).strip() if match else ''
    
    # Исправляем типичные опечатки в времени
    if raw_date:
        # Исправляем опечатки вида "05:301" -> "05:30"
        raw_date = re.sub(r'(\d{2}:\d{2})\d+', r'\1', raw_date)
        
    # DATE_ORDER='DMY' — явно указываем русский порядок день.месяц.год
    parsed_date = dateparser.parse(raw_date, settings={'DATE_ORDER': 'DMY'})
    data['WHEN'] = parsed_date.strftime('%Y-%m-%d %H:%M:%S') if parsed_date else ''


    # Откуда
    match = re.search(r'🅰️\s*(.+)', message)
    data['FROM'] = match.group(1).strip() if match else ''

    # Куда
    match = re.search(r'🅱️\s*(.+)', message)
    data['TO'] = match.group(1).strip() if match else ''

    # Номер рейса
    match = re.search(r'(✈️|🚆/✈️)\s*(.+)', message)
    data['FLIGHT'] = match.group(2).strip() if match else ''

    # Пассажир
    matches = re.findall(r'👥\s*(.+)', message)
    data['PASSENGER'] = matches if matches else []

    # Доп условия
    match = re.search(r'ℹ️\s*(.+)', message)
    data['CONDITIONS'] = match.group(1).strip() if match else ''

    # Стоимость
    # Стоимость
    match = re.search(r'💰\s*(\d+)[^\d]+(\d+)', message)
    if match:
        data['PRICE_CLIENT'] = int(match.group(1))
        data['PRICE_DRIVER'] = int(match.group(2))
        data['PRICE_COMBINED'] = "{}/{}".format(match.group(1), match.group(2))
    else:
        data['PRICE_CLIENT'] = None
        data['PRICE_DRIVER'] = None
        data['PRICE_COMBINED'] = ''


    return data

@bot.message_handler(func=lambda m: True)
def handle_message(message):
    print("RAW_MSG:", repr(message.text[:400]))
    parsed = parse_message(message.text)

    fields = {
        'fields': {
            'TITLE': parsed['TITLE'],
            'UF_CRM_1751269147414': parsed['FROM'],
            'UF_CRM_1751269175432': parsed['TO'],
            'UF_CRM_1751269222959': parsed['WHEN'],
            'UF_CRM_1751271728682': parsed['CAR_CLASS'],
            'UF_CRM_1751271774391': parsed['FLIGHT'],
            'UF_CRM_1751271798896': parsed['PASSENGER'],
            'UF_CRM_1751269256380': parsed['CONDITIONS'],
            'UF_CRM_1754381402': parsed['PRICE_COMBINED'],        # старое поле для общего вида 4500/3700
            'UF_CRM_1751271841129': parsed['PRICE_CLIENT'],       # Сумма для клиента
            'UF_CRM_1751271862251': parsed['PRICE_DRIVER'],       # Сумма для водителя
            'CATEGORY_ID': 0,
            'STAGE_ID': 'NEW'
        }
    }

    print("🧷 Проверка — URL перед отправкой:")
    print(BITRIX_WEBHOOK)
    response = requests.post(BITRIX_WEBHOOK, json=fields)
    print("🔗 URL запроса:")
    print(BITRIX_WEBHOOK)
    print("📦 Данные fields:")
    print(fields)
    print("📡 Куда отправили запрос:")
    print(response.url)
    print("📤 Отправляем в Битрикс24:")
    print(fields)

    print("📥 Ответ от Bitrix:")
    print(response.status_code)
    print(response.text)

    if response.status_code == 200 and 'result' in response.json():
        bot.send_message(message.chat.id, "✅ Сделка успешно создана!")
    else:
        bot.send_message(message.chat.id, "❌ Ошибка при создании сделки:\n{}".format(response.text))

    #bot.send_message(message.chat.id, f"📥 Ответ от Битрикс24:\n{response.text}")

bot.polling(none_stop=True, interval=0, timeout=30)

