# Knex Migragtion Generator for MySQL

This is a command line tool to automatic generate knex migration files from database exported structure file.

## 1. Get a sql file from your running database

you run

    mysqldump -u your-user-name -p your-passwd -P your-database-port -h your-database-host -d --databases use-your-database-name-here --set-charset --add-drop-table > your-database-name.sql

to get a SQL file which contains your database table structure.

## 2. Run generator.php (you should have php installed first)

    php generator.php your-database-name.sql
    
## 3. That's all. I hope it can help you.

a directory named ./migrations has been automatically created for you. In this directory, there is an individual javascript file for each table.



