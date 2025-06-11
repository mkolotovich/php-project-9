#!/bin/bash
set -e

echo "Running migrations..."
psql -a -d "$DATABASE_URL" -f database.sql
exec "$@"
