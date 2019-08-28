#!/usr/bin/env bash

eecho() {
  echo -e "\e[34;1m****** ${1}\e[0m"
}

eprnt() {
  printf "\e[34;1m${1}\e[0m"
}

etest() {
  if [ $? -eq 0 ]; then
    echo -e "\e[92;1m[PASS] ${1} done.\e[0m"
  else
    echo -e "\e[91;1m[FAIL] ${1} failed.\e[0m"
fi
}

ewarn() {
  echo -e "\e[93;1m[WARN] ${1}\e[0m"
}

eecho "********************************"
eecho "*** GLUED INSTALL/UPDATE BOT ***"
eecho "********************************"
eecho ""

if [ ! -f ./private/crypto/private.key ]; then
   ewarn "Private key missing. Generating, please wait ..."
   openssl genrsa -out ./private/crypto/private.key 2048
   etest "Private key generation"
fi

if [ ! -f ./private/crypto/public.key ]; then
   ewarn "Public key missing. Generating, please wait ..."
   openssl rsa -in  ./private/crypto/private.key -pubout -out  ./private/crypto/public.key
   etest "Public key generation"
fi

if [ ! -f ./phinx.yml ] || [ ! -f ./glued/settings.php ]; then
   ewarn "Some mandatory configuration is missing. Lets setup the database:"

   eprnt "Please fill database host [127.0.0.1]: "
   read dbhost
   dbhost=${dbhost:-127.0.0.1}
   #echo $dbhost

   eprnt "Please fill database name [glued]: "
   read dbname
   dbname=${dbname:-glued}
   #echo $dbname

   eprnt "Please fill database user [glued]: "
   read dbuser
   dbuser=${dbuser:-glued}
   #echo $dbuser

   eprnt "Please fill database pass [glued-pw]: "
   read dbpass
   dbpass=${dbpass:-glued-pw}
   #echo $dbpass
fi

if [ ! -f ./phinx.yml ]; then
   ewarn "phinx.yml not configured ..."
   cp ./phinx.dist.yml ./phinx.yml
   sed -i "s/db_host/${dbhost}/g" phinx.yml
   sed -i "s/production_db/${dbname}/g" phinx.yml
   sed -i "s/db_user/${dbuser}/g" phinx.yml
   sed -i "s/db_pass/${dbpass}/g" phinx.yml
   php vendor/bin/phinx test -e production
   etest "phinx.yml configuration"
fi

if [ ! -f ./glued/settings.php ]; then
   ewarn "glued/settings.php not configured ..."
   cp ./glued/settings.dist.php ./glued/settings.php
   sed -i "s/db_host/${dbhost}/g" ./glued/settings.php
   sed -i "s/db_name/${dbname}/g" ./glued/settings.php
   sed -i "s/db_user/${dbuser}/g" ./glued/settings.php
   sed -i "s/db_pass/${dbpass}/g" ./glued/settings.php
   etest "glued/settings.php configuration"
fi


eecho "Everything looks fine, nothing else to do. Bye!"