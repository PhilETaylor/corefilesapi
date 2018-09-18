
# Run on production

docker volume create corefiles-downloads

docker run -d --restart=always --env-file=.env-corefiles --mount source=corefiles-downloads,target=/var/www/html/public/downloads -p 88:80 philetaylor/corefilesapi:latest


# Run on development (with Docker-Compose)

docker-sync-stack start


# Volume
### dev
Handled by docker-composer
### prod