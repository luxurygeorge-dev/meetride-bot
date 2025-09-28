# MeetRide Bot - Codex Integration

Этот репозиторий настроен для эффективной работы с AI-ассистентами (Codex, ChatGPT, Claude и др.).

## 📁 Структура для AI

```
.codex/
├── README.md              # Этот файл
├── docs/                  # Документация для AI
│   ├── architecture.md    # Архитектура системы
│   ├── api-reference.md   # Справочник API
│   └── troubleshooting.md # Решение проблем
├── context/               # Контекстные файлы
│   ├── project-overview.md # Обзор проекта
│   ├── business-logic.md   # Бизнес-логика
│   └── integration-guide.md # Руководство по интеграции
├── examples/              # Примеры использования
│   ├── webhook-examples.md # Примеры webhook'ов
│   └── message-examples.md # Примеры сообщений
└── workflows/             # Рабочие процессы
    ├── development.md     # Процесс разработки
    └── deployment.md      # Процесс деплоя
```

## 🤖 Для AI-ассистентов

При работе с этим проектом:

1. **Начните с** `.codex/context/project-overview.md` - общее понимание
2. **Изучите** `.codex/docs/architecture.md` - техническая архитектура
3. **Используйте** `.codex/examples/` - примеры кода и данных
4. **Следуйте** `.codex/workflows/` - процессы разработки

## 🔧 Быстрый старт

```bash
# Клонирование репозитория
git clone https://github.com/luxurygeorge-dev/meetride-bot.git
cd meetride-bot

# Настройка конфигурации
cp config/config.example.php config/config.php
# Отредактируйте config/config.php с вашими настройками

# Установка зависимостей (если нужно)
composer install

# Запуск
php src/index.php
```

## 📞 Поддержка

- **Issues:** GitHub Issues
- **Документация:** В папке `.codex/docs/`
- **Примеры:** В папке `.codex/examples/`
