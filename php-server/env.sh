#!/usr/bin/env bash
printf '\n' >> /var/www/html/.env
echo "APP_DEBUG=$APP_DEBUG" >> /var/www/html/.env
echo "DB_HOST=$DB_HOST" >> /var/www/html/.env
echo "DB_PORT=$DB_PORT" >> /var/www/html/.env
echo "DB_USERNAME=$DB_USERNAME" >> /var/www/html/.env
echo "DB_PASSWORD=$DB_PASSWORD" >> /var/www/html/.env
echo "REMUNERASI_DATA_API_URL=$REMUNERASI_DATA_API_URL" >> /var/www/html/.env
echo "AWS_ENDPOINT=$AWS_ENDPOINT" >> /var/www/html/.env
echo "AWS_ENDPOINT_UPLOAD=$AWS_ENDPOINT_UPLOAD" >> /var/www/html/.env
echo "AWS_SECRET_ACCESS_KEY=$AWS_SECRET_ACCESS_KEY" >> /var/www/html/.env
echo "AWS_ACCESS_KEY_ID=$AWS_ACCESS_KEY_ID" >> /var/www/html/.env
#echo "AWS_SSL=$AWS_SSL" >> /var/www/html/.env
#echo "AWS_BUCKETNAME=$AWS_BUCKETNAME" >> /var/www/html/.env
echo "AWS_REGION=$AWS_REGION" >> /var/www/html/.env
echo "AWS_VERSION=$AWS_VERSION" >> /var/www/html/.env
echo "PHP_MEMORY_LIMIT=$PHP_MEMORY_LIMIT" >> /var/www/html/.env
# echo "PHP_MEMORY_LIMIT=$PHP_MEMORY_LIMIT" >> /var/www/html/.env
echo "PORTOFOLIO_REMUNERASI_DATA_API_URL=$PORTOFOLIO_REMUNERASI_DATA_API_URL" >> /var/www/html/.env
echo "PORTOFOLIO_TAGGING_API_URL=$PORTOFOLIO_TAGGING_API_URL" >> /var/www/html/.env
echo "APP_ENV=$APP_ENV" >> /var/www/html/.env
