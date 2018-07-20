FROM composer AS build

WORKDIR /app
COPY . /app

RUN composer install --no-ansi --no-dev --no-interaction --no-progress --no-scripts --optimize-autoloader --prefer-dist

FROM graze/php-alpine:7.2 AS run

LABEL org.label-schema.schema-version="1.0" \
    org.label-schema.vendor="graze" \
    org.label-schema.name="morphism" \
    org.label-schema.description="extract, diff, and update databases based on differences" \
    org.label-schema.vcs-url="https://github.com/graze/morphism" \
    maintainer="developers@graze.com" \
    license="MIT"

WORKDIR /app
COPY --from=build /app /app

ARG BUILD_DATE
ARG VCS_REF

LABEL org.label-schema.vcs-ref=$VCS_REF \
    org.label-schema.build-date=$BUILD_DATE

ENTRYPOINT ["/app/bin/morphism", "--ansi"]
