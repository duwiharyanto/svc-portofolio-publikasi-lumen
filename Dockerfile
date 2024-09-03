FROM registry.uii.ac.id/uii-project/panduba/php-server:lumen5.8-4

LABEL Mantainer="Ibnu Rohan <ibnu.rohan@uii.ac.id>"

COPY app /var/www/html/app/
COPY bootstrap /var/www/html/bootstrap/
COPY config /var/www/html/config/
COPY database /var/www/html/database/
COPY public /var/www/html/public/
COPY resources /var/www/html/resources/
COPY routes /var/www/html/routes/
COPY storage/logs /var/www/html/storage/logs/
COPY tests /var/www/html/tests/
COPY vendor /var/www/html/vendor/
COPY .env.deploy /var/www/html/.env
COPY artisan /var/www/html/
COPY php-server/env.sh /root/env.sh

#COPY redis.sh /root/

#RUN chmod +x /root/redis.sh && chown -R nobody.www-data /var/www/html
RUN chown -R nobody.www-data /var/www/html

#ENTRYPOINT ["sh", "/root/redis.sh"]
