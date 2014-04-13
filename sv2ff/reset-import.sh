#!/bin/bash
sudo su - postgres -c "psql -c 'DROP DATABASE gforge' && psql -c 'CREATE DATABASE gforge' && psql gforge" < /vagrant/fusionforge-init.sql
