FROM debian:buster-slim

ARG debian_mirror=none
ARG do_update_os=true
ARG timezone=none

# Set up debian mirror
# (use fixed debian mirrors if you have problems building on a given day)
# -----------------------------------------------------------------------------
RUN if [ "${debian_mirror}" != "none" ]; then printf "deb ${debian_mirror} buster main\ndeb http://security.debian.org buster/updates main\ndeb ${debian_mirror} buster-updates main" > /etc/apt/sources.list; fi

# Configure timezone
# -----------------------------------------------------------------------------
RUN if [ "${timezone}" != "none" ]; then echo "${timezone}" > /etc/timezone && dpkg-reconfigure -f noninteractive tzdata; fi

# @todo Buster comes with php 7.3. Allow usage of an ARG to decide if we want to install instead php 7.4 (from SURY repos)

# Required packages
# -----------------------------------------------------------------------------
RUN mkdir -p /usr/share/man/man1
RUN apt-get update && DEBIAN_FRONTEND=noninteractive apt-get install -y \
    apache2 \
    default-jre-headless \
    default-mysql-client \
    git \
    memcached \
    php \
    php-cli \
    php-curl \
    php-gd \
    php-intl \
    php-json \
    php-memcached \
    php-mbstring \
    php-mysql \
    php-xml \
    sudo \
    unzip \
    wget \
    zip

# Install Composer (a more recent version than the Debian one)
# -----------------------------------------------------------------------------
RUN apt-get remove -y composer
COPY getcomposer.sh /root/getcomposer.sh
RUN chmod 755 /root/getcomposer.sh
RUN cd /root && ./getcomposer.sh
RUN mv /usr/local/bin/composer.phar /usr/local/bin/composer && chmod 755 /usr/local/bin/composer

# Local user
# -----------------------------------------------------------------------------
RUN addgroup --gid 1013 test && \
    adduser --system --uid=1013 --gid=1013 --home /home/test --shell /bin/bash test && \
    adduser test test && \
    mkdir -p /home/test/.ssh && \
    cp /etc/skel/.[!.]* /home/test/

RUN adduser test sudo && \
    sed -i '$ a test   ALL=\(ALL:ALL\) NOPASSWD: ALL' /etc/sudoers

RUN chown -R test:test /home/test

# -----------------------------------------------------------------------------
RUN if [ "${do_update_os}" != "false" ]; then apt-get update && DEBIAN_FRONTEND=noninteractive apt-get upgrade -y; fi

# Clear archives in apt cache folder
RUN apt-get clean && rm -rf /var/lib/apt/lists/*

COPY entrypoint.sh /root/entrypoint.sh
RUN chmod 755 /root/entrypoint.sh

WORKDIR /home/test/ezmigrationbundle

# 80: haproxy / varnish / apache
# 8983: solr
# 11211: memcached
EXPOSE 80 8983 11211

ENTRYPOINT ["/root/entrypoint.sh"]
