-- MYSQL_DATABASE solo concede privilegios sobre esa base de datos concreta.
-- La suite de tests usa una BD adicional (dbname_suffix "_test"), así que
-- la creamos aquí y damos permisos al mismo usuario de la app.
CREATE DATABASE IF NOT EXISTS reservas_experiencias_test;
GRANT ALL PRIVILEGES ON reservas_experiencias_test.* TO 'app'@'%';
FLUSH PRIVILEGES;
