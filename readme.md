# update on production

docker pull philetaylor/corefilesapi:latest

docker stop $(docker ps -f name=corefiles -q)

docker rm corefiles

docker run --name corefiles -d  -e SSL_DOMAIN=corefiles.myjoomla.io --restart=always --env-file=.env-corefiles --mount source=corefiles-ssl,target=/ssl --mount source=acme.sh,target=/root/.acme.sh --mount source=corefiles-downloads,target=/var/www/html/public/downloads -p 80:80 -p 443:443 philetaylor/corefilesapi:latest

# Run on development (with Docker-Compose)

docker-sync-stack start


