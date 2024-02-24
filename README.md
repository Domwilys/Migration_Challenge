Para executar o projeto é necessário ter o Docker (optei por essa estratégia para as dependências e arquivos do projeto não conflitarem com arquivos ou aplicativos da máquina em que está sendo executado)

Primeiramente é necessário criar um arquivo .env utilizando o .env.example como modelo
OBS: Coloquei aqui no GitHub o .env que utilizei no projeto

```dosini
#MySQL database variables
DB_CONNECTION='Database used'
DB_HOST='Database host'
DB_PORT='Database port'
DB_DATABASE='Database name'
DB_USERNAME='Username'
DB_PASSWORD='User password'

#MariaDB database variables
MARIADB_ROOT_PASSWORD='Root password'
MARIADB_DATABASE='Database name'
MARIADB_USER='Username'
MARIADB_PASSWORD='User password'

#Redis
CACHE_DRIVER=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis

REDIS_HOST=Redis host
REDIS_PASSWORD=Redis password
REDIS_PORT=Redis port
```

OBS: Verifique se as conexões com os bancos de dados está funcionando corretamente

Suba os containers do projeto
```sh
docker-compose up -d
```


Acesse o container php
```sh
docker-compose exec php bash
```

Execute o arquivo migrate.php
```sh
php migrate.php
```
