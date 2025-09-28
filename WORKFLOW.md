# 🔄 Workflow: Production ↔ GitHub ↔ Development

## 📋 Процесс разработки

### 0. **Синхронизация с боевым сервером**
```bash
# Получить актуальную версию с боевого сервера
./scripts/sync-from-production.sh

# Загрузить изменения на боевой сервер
./scripts/sync-to-production.sh
```

### 1. **Development (Разработка)**
```bash
# Создать feature ветку
git checkout development
git checkout -b feature/new-feature

# Разработать функциональность
# ... код ...

# Зафиксировать изменения
git add .
git commit -m "Add new feature"
git push origin feature/new-feature

# Создать Pull Request в development
```

### 2. **Staging (Тестирование)**
```bash
# После одобрения PR в development
git checkout staging
git merge development

# Деплой в тестовую среду
./scripts/deploy-safe.sh staging

# Тестирование
# ... тесты ...

# Если всё ОК - создать PR в production
```

### 3. **Production (Продакшн)**
```bash
# После тестирования в staging
git checkout production
git merge staging

# Деплой в продакшн
./scripts/deploy-safe.sh production
```

## 🛡️ Защита

### Автоматические проверки:
- ✅ Синтаксис PHP
- ✅ Отсутствие секретов в коде
- ✅ Наличие конфигурации
- ✅ Структура проекта

### Ручные проверки:
- 🧪 Тестирование в staging
- 📊 Проверка логов
- 🔍 Code review

## 🚨 Откат (Rollback)

```bash
# Если что-то пошло не так
git checkout production
git reset --hard HEAD~1
./scripts/deploy-safe.sh production
```

## 📊 Мониторинг

- **Логи:** `/var/www/html/meetRiedeBot/logs/`
- **Статус:** GitHub Actions
- **Уведомления:** Telegram/Email

## 🔧 Настройка

1. **Создать ветки:**
```bash
git checkout -b development
git checkout -b staging
git checkout -b production
git push -u origin development staging production
```

2. **Настроить GitHub Actions** (уже готово)

3. **Настроить уведомления** в GitHub Settings
