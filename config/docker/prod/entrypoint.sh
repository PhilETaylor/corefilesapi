if [ ! -f /ssl/sslcert.pem ]; then

    curl https://get.acme.sh | sh

    # Set the domain from the env
    sed "s/REPLACE;/$SSL_DOMAIN;/" /site.conf > /etc/nginx/conf.d/$SSL_DOMAIN.conf

    #start nginx so that acme can run
    nginx

    # test nginx and issue new cert
    /root/.acme.sh/acme.sh  --issue --nginx -d  ${SSL_DOMAIN} --reloadcmd "pkill nginx && sleep 3 && nginx"

    # configure nginx to run in SSL mode
    rm /etc/nginx/conf.d/$SSL_DOMAIN.conf && sed "s/REPLACE;/$SSL_DOMAIN;/" /sitessl.conf > /etc/nginx/conf.d/$SSL_DOMAIN.conf

    # isntall the certs to the right place and restart apache
    /root/.acme.sh/acme.sh --install-cert -d ${SSL_DOMAIN} --key-file /ssl/sslkey.pem --fullchain-file /ssl/sslcert.pem --reloadcmd "pkill nginx && sleep 3 && nginx"
else

    sed "s/REPLACE;/$SSL_DOMAIN;/" /sitessl.conf > /etc/nginx/conf.d/$SSL_DOMAIN.conf

    pkill nginx && sleep 3 && nginx

fi

# Start the cron jobs
crond -L /var/log/cron.log

# start all services
supervisord -c /etc/supervisord.conf