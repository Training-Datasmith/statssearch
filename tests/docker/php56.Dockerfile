FROM php@sha256:6ce95208609dc66df163ab936c970b3b34cd901b85c747102c5999f08ade9143

RUN docker-php-ext-install mysqli

WORKDIR /src
