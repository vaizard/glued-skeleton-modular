#!/usr/bin/env bash

eecho() {
  echo -e "\e[34;1m${1}\e[0m"
}

eprintf() {
  printf "\e[93;1m${1}\e[0m"
}

eecho "***********************"
eecho "*** GLUED INSTALLER ***"
eecho "***********************"
eecho ""

eecho "--- Ensuring private and public keys are present"
if [ ! -f ./private/crypto/private.key ]; then
   openssl genrsa -out ./private/crypto/private.key 2048
fi

if [ ! -f ./private/crypto/public.key ]; then
   openssl rsa -in  ./private/crypto/private.key -pubout -out  ./private/crypto/public.key
fi