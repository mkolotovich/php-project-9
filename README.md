# PHP проект - "Анализатор страниц"
### Hexlet tests and linter status:
[![Actions Status](https://github.com/mkolotovich/php-project-9/actions/workflows/hexlet-check.yml/badge.svg)](https://github.com/mkolotovich/php-project-9/actions)
[![PHP CI](https://github.com/mkolotovich/php-project-9/actions/workflows/workflow.yml/badge.svg)](https://github.com/mkolotovich/php-project-48/actions/workflows/workflow.yml)
[![Maintainability Rating](https://sonarcloud.io/api/project_badges/measure?project=mkolotovich_php-project-9&metric=sqale_rating)](https://sonarcloud.io/summary/new_code?id=mkolotovich_php-project-9)

## Описание
Page Analyzer – это сайт, который анализирует указанные страницы на SEO-пригодность по аналогии с PageSpeed Insights.

## Установка и запуск приложения 
1. Убедитесь, что у вас установлен PHP версии 8.1 или выше. В противном случае установите PHP версии 8.1 или выше.
2. Установите СУБД PostgreSQL если она у вас не установлена и создайте в ней БД. Создайте файл .env в котором пропишите переменную окружения DATABASE_URL которая задаёт подключение к вашей БД. Также создайте переменную окружения PORT и задайте ей значение 5432(порт по умолчанию в PostgreSQL).
3. Установите зависимости в систему и создайте таблицы в БД командой make build. Запуск приложения осуществляется командой make dev в терминале. Команды make build и make dev необходимо запускать из корневой директории проекта.

Ссылка на деплой - https://php-project-9-csdd.onrender.com