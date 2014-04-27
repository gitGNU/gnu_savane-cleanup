#!/bin/bash
service postgresql restart  # kill existing connections
psql postgres -c 'DROP DATABASE gforge'
psql postgres -c "CREATE DATABASE gforge ENCODING 'UNICODE' TEMPLATE template0"
psql gforge < /vagrant/fusionforge-init.sql
psql postgres -c "ALTER ROLE gforge WITH PASSWORD '$(cat pass)'"
