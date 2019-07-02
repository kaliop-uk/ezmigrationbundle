FROM debian:buster

ARG debian_mirror=none
ARG timezone=none
ARG do_update_os=true

# Set up debian mirror
# (use fixed debian mirrors if you have problems building on a given day)
# -----------------------------------------------------------------------------
RUN if [ "${debian_mirror}" != "none" ]; then printf "deb ${debian_mirror} buster main\ndeb http://security.debian.org buster/updates main\ndeb ${debian_mirror} buster-updates main" > /etc/apt/sources.list; fi

# Configure timezone
# -----------------------------------------------------------------------------
RUN if [ "${timezone}" != "none" ]; then echo "${timezone}" > /etc/timezone && dpkg-reconfigure -f noninteractive tzdata; fi

# Required packages
# -----------------------------------------------------------------------------
RUN apt-get update && DEBIAN_FRONTEND=noninteractive apt-get install -y \
    apache2 \
    default-jre-headless \
    default-mysql-client \
    git \
    memcached \
    php \
    php-mbstring \
    php-cli \
    php-curl \
    php-intl \
    php-memcached \
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

# Set up eZ
# -----------------------------------------------------------------------------
RUN su test -c "cd /home/test/ezmigrationbundle && ./Tests/environment/setup.sh"

# -----------------------------------------------------------------------------
RUN if [ "${do_update_os}" != "false" ]; then apt-get update && DEBIAN_FRONTEND=noninteractive apt-get upgrade -y; fi

# Clear archives in apt cache folder
RUN apt-get clean && rm -rf /var/lib/apt/lists/*

COPY bootstrap.sh /root/bootstrap.sh
RUN chmod 755 /root/bootstrap.sh

WORKDIR /home/test/ezmigrationbundle

# 80: haproxy / varnish / apache
# 8983: solr
# 11211: memcached
EXPOSE 80 8983 11211

ENTRYPOINT ["/root/bootstrap.sh"]
