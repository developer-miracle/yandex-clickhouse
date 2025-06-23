FROM php:8.3-cli-alpine3.20
COPY . /src
WORKDIR /src
VOLUME /src
RUN chmod -R 777 /src
