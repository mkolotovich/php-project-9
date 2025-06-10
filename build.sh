#!/bin/bash

# Подождём пока запустится postgres
until pg_isready -d $DATABASE_URL -p 5432; do
  echo "Waiting for PostgreSQL..."
  sleep 1
done

echo "PostgreSQL is ready!"
psql -a -d $DATABASE_URL -f database.sql