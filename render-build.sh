#!/usr/bin/env bash
set -o errexit

# Update package list and install necessary PHP extensions for PostgreSQL
apt-get update
apt-get install -y php-pgsql php-pdo-pgsql
