#!/bin/bash

DB_HOST=$(echo "$DATABASE_URL" | awk -F'[/:@]' '{print $4}')
DB_PORT=5432 # Предполагаем стандартный порт, можно изменить исходя из переменной окружения
DB_NAME=$(echo "$DATABASE_URL" | awk -F'/' '{print $NF}') # Получаем имя БД из конца строки

until pg_isready -h "$DB_HOST" -p "$DB_PORT"; do
  echo "Waiting for PostgreSQL..."
  sleep 1
done

echo "PostgreSQL is ready!"
psql -a -d "$DATABASE_URL" -f database.sql