#!/bin/bash
#su - postgres -c 'psql -c "CREATE ROLE root WITH LOGIN SUPERUSER"';
service postgresql restart  # kill existing connections
psql postgres -c 'DROP DATABASE gforge'
psql postgres -c "CREATE DATABASE gforge ENCODING 'UNICODE' TEMPLATE template0"
psql gforge < /vagrant/fusionforge-init.sql
